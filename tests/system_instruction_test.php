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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the per action system instruction override.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\system_instruction
 * @covers     \aiprovider_genericopenai\process_generate_text
 */
#[CoversClass(\aiprovider_genericopenai\system_instruction::class)]
#[CoversClass(\aiprovider_genericopenai\process_generate_text::class)]
final class system_instruction_test extends \advanced_testcase {

    /**
     * Create a provider instance.
     *
     * @param bool $allowoverride Whether the instance allows the override.
     * @return \core_ai\provider
     */
    private function create_provider(bool $allowoverride): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: '\aiprovider_genericopenai\provider',
            name: 'dummy',
            config: [
                'apiendpoint' => 'https://api.openai.com/v1',
                'apikey' => '123',
                'allowinstructionoverride' => $allowoverride ? 1 : 0,
            ],
            actionconfig: [
                \core_ai\aiactions\generate_text::class => [
                    'settings' => [
                        'model' => 'gpt-4o',
                        'endpoint' => '',
                        'systeminstruction' => 'Admin instruction',
                    ],
                ],
            ],
        );
    }

    /**
     * Create a generate text action.
     *
     * @return \core_ai\aiactions\generate_text
     */
    private function create_action(): \core_ai\aiactions\generate_text {
        return new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
        );
    }

    /**
     * Get the messages the processor would send.
     *
     * @param \core_ai\provider $provider The provider.
     * @param \core_ai\aiactions\generate_text $action The action.
     * @return array The messages.
     */
    private function get_messages(\core_ai\provider $provider, \core_ai\aiactions\generate_text $action): array {
        $processor = new process_generate_text($provider, $action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, '1');
        return json_decode((string) $request->getBody())->messages;
    }

    /**
     * Data provider for test_override.
     *
     * @return array
     */
    public static function override_provider(): array {
        return [
            'No override' => [true, null, 'Admin instruction'],
            'Override allowed' => [true, 'Plugin instruction', 'Plugin instruction'],
            'Override not allowed' => [false, 'Plugin instruction', 'Admin instruction'],
            'Empty override sends no system message' => [true, '', null],
        ];
    }

    /**
     * Test the system message sent for each combination of setting and override.
     *
     * @dataProvider override_provider
     * @param bool $allowoverride Whether the provider instance allows the override.
     * @param string|null $override The override to set, or null for none.
     * @param string|null $expected The expected system message, or null for none.
     */
    #[DataProvider('override_provider')]
    public function test_override(bool $allowoverride, ?string $override, ?string $expected): void {
        $this->resetAfterTest();
        $provider = $this->create_provider($allowoverride);
        $action = $this->create_action();
        if ($override !== null) {
            system_instruction::set_for_action($action, $override);
        }

        $messages = $this->get_messages($provider, $action);

        if ($expected === null) {
            $this->assertCount(1, $messages);
            $this->assertEquals('user', $messages[0]->role);
        } else {
            $this->assertCount(2, $messages);
            $this->assertEquals('system', $messages[0]->role);
            $this->assertEquals($expected, $messages[0]->content);
        }
    }

    /**
     * Test an override only applies to the action it was set on.
     */
    public function test_override_is_per_action(): void {
        $this->resetAfterTest();
        $provider = $this->create_provider(true);
        $action = $this->create_action();
        system_instruction::set_for_action($action, 'Plugin instruction');

        $other = $this->create_action();
        $this->assertNull(system_instruction::get_for_action($other));
        $this->assertEquals('Admin instruction', $this->get_messages($provider, $other)[0]->content);
    }
}
