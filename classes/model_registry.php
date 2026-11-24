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
use aiprovider_genericopenai\aimodel\openai_base;

/**
 * The AI models offered by a provider instance.
 *
 * The bundled models are in db/models.json. A provider instance can add to them, or replace them, with the JSON in its
 * models setting:
 *
 *     {"mode": "extend", "models": [{"name": "llama3.1:8b", "type": "text"}, ...]}
 *
 * A plain list of models is the same as mode extend. In extend mode a model with the name of a bundled model replaces it.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class model_registry {
    /** @var string The config key of the provider instance models setting. */
    public const CONFIG_KEY = 'models';

    /** @var string Model option in the action settings form for a model name typed by the admin. */
    public const CUSTOM_MODEL = 'custom';

    /** @var int The most errors reported for one JSON value. */
    private const MAX_ERRORS = 5;

    /**
     * @var string[] Setting names a model may not use, because they are form fields or request parameters the plugin sets.
     */
    private const RESERVED_SETTINGS = [
        'action', 'custommodel', 'enabled', 'endpoint', 'id', 'messages', 'model', 'modelextraparams', 'modelsettings',
        'modeltemplate', 'n', 'output_format', 'prompt', 'provider', 'providerid', 'quality', 'response_format', 'returnurl',
        'size', 'systeminstruction', 'updateactionsettings', 'user',
    ];

    /** @var string[] Aspect ratios an image model must map to a size. */
    private const RATIOS = ['square', 'landscape', 'portrait'];

    /** @var string[] Quality options an image model must map to an API value. */
    private const QUALITIES = ['standard', 'hd'];

    /** @var array|null The bundled model definitions. */
    private static ?array $bundled = null;

    /** @var configured_model[][] Models keyed by a hash of the provider instance models setting, then by name. */
    private static array $cache = [];

    /**
     * Get the models offered by a provider instance.
     *
     * @param \core_ai\provider|null $provider The provider instance, or null for the bundled models only.
     * @return configured_model[] Models keyed by name, in the order they are offered.
     */
    public static function get_models(?\core_ai\provider $provider = null): array {
        $json = trim((string) ($provider->config[self::CONFIG_KEY] ?? ''));
        $cachekey = sha1($json);
        if (!isset(self::$cache[$cachekey])) {
            $models = [];
            foreach (self::get_definitions($json) as $definition) {
                $models[$definition['name']] = new configured_model($definition);
            }
            self::$cache[$cachekey] = $models;
        }
        return self::$cache[$cachekey];
    }

    /**
     * Get a model by name.
     *
     * @param string $name The model name.
     * @param \core_ai\provider|null $provider The provider instance, or null for the bundled models only.
     * @return configured_model|null The model, or null if it is not offered (for example a custom model).
     */
    public static function get_model(string $name, ?\core_ai\provider $provider = null): ?configured_model {
        return self::get_models($provider)[$name] ?? null;
    }

    /**
     * Get the models of one type.
     *
     * @param int $type openai_base::MODEL_TYPE_TEXT or openai_base::MODEL_TYPE_IMAGE.
     * @param \core_ai\provider|null $provider The provider instance, or null for the bundled models only.
     * @return configured_model[] Models keyed by name.
     */
    public static function get_models_of_type(int $type, ?\core_ai\provider $provider = null): array {
        return array_filter(
            self::get_models($provider),
            fn(configured_model $model): bool => in_array($type, $model->model_type()),
        );
    }

    /**
     * Get the name of the default model of a type.
     *
     * This is the model marked as default, or else the first model of the type.
     *
     * @param int $type openai_base::MODEL_TYPE_TEXT or openai_base::MODEL_TYPE_IMAGE.
     * @param \core_ai\provider|null $provider The provider instance, or null for the bundled models only.
     * @return string|null The model name, or null if no model of the type is offered.
     */
    public static function get_default_model(int $type, ?\core_ai\provider $provider = null): ?string {
        $models = self::get_models_of_type($type, $provider);
        foreach ($models as $name => $model) {
            if ($model->is_default()) {
                return $name;
            }
        }
        return array_key_first($models);
    }

    /**
     * Get the options for an AI model dropdown.
     *
     * These are the models of the type, then the models last listed by the API server (see {@see set_server_models()})
     * that are not in the model lists. The type of a server model is not known, so those are offered as text models.
     *
     * @param int $type openai_base::MODEL_TYPE_TEXT or openai_base::MODEL_TYPE_IMAGE.
     * @param provider|null $provider The provider instance, or null for the bundled models only.
     * @return string[] Display names keyed by model name.
     */
    public static function get_model_options(int $type, ?provider $provider = null): array {
        $options = array_map(
            fn(configured_model $model): string => $model->get_model_display_name(),
            self::get_models_of_type($type, $provider),
        );
        if ($type === openai_base::MODEL_TYPE_TEXT) {
            $known = self::get_models($provider);
            foreach (self::get_server_models($provider) as $name) {
                if (!isset($known[$name])) {
                    $options[$name] = $name;
                }
            }
        }
        return $options;
    }

    /**
     * Remember the models listed by the API server of a provider instance, for the AI model dropdowns.
     *
     * @param provider $provider The provider instance.
     * @param string[] $models The model names.
     */
    public static function set_server_models(provider $provider, array $models): void {
        if (empty($provider->id)) {
            return;
        }
        \cache::make('aiprovider_genericopenai', 'servermodels')->set($provider->id, [
            'connection' => self::get_connection_hash($provider),
            'models' => array_values($models),
        ]);
    }

    /**
     * Get the models last listed by the API server of a provider instance.
     *
     * A list from before the API endpoint or key was changed is not used.
     *
     * @param provider|null $provider The provider instance.
     * @return string[] The model names, empty if the server has not been asked recently.
     */
    public static function get_server_models(?provider $provider): array {
        if (empty($provider->id)) {
            return [];
        }
        $cached = \cache::make('aiprovider_genericopenai', 'servermodels')->get($provider->id);
        if (!is_array($cached) || ($cached['connection'] ?? null) !== self::get_connection_hash($provider)) {
            return [];
        }
        return $cached['models'];
    }

    /**
     * Get a value that changes when the API endpoint or key of a provider instance changes.
     *
     * @param provider $provider The provider instance.
     * @return string
     */
    private static function get_connection_hash(provider $provider): string {
        return sha1($provider->get_api_endpoint() . "\n" . ($provider->config['apikey'] ?? ''));
    }

    /**
     * Check a models setting.
     *
     * @param string $json The JSON entered in the models setting. An empty value is valid and means the bundled models.
     * @return string[] Error messages, empty if the value is valid.
     */
    public static function validate_json(string $json): array {
        if (trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [get_string('models:error:json', 'aiprovider_genericopenai', json_last_error_msg())];
        }
        if (!is_array($data)) {
            return [get_string('models:error:structure', 'aiprovider_genericopenai')];
        }
        if (array_is_list($data)) {
            $data = ['models' => $data];
        }
        if (!isset($data['models']) || !is_array($data['models']) || !array_is_list($data['models'])) {
            return [get_string('models:error:structure', 'aiprovider_genericopenai')];
        }
        $errors = [];
        if (isset($data['mode']) && !in_array($data['mode'], ['extend', 'replace'], true)) {
            $errors[] = get_string('models:error:mode', 'aiprovider_genericopenai');
        }
        $errors = array_merge($errors, self::validate_definitions($data['models']));
        return array_slice($errors, 0, self::MAX_ERRORS);
    }

    /**
     * Whether a models setting is valid. Used as a form rule callback.
     *
     * @param mixed $json The submitted value.
     * @return bool
     */
    public static function is_valid_json(mixed $json): bool {
        return self::validate_json((string) $json) === [];
    }

    /**
     * Check a list of model definitions.
     *
     * @param array $definitions The model definitions.
     * @return string[] Error messages, empty if all definitions are valid.
     */
    public static function validate_definitions(array $definitions): array {
        $errors = [];
        $names = [];
        foreach ($definitions as $index => $definition) {
            $name = is_array($definition) && is_string($definition['name'] ?? null) ? $definition['name'] : '';
            foreach (self::validate_definition($definition, $names) as $error) {
                $errors[] = get_string('models:error:model', 'aiprovider_genericopenai', [
                    'index' => $index + 1,
                    'name' => $name,
                    'error' => $error,
                ]);
            }
            $names[] = $name;
        }
        return $errors;
    }

    /**
     * Check one model definition.
     *
     * @param mixed $definition The model definition.
     * @param string[] $names Names of the models before this one in the list.
     * @return string[] Error messages.
     */
    private static function validate_definition(mixed $definition, array $names): array {
        if (!is_array($definition) || array_is_list($definition)) {
            return [get_string('models:error:notobject', 'aiprovider_genericopenai')];
        }

        $errors = [];
        $name = $definition['name'] ?? null;
        if (
            !is_string($name) || trim($name) === '' || $name !== trim($name) || \core_text::strlen($name) > 255
                || $name === self::CUSTOM_MODEL
        ) {
            $errors[] = get_string('models:error:name', 'aiprovider_genericopenai');
        } else if (in_array($name, $names, true)) {
            $errors[] = get_string('models:error:duplicate', 'aiprovider_genericopenai');
        }
        if (!in_array($definition['type'] ?? null, ['text', 'image'], true)) {
            $errors[] = get_string('models:error:type', 'aiprovider_genericopenai');
        }
        if (isset($definition['displayname']) && !is_string($definition['displayname'])) {
            $errors[] = get_string('models:error:text', 'aiprovider_genericopenai', 'displayname');
        }
        if (isset($definition['default']) && !is_bool($definition['default'])) {
            $errors[] = get_string('models:error:default', 'aiprovider_genericopenai');
        }
        $errors = array_merge($errors, self::validate_settings($definition['settings'] ?? []));

        if (($definition['type'] ?? null) === 'image') {
            foreach (['responseformat', 'outputformat'] as $field) {
                if (isset($definition[$field]) && !is_string($definition[$field])) {
                    $errors[] = get_string('models:error:text', 'aiprovider_genericopenai', $field);
                }
            }
            if (!self::is_map($definition['sizes'] ?? null, self::RATIOS, '/^[1-9][0-9]*x[1-9][0-9]*$/')) {
                $errors[] = get_string('models:error:sizes', 'aiprovider_genericopenai');
            }
            if (!self::is_map($definition['quality'] ?? null, self::QUALITIES, '/^\S+$/')) {
                $errors[] = get_string('models:error:quality', 'aiprovider_genericopenai');
            }
        }
        return $errors;
    }

    /**
     * Check the settings of a model definition.
     *
     * @param mixed $settings The settings, keyed by request parameter name.
     * @return string[] Error messages.
     */
    private static function validate_settings(mixed $settings): array {
        if ($settings === []) {
            return [];
        }
        if (!is_array($settings) || array_is_list($settings)) {
            return [get_string('models:error:settings', 'aiprovider_genericopenai')];
        }
        $errors = [];
        foreach ($settings as $key => $setting) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', (string) $key) || in_array($key, self::RESERVED_SETTINGS, true)) {
                $errors[] = get_string('models:error:settingname', 'aiprovider_genericopenai', $key);
                continue;
            }
            if (!is_array($setting) || !array_key_exists($setting['type'] ?? null, configured_model::SETTING_TYPES)) {
                $errors[] = get_string('models:error:settingtype', 'aiprovider_genericopenai', $key);
                continue;
            }
            if ($setting['type'] === 'select') {
                $options = $setting['options'] ?? null;
                if (
                    !is_array($options) || !$options || !array_is_list($options)
                        || array_filter($options, fn($option) => !is_string($option) || $option === '')
                ) {
                    $errors[] = get_string('models:error:settingoptions', 'aiprovider_genericopenai', $key);
                }
            }
            foreach (['label', 'help'] as $field) {
                if (isset($setting[$field]) && !self::is_text($setting[$field])) {
                    $errors[] = get_string('models:error:settingtext', 'aiprovider_genericopenai', $key);
                }
            }
        }
        return $errors;
    }

    /**
     * Whether a value is plain text or a reference to an existing language string.
     *
     * @param mixed $value The value.
     * @return bool
     */
    private static function is_text(mixed $value): bool {
        if (is_string($value)) {
            return true;
        }
        return is_array($value)
            && is_string($value['identifier'] ?? null)
            && is_string($value['component'] ?? null)
            && get_string_manager()->string_exists($value['identifier'], $value['component']);
    }

    /**
     * Whether a value maps each of the given keys to a string matching a pattern.
     *
     * @param mixed $map The value.
     * @param string[] $keys The keys that must be present.
     * @param string $pattern The pattern each value must match.
     * @return bool
     */
    private static function is_map(mixed $map, array $keys, string $pattern): bool {
        if (!is_array($map)) {
            return false;
        }
        foreach ($keys as $key) {
            if (!is_string($map[$key] ?? null) || !preg_match($pattern, $map[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the bundled model definitions.
     *
     * @return array
     */
    public static function get_bundled_definitions(): array {
        if (self::$bundled === null) {
            $json = file_get_contents(__DIR__ . '/../db/models.json');
            self::$bundled = json_decode($json, true, 512, JSON_THROW_ON_ERROR)['models'];
        }
        return self::$bundled;
    }

    /**
     * Get the model definitions for a models setting, combined with the bundled models.
     *
     * @param string $json The JSON of the models setting.
     * @return array Model definitions, in the order they are offered.
     */
    private static function get_definitions(string $json): array {
        $bundled = self::get_bundled_definitions();
        if ($json === '') {
            return $bundled;
        }
        if (!self::is_valid_json($json)) {
            // The setting is checked when the instance is saved, so this only happens if the config was changed directly.
            debugging('aiprovider_genericopenai: the models setting is not valid and has been ignored.', DEBUG_DEVELOPER);
            return $bundled;
        }

        $data = json_decode($json, true);
        if (array_is_list($data)) {
            $data = ['models' => $data];
        }
        $own = $data['models'];
        if (($data['mode'] ?? 'extend') === 'replace') {
            return $own;
        }

        // A default set by the instance replaces the bundled default of the same type.
        $owndefaulttypes = array_column(array_filter($own, fn($model) => !empty($model['default'])), 'type');
        $definitions = [];
        foreach ($bundled as $definition) {
            if (in_array($definition['type'], $owndefaulttypes, true)) {
                unset($definition['default']);
            }
            $definitions[$definition['name']] = $definition;
        }
        foreach ($own as $definition) {
            $definitions[$definition['name']] = $definition;
        }
        return array_values($definitions);
    }
}
