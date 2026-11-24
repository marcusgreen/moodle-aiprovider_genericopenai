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

use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the connection tester.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\connection_tester
 */
#[CoversClass(connection_tester::class)]
final class connection_tester_test extends \advanced_testcase {
    /** @var string A models response body listing two models. */
    private const MODELS_BODY = '{"object": "list", "data": [{"id": "gpt-4o-mini"}, {"id": "gpt-4o"}]}';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Start with no HTTP security rules, so only tests that set them are affected.
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');
    }

    /**
     * Create a provider instance.
     *
     * @param array $config Provider config to add to the defaults.
     * @param array $actionconfig Action config, keyed by action class.
     * @return provider
     */
    private function create_test_provider(array $config = [], array $actionconfig = []): provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: $config + ['apiendpoint' => 'https://api.example.com/v1/', 'apikey' => 'secret'],
            actionconfig: $actionconfig,
        );
    }

    /**
     * Test the models URL is built from the API endpoint.
     */
    public function test_get_models_url(): void {
        $tester = new connection_tester($this->create_test_provider());
        $this->assertEquals('https://api.example.com/v1/models', $tester->get_models_url());
    }

    /**
     * Test nothing is requested when the provider is not configured.
     */
    public function test_not_configured(): void {
        $history = [];
        $this->get_mocked_http_client($history);

        $result = (new connection_tester($this->create_test_provider(['apikey' => ''])))->test();

        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('connectiontest:notconfigured', 'aiprovider_genericopenai'), $result['message']);
        $this->assertCount(0, $history);
    }

    /**
     * Test a successful request lists the models and sends the authentication headers.
     */
    public function test_success(): void {
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        // No action has a model saved, so there are no warnings.
        $body = '{"data": [{"id": "qwen2.5"}, {"id": "gpt-4o"}, {"id": "llama3.1:8b"}, {"id": "llama3.1:8b"}]}';
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $body));

        $provider = $this->create_test_provider(['orgid' => 'org-1']);
        $result = (new connection_tester($provider))->test();

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['httpstatus']);
        // Sorted, without duplicates.
        $this->assertEquals(['gpt-4o', 'llama3.1:8b', 'qwen2.5'], $result['models']);
        $this->assertEquals(get_string('connectiontest:success', 'aiprovider_genericopenai', 3), $result['message']);
        $this->assertEquals([], $result['warnings']);

        // The models are remembered for the AI model dropdowns.
        $this->assertEquals($result['models'], model_registry::get_server_models($provider));

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://api.example.com/v1/models', (string) $request->getUri());
        $this->assertEquals('Bearer secret', $request->getHeaderLine('Authorization'));
        $this->assertEquals('org-1', $request->getHeaderLine('OpenAI-Organization'));
    }

    /**
     * Test the kind of model is taken from the fields some servers add, and the dropdowns use it.
     */
    public function test_model_kinds(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $body = json_encode(['data' => [
            // OpenAI, Groq and Ollama give no kind.
            ['id' => 'llama3.1:8b'],
            // Together AI.
            ['id' => 'flux-schnell', 'type' => 'image'],
            ['id' => 'bge-large', 'type' => 'embedding'],
            ['id' => 'mixtral', 'type' => 'chat'],
            // OpenRouter.
            ['id' => 'image-only', 'architecture' => ['output_modalities' => ['image']]],
            ['id' => 'text-and-image', 'architecture' => ['output_modalities' => ['text', 'image']]],
            ['id' => 'audio-only', 'architecture' => ['output_modalities' => ['audio']]],
        ]]);
        $mock->append(new Response(200, [], $body));
        $provider = $this->create_test_provider();

        $result = (new connection_tester($provider))->test();

        $this->assertCount(7, $result['models']);
        $this->assertEquals(
            ['llama3.1:8b', 'mixtral', 'text-and-image'],
            model_registry::get_server_models($provider, model_registry::KIND_TEXT),
        );
        $this->assertEquals(
            ['flux-schnell', 'image-only'],
            model_registry::get_server_models($provider, model_registry::KIND_IMAGE),
        );
        $this->assertEquals(['llama3.1:8b', 'mixtral', 'text-and-image'], array_keys(
            model_registry::get_model_options(\aiprovider_genericopenai\aimodel\openai_base::MODEL_TYPE_TEXT, $provider),
        ));
        $this->assertEquals(['flux-schnell', 'image-only'], array_keys(
            model_registry::get_model_options(\aiprovider_genericopenai\aimodel\openai_base::MODEL_TYPE_IMAGE, $provider),
        ));
    }

    /**
     * Test a warning is given for each enabled action whose model is not listed by the server.
     */
    public function test_model_not_listed(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], self::MODELS_BODY));
        $provider = $this->create_test_provider(actionconfig: [
            generate_text::class => ['enabled' => true, 'settings' => ['model' => 'gpt-4o']],
            summarise_text::class => ['enabled' => true, 'settings' => ['model' => 'llama3.1:8b']],
            // Disabled actions are not checked.
            generate_image::class => ['enabled' => false, 'settings' => ['model' => 'dall-e-3']],
        ]);

        $result = (new connection_tester($provider))->test();

        $this->assertTrue($result['success']);
        $this->assertEquals([
            get_string('connectiontest:modelnotlisted', 'aiprovider_genericopenai', [
                'action' => summarise_text::get_name(),
                'model' => 'llama3.1:8b',
            ]),
        ], $result['warnings']);
    }

    /**
     * Test a server that lists no models gives no model warnings.
     */
    public function test_no_models_listed(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], '{"object": "list", "data": []}'));
        $provider = $this->create_test_provider(actionconfig: [
            generate_text::class => ['enabled' => true, 'settings' => ['model' => 'gpt-4o']],
        ]);

        $result = (new connection_tester($provider))->test();

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['models']);
        $this->assertEquals([], $result['warnings']);
    }

    /**
     * Test HTTP error responses.
     *
     * @dataProvider http_error_provider
     * @param int $status The HTTP status.
     * @param string $body The response body.
     * @param string $identifier The expected message string identifier.
     * @param string $error The expected error detail.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('http_error_provider')]
    public function test_http_error(int $status, string $body, string $identifier, string $error): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response($status, [], $body));

        $provider = $this->create_test_provider();
        $result = (new connection_tester($provider))->test();

        $this->assertFalse($result['success']);
        $this->assertEquals($status, $result['httpstatus']);
        $this->assertEquals([], model_registry::get_server_models($provider));
        $this->assertEquals(get_string($identifier, 'aiprovider_genericopenai', [
            'url' => 'https://api.example.com/v1/models',
            'status' => $status,
            'error' => $error,
        ]), $result['message']);
    }

    /**
     * Data provider for test_http_error.
     *
     * @return array
     */
    public static function http_error_provider(): array {
        return [
            'Bad key' => [401, '{"error": {"message": "Incorrect API key provided"}}', 'connectiontest:unauthorised',
                'Incorrect API key provided'],
            'Forbidden' => [403, '', 'connectiontest:unauthorised', 'Forbidden'],
            'Not found' => [404, '404 page not found', 'connectiontest:httperror', '404 page not found'],
            'Server error' => [500, '{"detail": "Internal error"}', 'connectiontest:httperror', 'Internal error'],
        ];
    }

    /**
     * Test a 200 response that is not a models list.
     */
    public function test_invalid_response(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], '<html><body>Welcome</body></html>'));

        $result = (new connection_tester($this->create_test_provider()))->test();

        $this->assertFalse($result['success']);
        $this->assertEquals(
            get_string('connectiontest:invalidresponse', 'aiprovider_genericopenai', 'https://api.example.com/v1/models'),
            $result['message'],
        );
    }

    /**
     * Test a connection failure.
     */
    public function test_no_connection(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $url = 'https://api.example.com/v1/models';
        $mock->append(new ConnectException('Could not resolve host', new Request('GET', $url)));

        $result = (new connection_tester($this->create_test_provider()))->test();

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['httpstatus']);
        $this->assertEquals(get_string('connectiontest:noconnection', 'aiprovider_genericopenai', [
            'url' => $url,
            'error' => 'Could not resolve host',
        ]), $result['message']);
    }

    /**
     * Test a blocked API endpoint is reported without a request being made.
     */
    public function test_blocked_endpoint(): void {
        $history = [];
        $this->get_mocked_http_client($history);
        set_config('curlsecurityblockedhosts', 'api.example.com');

        $result = (new connection_tester($this->create_test_provider()))->test();

        $this->assertFalse($result['success']);
        $this->assertEquals(
            get_string('connectiontest:blocked', 'aiprovider_genericopenai', 'https://api.example.com/v1/models'),
            $result['message'],
        );
        $this->assertCount(0, $history);
    }

    /**
     * Test a blocked port is reported.
     */
    public function test_blocked_port(): void {
        $this->get_mocked_http_client();
        set_config('curlsecurityallowedport', "443\n80");

        $result = (new connection_tester($this->create_test_provider(['apiendpoint' => 'http://ollama.example.com:11434/v1'])))
            ->test();

        $this->assertFalse($result['success']);
        $this->assertEquals(
            get_string('connectiontest:blocked', 'aiprovider_genericopenai', 'http://ollama.example.com:11434/v1/models'),
            $result['message'],
        );
    }

    /**
     * Test a blocked action endpoint gives a warning but does not stop the test.
     */
    public function test_blocked_action_endpoint(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], self::MODELS_BODY));
        set_config('curlsecurityblockedhosts', 'blocked.example.com');
        $endpoint = 'https://blocked.example.com/v1/chat/completions';
        $provider = $this->create_test_provider(actionconfig: [
            generate_text::class => ['enabled' => true, 'settings' => ['model' => 'gpt-4o', 'endpoint' => $endpoint]],
        ]);

        $result = (new connection_tester($provider))->test();

        $this->assertTrue($result['success']);
        $this->assertEquals([
            get_string('connectiontest:actionblocked', 'aiprovider_genericopenai', [
                'action' => generate_text::get_name(),
                'endpoint' => $endpoint,
            ]),
        ], $result['warnings']);
    }
}
