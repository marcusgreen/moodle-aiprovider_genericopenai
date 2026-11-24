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

use aiprovider_genericopenai\connection_tester;
use aiprovider_genericopenai\provider;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Test the connection of a saved provider instance by listing the models.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_connection extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'providerid' => new external_value(PARAM_INT, 'The provider instance id'),
        ]);
    }

    /**
     * Test the connection of a provider instance.
     *
     * @param int $providerid The provider instance id.
     * @return array
     */
    public static function execute(int $providerid): array {
        ['providerid' => $providerid] = self::validate_parameters(self::execute_parameters(), [
            'providerid' => $providerid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $manager = \core\di::get(\core_ai\manager::class);
        $providers = $manager->get_provider_instances(['id' => $providerid]);
        $instance = reset($providers);
        if (!$instance instanceof provider) {
            throw new \moodle_exception('connectiontest:invalidprovider', 'aiprovider_genericopenai');
        }

        return (new connection_tester($instance))->test();
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the models were listed'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
            'httpstatus' => new external_value(PARAM_INT, 'HTTP status of the models request, 0 if none was made'),
            'models' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Model name'),
                'Models listed by the server',
            ),
            'warnings' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Warning message'),
                'Problems found that did not stop the test',
            ),
        ]);
    }
}
