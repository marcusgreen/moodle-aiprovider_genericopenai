<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_genericopenai;

use aiprovider_genericopenai\test\testcase_helper_trait;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for behaviour needed by OpenAI compatible servers that are not OpenAI.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\abstract_processor
 * @covers     \aiprovider_genericopenai\process_generate_text
 * @covers     \aiprovider_genericopenai\process_generate_image
 * @covers     \aiprovider_genericopenai\provider
 */
#[CoversClass(\aiprovider_genericopenai\abstract_processor::class)]
#[CoversClass(\aiprovider_genericopenai\process_generate_text::class)]
#[CoversClass(\aiprovider_genericopenai\process_generate_image::class)]
#[CoversClass(\aiprovider_genericopenai\provider::class)]
final class compatibility_test extends \advanced_testcase {

    use testcase_helper_trait;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a text processor.
     *
     * @param array $actionconfig The action configuration.
     * @return process_generate_text
     */
    private function create_text_processor(array $actionconfig = []): process_generate_text {
        $provider = $this->create_provider(
            \core_ai\aiactions\generate_text::class,
            array_merge(['systeminstruction' => get_string('action_generate_text_instruction', 'core_ai')], $actionconfig),
        );
        $action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
        );
        return new process_generate_text($provider, $action);
    }

    /**
     * Create an image processor.
     *
     * @param array $actionconfig The action configuration.
     * @return process_generate_image
     */
    private function create_image_processor(array $actionconfig = []): process_generate_image {
        $provider = $this->create_provider(
            \core_ai\aiactions\generate_image::class,
            array_merge(['model' => 'dall-e-3'], $actionconfig),
        );
        $action = new \core_ai\aiactions\generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
            quality: 'hd',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
        return new process_generate_image($provider, $action);
    }

    /**
     * Test the action endpoint overrides the provider endpoint, and the provider endpoint is used when it is empty.
     */
    public function test_get_endpoint(): void {
        $method = new \ReflectionMethod(abstract_processor::class, 'get_endpoint');

        $processor = $this->create_text_processor(['endpoint' => 'https://example.com/custom/chat']);
        $this->assertEquals('https://example.com/custom/chat', (string) $method->invoke($processor));

        $processor = $this->create_text_processor(['endpoint' => '']);
        $this->assertEquals('https://api.openai.com/v1/chat/completions', (string) $method->invoke($processor));

        $processor = $this->create_image_processor(['endpoint' => '']);
        $this->assertEquals('https://api.openai.com/v1/images/generations', (string) $method->invoke($processor));
    }

    /**
     * Test a trailing slash on the provider endpoint is removed.
     */
    public function test_get_api_endpoint_trailing_slash(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: '\aiprovider_genericopenai\provider',
            name: 'dummy',
            config: ['apikey' => '123', 'apiendpoint' => ' http://localhost:11434/v1/ '],
        );
        $this->assertEquals('http://localhost:11434/v1', $provider->get_api_endpoint());
    }

    /**
     * Data provider for test_handle_api_error.
     *
     * @return array
     */
    public static function api_error_provider(): array {
        return [
            'OpenAI' => [400, json_encode(['error' => ['message' => 'Bad model']]), 'Bad model'],
            'Ollama' => [404, json_encode(['error' => 'model "llama3" not found']), 'model "llama3" not found'],
            'vLLM' => [400, json_encode(['object' => 'error', 'message' => 'Context too long']), 'Context too long'],
            'FastAPI' => [422, json_encode(['detail' => 'Field required']), 'Field required'],
            'Plain text' => [502, '<html><body>Bad gateway</body></html>', 'Bad gateway'],
            'Long plain text' => [502, str_repeat('x', 201), 'Bad Gateway'],
            'Unknown JSON' => [500, json_encode(['foo' => 'bar']), 'Internal Server Error'],
            'Empty body' => [503, '', 'Service Unavailable'],
        ];
    }

    /**
     * Test error messages are found in the different error formats.
     *
     * @dataProvider api_error_provider
     * @param int $status The HTTP status.
     * @param string $body The response body.
     * @param string $expected The expected error message.
     */
    #[DataProvider('api_error_provider')]
    public function test_handle_api_error(int $status, string $body, string $expected): void {
        $processor = $this->create_text_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        $result = $method->invoke($processor, new Response($status, [], $body));

        $this->assertEquals($status, $result['errorcode']);
        $this->assertEquals($expected, $result['errormessage']);
        $this->assertDebuggingCalled();
    }

    /**
     * Test a connection failure, which has no HTTP status code, returns an error rather than throwing.
     */
    public function test_query_ai_api_connection_failure(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new ConnectException('Connection refused', new Request('POST', 'http://localhost')));

        $processor = $this->create_text_processor();
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
        $this->assertStringContainsString('Connection refused', $result['errormessage']);
    }

    /**
     * Test a text response without the optional OpenAI fields is handled.
     */
    public function test_handle_api_success_minimal_response(): void {
        $processor = $this->create_text_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $body = json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello']]]]);
        $result = $method->invoke($processor, new Response(200, [], $body));

        $this->assertTrue($result['success']);
        $this->assertEquals('', $result['id']);
        $this->assertEquals('', $result['fingerprint']);
        $this->assertEquals('Hello', $result['generatedcontent']);
        $this->assertEquals('', $result['finishreason']);
        $this->assertEquals(0, $result['prompttokens']);
        $this->assertEquals(0, $result['completiontokens']);
        $this->assertEquals('gpt-4o', $result['model']);
    }

    /**
     * Test an image returned as a URL is downloaded into a draft file.
     */
    public function test_query_ai_api_image_url(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'created' => 1719140500,
            'data' => [['url' => 'https://images.example.com/generated/abc123.jpg?sig=xyz']],
        ])));
        $mock->append(new Response(
            200,
            ['Content-Type' => 'image/jpeg'],
            file_get_contents(self::get_fixture_path('aiprovider_genericopenai', 'test.jpg')),
        ));

        $processor = $this->create_image_processor();
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(\stored_file::class, $result['draftfile']);
        $this->assertEquals('abc123.jpg', $result['draftfile']->get_filename());
        $this->assertCount(2, $history);
        $this->assertEquals('images.example.com', $history[1]['request']->getUri()->getHost());
    }

    /**
     * Test a failed image download returns an error rather than throwing.
     */
    public function test_query_ai_api_image_url_download_failure(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'data' => [['url' => 'https://images.example.com/generated/abc123.jpg']],
        ])));
        $mock->append(new Response(403));

        $processor = $this->create_image_processor();
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['errorcode']);
    }

    /**
     * Test an image response with no image data returns an error.
     */
    public function test_handle_api_success_no_image_data(): void {
        $processor = $this->create_image_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, new Response(200, [], json_encode(['data' => [[]]])));

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('error:noimagedata', 'aiprovider_genericopenai'), $result['errormessage']);
    }
}
