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

use aiprovider_genericopenai\aimodel\configured_model;

/**
 * Helper class for the Generic OpenAI provider.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get a model offered by a provider instance.
     *
     * @param string $modelname Model name.
     * @param \core_ai\provider|null $provider The provider instance, or null for the bundled models only.
     * @return configured_model|null The model, or null if it is not offered (for example a custom model).
     */
    public static function get_model_class(string $modelname, ?\core_ai\provider $provider = null): ?configured_model {
        return model_registry::get_model($modelname, $provider);
    }

    /**
     * Get a saved provider instance of this plugin.
     *
     * @param int $providerid The provider instance id.
     * @return provider|null The instance, or null if there is none with the id.
     */
    public static function get_provider(int $providerid): ?provider {
        if ($providerid <= 0) {
            return null;
        }
        $providers = \core\di::get(\core_ai\manager::class)->get_provider_instances(['id' => $providerid]);
        $provider = reset($providers);
        return $provider instanceof provider ? $provider : null;
    }

    /**
     * Extract a readable error message from an error response body.
     *
     * OpenAI compatible servers do not agree on an error format, so the common shapes are checked:
     * OpenAI {"error": {"message": ""}}, Ollama {"error": ""}, vLLM {"message": ""} and FastAPI {"detail": ""}.
     * Short non-JSON bodies are returned as plain text.
     *
     * @param string $body The response body.
     * @return string The error detail, or an empty string if none was found.
     */
    public static function get_error_detail(string $body): string {
        $bodyobj = json_decode($body);
        if (is_object($bodyobj)) {
            $candidates = [
                $bodyobj->error->message ?? null,
                $bodyobj->error ?? null,
                $bodyobj->message ?? null,
                $bodyobj->detail ?? null,
            ];
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
            return '';
        }

        $text = trim(strip_tags($body));
        return \core_text::strlen($text) <= 200 ? $text : '';
    }

    /**
     * Add the connection test button for a saved provider instance to a form.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param int $providerid The provider instance id. Nothing is added for an unsaved instance.
     * @param int $modeltype On an action form, the type of the AI model dropdown to fill with the models found, else 0.
     */
    public static function add_connection_test(\MoodleQuickForm $mform, int $providerid, int $modeltype = 0): void {
        global $OUTPUT, $PAGE;

        if ($providerid <= 0) {
            return;
        }
        $PAGE->requires->js_call_amd('aiprovider_genericopenai/connectiontest', 'init');
        $mform->addElement(
            'static',
            'connectiontest',
            '',
            $OUTPUT->render_from_template('aiprovider_genericopenai/connection_test', [
                'providerid' => $providerid,
                'modeltype' => $modeltype,
            ]),
        );
    }
}
