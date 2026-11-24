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

use core_ai\aiactions\base;

/**
 * Per action override of the system instruction set in the provider action settings.
 *
 * A plugin sets an override on an action object before passing it to \core_ai\manager::process_action().
 * The override is only used if the provider instance allows it (the allowinstructionoverride setting).
 * Overrides are held against the action object, so they end when the action object is destroyed and
 * cannot affect any other request.
 *
 * Usage:
 *     $action = new \core_ai\aiactions\generate_text(...);
 *     if (class_exists(\aiprovider_genericopenai\system_instruction::class)) {
 *         \aiprovider_genericopenai\system_instruction::set_for_action($action, 'My instruction');
 *     }
 *     $response = \core\di::get(\core_ai\manager::class)->process_action($action);
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class system_instruction {
    /** @var \WeakMap|null Instruction overrides keyed by action object. */
    private static ?\WeakMap $overrides = null;

    /**
     * Set the system instruction to use for an action instead of the one in the provider action settings.
     *
     * @param base $action The action that will be processed.
     * @param string $instruction The system instruction. An empty string sends no system message.
     */
    public static function set_for_action(base $action, string $instruction): void {
        self::$overrides ??= new \WeakMap();
        self::$overrides[$action] = $instruction;
    }

    /**
     * Get the system instruction override for an action.
     *
     * @param base $action The action being processed.
     * @return string|null The override, or null if none was set.
     */
    public static function get_for_action(base $action): ?string {
        return self::$overrides[$action] ?? null;
    }

    /**
     * Check whether a provider instance allows plugins to override the system instruction.
     *
     * @param \core_ai\provider $provider The provider instance.
     * @return bool
     */
    public static function is_override_allowed(\core_ai\provider $provider): bool {
        return !empty($provider->config['allowinstructionoverride']);
    }
}
