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

namespace aiprovider_genericopenai\task;

use aiprovider_genericopenai\model_registry;
use aiprovider_genericopenai\provider;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the task that refreshes the models listed by the API servers.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\task\refresh_server_models
 */
#[CoversClass(refresh_server_models::class)]
final class refresh_server_models_test extends \advanced_testcase {
    /**
     * Create a provider instance.
     *
     * @param string $name The instance name.
     * @param bool $enabled Whether the instance is enabled.
     * @return provider
     */
    private function create_test_provider(string $name, bool $enabled = true): provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: provider::class,
            name: $name,
            enabled: $enabled,
            config: ['apiendpoint' => "https://{$name}.example.com/v1", 'apikey' => 'x'],
        );
        return $provider;
    }

    /**
     * Test enabled instances are refreshed, and a failed request keeps the last list.
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        set_config('curlsecurityblockedhosts', '');
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $up = $this->create_test_provider('up');
        $down = $this->create_test_provider('down');
        $off = $this->create_test_provider('off', false);
        model_registry::set_server_models($down, ['old-model']);

        $mock->append(new Response(200, [], '{"data": [{"id": "new-model"}]}'));
        $mock->append(new Response(503, [], ''));
        $this->expectOutputRegex('/up: .*\ndown: .*503/s');
        (new refresh_server_models())->execute();

        // The disabled instance is not asked.
        $this->assertCount(2, $history);
        $this->assertEquals(['new-model'], model_registry::get_server_models($up));
        $this->assertEquals(['old-model'], model_registry::get_server_models($down));
        $this->assertEquals([], model_registry::get_server_models($off));
    }
}
