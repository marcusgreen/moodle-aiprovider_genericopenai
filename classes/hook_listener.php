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

use core_ai\hook\after_ai_action_settings_form_hook;
use core_ai\hook\after_ai_provider_form_hook;

/**
 * Hook listener for Generic OpenAI provider.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {

    /**
     * Hook listener for the Open AI instance setup form.
     *
     * @param after_ai_provider_form_hook $hook The hook to add to the AI instance setup.
     */
    public static function set_form_definition_for_aiprovider_genericopenai(after_ai_provider_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_genericopenai') {
            return;
        }

        $mform = $hook->mform;

        // Required setting to store the base API endpoint.
        $mform->addElement(
            'text',
            'apiendpoint',
            get_string('apiendpoint', 'aiprovider_genericopenai'),
            ['size' => 75],
        );
        $mform->setType('apiendpoint', PARAM_URL);
        $mform->setDefault('apiendpoint', 'https://api.openai.com/v1');
        $mform->addHelpButton('apiendpoint', 'apiendpoint', 'aiprovider_genericopenai');
        $mform->addRule('apiendpoint', get_string('required'), 'required', null, 'client');

        // Required setting to store the API key.
        $mform->addElement(
            'passwordunmask',
            'apikey',
            get_string('apikey', 'aiprovider_genericopenai'),
            ['size' => 75],
        );
        $mform->addHelpButton('apikey', 'apikey', 'aiprovider_genericopenai');
        $mform->addRule('apikey', get_string('required'), 'required', null, 'client');

        // Setting to store the organization ID (sent as OpenAI-Organization header).
        $mform->addElement(
            'text',
            'orgid',
            get_string('orgid', 'aiprovider_genericopenai'),
            ['size' => 25],
        );
        $mform->setType('orgid', PARAM_TEXT);
        $mform->addHelpButton('orgid', 'orgid', 'aiprovider_genericopenai');

        // Setting to allow plugins to replace the action system instruction.
        $mform->addElement(
            'advcheckbox',
            'allowinstructionoverride',
            get_string('allowinstructionoverride', 'aiprovider_genericopenai'),
        );
        $mform->setDefault('allowinstructionoverride', 0);
        $mform->addHelpButton('allowinstructionoverride', 'allowinstructionoverride', 'aiprovider_genericopenai');

        self::add_models_setting($mform);

        // The hook does not pass the instance, so take the id from the request as /ai/configure.php does.
        helper::add_connection_test($mform, optional_param('id', 0, PARAM_INT));
    }

    /**
     * Add the models setting, with a file picker that loads a JSON file into it, to the provider instance form.
     *
     * @param \MoodleQuickForm $mform The provider instance form.
     */
    private static function add_models_setting(\MoodleQuickForm $mform): void {
        global $PAGE;

        $mform->addElement(
            'textarea',
            model_registry::CONFIG_KEY,
            get_string('models', 'aiprovider_genericopenai'),
            ['rows' => 10, 'cols' => 60, 'class' => 'text-monospace', 'spellcheck' => 'false'],
        );
        $mform->setType(model_registry::CONFIG_KEY, PARAM_RAW);
        $mform->addHelpButton(model_registry::CONFIG_KEY, 'models', 'aiprovider_genericopenai');

        $PAGE->requires->js_call_amd('aiprovider_genericopenai/modelsfile', 'init');
        $mform->addElement(
            'static',
            'modelsfile',
            get_string('modelsfile', 'aiprovider_genericopenai'),
            \html_writer::empty_tag('input', [
                'type' => 'file',
                'accept' => '.json,application/json',
                'class' => 'form-control',
                'data-action' => 'aiprovider_genericopenai-loadmodels',
                'data-target' => 'id_' . model_registry::CONFIG_KEY,
            ]),
        );
        $mform->addHelpButton('modelsfile', 'modelsfile', 'aiprovider_genericopenai');

        // A rule message is fixed when the rule is added, so the errors are worked out from the submitted value here.
        $submitted = optional_param(model_registry::CONFIG_KEY, null, PARAM_RAW);
        $errors = $submitted === null ? [] : model_registry::validate_json($submitted);
        $mform->addRule(
            model_registry::CONFIG_KEY,
            implode('<br>', array_map('s', $errors)),
            'callback',
            [model_registry::class, 'is_valid_json'],
            'server',
        );
    }

    /**
     * Hook listener for the Open AI action settings form.
     *
     * @param after_ai_action_settings_form_hook $hook The hook to add to config action settings.
     */
    public static function set_model_form_definition_for_aiprovider_genericopenai(after_ai_action_settings_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_genericopenai') {
            return;
        }

        $mform = $hook->mform;
        if (isset($mform->_elementIndex['modeltemplate'])) {
            $model = $mform->getElementValue('modeltemplate');
            if (is_array($model)) {
                $model = $model[0];
            }

            if ($model == 'custom') {
                $mform->addElement('header', 'modelsettingsheader', get_string('settings', 'aiprovider_genericopenai'));
                $settingshelp = \html_writer::tag('p', get_string('settings_help', 'aiprovider_genericopenai'));
                $mform->addElement('html', $settingshelp);
                $mform->addElement(
                    'textarea',
                    'modelextraparams',
                    get_string('extraparams', 'aiprovider_genericopenai'),
                    ['rows' => 5, 'cols' => 20],
                );
                $mform->setType('modelextraparams', PARAM_TEXT);
                $mform->addElement('static', 'modelextraparams_help', null, get_string('extraparams_help', 'aiprovider_genericopenai'));
            } else {
                // The action form adds the provider id as a hidden field before the hook runs.
                $providerid = isset($mform->_elementIndex['providerid']) ? (int) $mform->getElementValue('providerid') : 0;
                $targetmodel = helper::get_model_class($model, helper::get_provider($providerid));
                if ($targetmodel) {
                    if ($targetmodel->has_model_settings()) {
                        $mform->addElement('header', 'modelsettingsheader', get_string('settings', 'aiprovider_genericopenai'));
                        $settingshelp = \html_writer::tag('p', get_string('settings_help', 'aiprovider_genericopenai'));
                        $mform->addElement('html', $settingshelp);
                        $targetmodel->add_model_settings($mform);
                    }
                }
            }
        }
    }
}
