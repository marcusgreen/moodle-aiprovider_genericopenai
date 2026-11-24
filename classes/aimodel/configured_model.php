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

namespace aiprovider_genericopenai\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * An AI model described by a definition array, from db/models.json or the models setting of a provider instance.
 *
 * Definitions are checked by {@see \aiprovider_genericopenai\model_registry::validate_definitions()} before use.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configured_model extends base implements openai_base, openai_image_base {
    /** @var array Setting types in a definition, mapped to the PARAM type used for the form element. */
    public const SETTING_TYPES = [
        'int' => PARAM_INT,
        'float' => PARAM_FLOAT,
        'text' => PARAM_TEXT,
        'select' => PARAM_ALPHANUMEXT,
    ];

    /**
     * Constructor.
     *
     * @param array $definition The model definition.
     */
    public function __construct(
        /** @var array The model definition. */
        private readonly array $definition,
    ) {
    }

    #[\Override]
    public function get_model_name(): string {
        return $this->definition['name'];
    }

    #[\Override]
    public function get_model_display_name(): string {
        return $this->definition['displayname'] ?? $this->definition['name'];
    }

    #[\Override]
    public function model_type(): array {
        return [$this->definition['type'] === 'image' ? self::MODEL_TYPE_IMAGE : self::MODEL_TYPE_TEXT];
    }

    /**
     * Whether this model is the default for its type.
     *
     * @return bool
     */
    public function is_default(): bool {
        return !empty($this->definition['default']);
    }

    /**
     * Get the settings in the format used by the action settings form.
     *
     * @return array Settings keyed by request parameter name, each with 'elementtype', 'type', 'label' and optionally
     *               'help' and 'options'. A label or help is either a string or ['identifier' => .., 'component' => ..].
     */
    #[\Override]
    public function get_model_settings(): array {
        $settings = [];
        foreach ($this->definition['settings'] ?? [] as $key => $setting) {
            $settings[$key] = [
                'elementtype' => $setting['type'] === 'select' ? 'select' : 'text',
                'type' => self::SETTING_TYPES[$setting['type']],
                'label' => $setting['label'] ?? $key,
            ];
            if (isset($setting['help'])) {
                $settings[$key]['help'] = $setting['help'];
            }
            if ($setting['type'] === 'select') {
                $settings[$key]['options'] = $setting['options'];
            }
        }
        return $settings;
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        foreach ($this->get_model_settings() as $key => $setting) {
            $label = self::get_text($setting['label']);
            if ($setting['elementtype'] === 'select') {
                // An empty choice leaves the parameter out of the request, so the server default is used.
                $options = ['' => get_string('choosedots')] + array_combine($setting['options'], $setting['options']);
                $mform->addElement('select', $key, $label, $options);
            } else {
                $mform->addElement('text', $key, $label);
            }
            $mform->setType($key, $setting['type']);

            if (!isset($setting['help'])) {
                continue;
            }
            if (is_array($setting['help'])) {
                $mform->addHelpButton($key, $setting['help']['identifier'], $setting['help']['component']);
            } else {
                // Help written into a provider instance setting has no language string, so it is shown below the field.
                $mform->addElement('static', "{$key}_description", '', s($setting['help']));
            }
        }
    }

    #[\Override]
    public function response_format(): ?string {
        return $this->definition['responseformat'] ?? null;
    }

    #[\Override]
    public function get_output_format(): ?string {
        return $this->definition['outputformat'] ?? null;
    }

    #[\Override]
    public function calculate_size(string $ratio): string {
        if (!isset($this->definition['sizes'][$ratio])) {
            throw new \coding_exception('Invalid aspect ratio: ' . $ratio);
        }
        return $this->definition['sizes'][$ratio];
    }

    #[\Override]
    public function calculate_quality(string $quality): string {
        if (!isset($this->definition['quality'][$quality])) {
            throw new \coding_exception('Invalid quality: ' . $quality);
        }
        return $this->definition['quality'][$quality];
    }

    /**
     * Get the HTML of a label value.
     *
     * @param string|array $text Plain text, or ['identifier' => .., 'component' => ..] for a language string.
     * @return string
     */
    private static function get_text(string|array $text): string {
        return is_array($text) ? get_string($text['identifier'], $text['component']) : s($text);
    }
}
