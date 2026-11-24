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

use aiprovider_genericopenai\connection_tester;
use aiprovider_genericopenai\provider;

/**
 * Refresh the models listed by the API server of each enabled provider instance.
 *
 * The AI model dropdowns then offer the models the server has now, without a page waiting for the server. A failed
 * request leaves the list from the last successful one.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_server_models extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task:refreshservermodels', 'aiprovider_genericopenai');
    }

    #[\Override]
    public function execute(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        foreach ($manager->get_provider_instances(['provider' => provider::class]) as $instance) {
            if (!$instance instanceof provider || !$instance->enabled || !$instance->is_provider_configured()) {
                continue;
            }
            $result = (new connection_tester($instance))->test();
            mtrace("{$instance->name}: {$result['message']}");
        }
    }
}
