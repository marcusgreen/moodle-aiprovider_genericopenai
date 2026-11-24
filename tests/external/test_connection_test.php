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

namespace aiprovider_genericopenai\external;

use core_external\external_api;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the test_connection external function.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\external\test_connection
 */
#[CoversClass(test_connection::class)]
final class test_connection_test extends \core_external\tests\externallib_testcase {
    /**
     * Create a provider instance.
     *
     * @param string $classname The provider class.
     * @return \core_ai\provider
     */
    private function create_test_provider(string $classname = \aiprovider_genericopenai\provider::class): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: $classname,
            name: 'dummy',
            config: ['apiendpoint' => 'https://api.example.com/v1', 'apikey' => 'secret'],
        );
    }

    /**
     * Test an admin can run the test.
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('curlsecurityblockedhosts', '');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, [], '{"data": [{"id": "gpt-4o"}]}'));
        $provider = $this->create_test_provider();

        $result = test_connection::execute($provider->id);
        $result = external_api::clean_returnvalue(test_connection::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(['gpt-4o'], $result['models']);
        $this->assertEquals(200, $result['httpstatus']);
    }

    /**
     * Test a user without moodle/site:config cannot run the test.
     */
    public function test_execute_no_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = $this->create_test_provider();

        $this->expectException(\required_capability_exception::class);
        test_connection::execute($provider->id);
    }

    /**
     * Test an unknown provider id.
     */
    public function test_execute_unknown_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connectiontest:invalidprovider', 'aiprovider_genericopenai'));
        test_connection::execute(-1);
    }

    /**
     * Test an instance of another provider plugin is rejected.
     */
    public function test_execute_other_provider(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = $this->create_test_provider(\aiprovider_openai\provider::class);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('connectiontest:invalidprovider', 'aiprovider_genericopenai'));
        test_connection::execute($provider->id);
    }
}
