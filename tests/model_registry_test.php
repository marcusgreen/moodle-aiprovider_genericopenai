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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the model registry and configured models.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\model_registry
 * @covers     \aiprovider_genericopenai\aimodel\configured_model
 */
#[CoversClass(model_registry::class)]
#[CoversClass(configured_model::class)]
final class model_registry_test extends \advanced_testcase {
    /** @var array An image model definition. */
    private const IMAGE_MODEL = [
        'name' => 'flux-schnell',
        'type' => 'image',
        'outputformat' => 'webp',
        'sizes' => ['square' => '512x512', 'landscape' => '768x512', 'portrait' => '512x768'],
        'quality' => ['standard' => 'low', 'hd' => 'high'],
    ];

    /**
     * Create a provider instance with a models setting.
     *
     * @param mixed $models The models setting, encoded as JSON unless it is a string.
     * @return provider
     */
    private function create_test_provider(mixed $models): provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: provider::class,
            name: 'dummy',
            config: [
                'apiendpoint' => 'http://localhost:11434/v1',
                'apikey' => 'x',
                model_registry::CONFIG_KEY => is_string($models) ? $models : json_encode($models),
            ],
        );
    }

    /**
     * Test the bundled models are valid and keep the models offered before they were moved to db/models.json.
     */
    public function test_bundled_models(): void {
        $this->assertEquals([], model_registry::validate_definitions(model_registry::get_bundled_definitions()));

        $this->assertEquals(
            ['gpt-6.2-astra', 'gpt-6.2-sol', 'gpt-6.1-terra', 'gpt-6-luna'],
            array_keys(model_registry::get_models_of_type(openai_base::MODEL_TYPE_TEXT)),
        );
        $this->assertEquals(
            ['gpt-image-2.6-sunburst', 'gpt-image-2.6-flare'],
            array_keys(model_registry::get_models_of_type(openai_base::MODEL_TYPE_IMAGE)),
        );
        $this->assertEquals('gpt-6-luna', model_registry::get_default_model(openai_base::MODEL_TYPE_TEXT));
        $this->assertEquals('gpt-image-2.6-sunburst', model_registry::get_default_model(openai_base::MODEL_TYPE_IMAGE));

        $luna = model_registry::get_model('gpt-6-luna');
        $this->assertEquals('GPT-6 Luna', $luna->get_model_display_name());
        $this->assertEquals(['max_completion_tokens'], array_keys($luna->get_model_settings()));
        $this->assertEquals(PARAM_INT, $luna->get_model_settings()['max_completion_tokens']['type']);

        $sunburst = model_registry::get_model('gpt-image-2.6-sunburst');
        $this->assertEquals('1536x1024', $sunburst->calculate_size('landscape'));
        $this->assertEquals('high', $sunburst->calculate_quality('hd'));
        $this->assertNull($sunburst->response_format());
        $this->assertEquals('png', $sunburst->get_output_format());

        // Models with a shutdown date are no longer offered.
        $this->assertNull(model_registry::get_model('gpt-4o'));
        $this->assertNull(model_registry::get_model('o1'));
        $this->assertNull(model_registry::get_model('dall-e-3'));
        $this->assertNull(model_registry::get_model('custom'));
    }

    /**
     * Test extend mode adds models, replaces a bundled model of the same name and moves the default of a type.
     */
    public function test_extend(): void {
        $this->resetAfterTest();
        $provider = $this->create_test_provider([
            'models' => [
                ['name' => 'llama3.1:8b', 'type' => 'text', 'default' => true],
                ['name' => 'gpt-6-luna', 'displayname' => 'GPT-6 Luna (proxy)', 'type' => 'text'],
                self::IMAGE_MODEL,
            ],
        ]);

        $text = model_registry::get_models_of_type(openai_base::MODEL_TYPE_TEXT, $provider);
        $this->assertEquals(
            ['gpt-6.2-astra', 'gpt-6.2-sol', 'gpt-6.1-terra', 'gpt-6-luna', 'llama3.1:8b'],
            array_keys($text),
        );
        $this->assertEquals('GPT-6 Luna (proxy)', $text['gpt-6-luna']->get_model_display_name());
        $this->assertFalse($text['gpt-6-luna']->has_model_settings());
        $this->assertEquals('llama3.1:8b', model_registry::get_default_model(openai_base::MODEL_TYPE_TEXT, $provider));

        // No image model is marked default by the instance, so the bundled default stays.
        $this->assertEquals('gpt-image-2.6-sunburst', model_registry::get_default_model(openai_base::MODEL_TYPE_IMAGE, $provider));
        $flux = model_registry::get_model('flux-schnell', $provider);
        $this->assertEquals('768x512', $flux->calculate_size('landscape'));
        $this->assertEquals('low', $flux->calculate_quality('standard'));
        $this->assertEquals('webp', $flux->get_output_format());

        // Other instances and the bundled list are not affected.
        $this->assertNull(model_registry::get_model('llama3.1:8b'));
    }

    /**
     * Test a plain list is the same as extend mode.
     */
    public function test_plain_list(): void {
        $this->resetAfterTest();
        $provider = $this->create_test_provider([['name' => 'mistral', 'type' => 'text']]);

        $this->assertEquals(
            ['gpt-6.2-astra', 'gpt-6.2-sol', 'gpt-6.1-terra', 'gpt-6-luna', 'mistral'],
            array_keys(model_registry::get_models_of_type(openai_base::MODEL_TYPE_TEXT, $provider)),
        );
    }

    /**
     * Test replace mode offers only the instance models.
     */
    public function test_replace(): void {
        $this->resetAfterTest();
        $provider = $this->create_test_provider([
            'mode' => 'replace',
            'models' => [
                ['name' => 'qwen2.5', 'type' => 'text'],
                ['name' => 'llama3.1:8b', 'type' => 'text'],
            ],
        ]);

        $this->assertEquals(['qwen2.5', 'llama3.1:8b'], array_keys(model_registry::get_models($provider)));
        // With no model marked default, the first model of the type is the default.
        $this->assertEquals('qwen2.5', model_registry::get_default_model(openai_base::MODEL_TYPE_TEXT, $provider));
        $this->assertNull(model_registry::get_default_model(openai_base::MODEL_TYPE_IMAGE, $provider));
    }

    /**
     * Test an invalid stored setting is ignored.
     */
    public function test_invalid_setting_ignored(): void {
        $this->resetAfterTest();
        $provider = $this->create_test_provider('{"models": [{"name": "x"}]}');

        $models = model_registry::get_models($provider);

        $this->assertDebuggingCalled();
        $this->assertEquals(array_column(model_registry::get_bundled_definitions(), 'name'), array_keys($models));
    }

    /**
     * Test settings of each type.
     */
    public function test_settings(): void {
        $model = new configured_model([
            'name' => 'm',
            'type' => 'text',
            'settings' => [
                'temperature' => ['type' => 'float', 'label' => 'Temperature <b>', 'help' => 'From 0 to 2.'],
                'reasoning_effort' => ['type' => 'select', 'options' => ['low', 'high']],
                'seed' => ['type' => 'int'],
                'stop' => ['type' => 'text'],
            ],
        ]);

        $settings = $model->get_model_settings();
        $this->assertEquals(PARAM_FLOAT, $settings['temperature']['type']);
        $this->assertEquals('text', $settings['temperature']['elementtype']);
        $this->assertEquals('select', $settings['reasoning_effort']['elementtype']);
        $this->assertEquals(['low', 'high'], $settings['reasoning_effort']['options']);
        $this->assertEquals('seed', $settings['seed']['label']);
        $this->assertEquals(PARAM_TEXT, $settings['stop']['type']);

        $mform = new \MoodleQuickForm('test', 'post', '');
        $model->add_model_settings($mform);
        $this->assertEquals('Temperature &lt;b&gt;', $mform->getElement('temperature')->getLabel());
        $this->assertTrue($mform->elementExists('temperature_description'));
        $select = $mform->getElement('reasoning_effort');
        $this->assertEquals(['', 'low', 'high'], array_map(fn($option) => $option['attr']['value'], $select->_options));
    }

    /**
     * Test an unknown aspect ratio or quality is rejected.
     */
    public function test_image_mapping_unknown(): void {
        $model = new configured_model(self::IMAGE_MODEL);

        $this->expectException(\coding_exception::class);
        $model->calculate_size('panorama');
    }

    /**
     * Test valid models settings.
     */
    public function test_validate_json_valid(): void {
        $this->assertEquals([], model_registry::validate_json(''));
        $this->assertEquals([], model_registry::validate_json('  '));
        $this->assertEquals([], model_registry::validate_json('[]'));
        $this->assertEquals([], model_registry::validate_json(json_encode(['mode' => 'replace', 'models' => [
            ['name' => 'meta-llama/Llama-3.1-8B-Instruct', 'type' => 'text', 'default' => false, 'settings' => [
                'reasoning_effort' => ['type' => 'select', 'options' => ['low'], 'label' => [
                    'identifier' => 'settings_top_p',
                    'component' => 'aiprovider_genericopenai',
                ]],
            ]],
            self::IMAGE_MODEL,
        ]])));
        $this->assertTrue(model_registry::is_valid_json('[{"name": "a", "type": "text"}]'));
    }

    /**
     * Test invalid models settings.
     *
     * @dataProvider invalid_json_provider
     * @param string $json The models setting.
     * @param string $identifier The expected error string identifier.
     * @param mixed $a The expected error string parameter.
     */
    #[DataProvider('invalid_json_provider')]
    public function test_validate_json_invalid(string $json, string $identifier, mixed $a = null): void {
        $errors = model_registry::validate_json($json);

        $this->assertNotEmpty($errors);
        $this->assertFalse(model_registry::is_valid_json($json));
        $expected = get_string($identifier, 'aiprovider_genericopenai', $a);
        $this->assertTrue(
            (bool) array_filter($errors, fn($error) => str_contains($error, $expected)),
            "'{$expected}' not in: " . implode(' | ', $errors),
        );
    }

    /**
     * Data provider for test_validate_json_invalid.
     *
     * @return array
     */
    public static function invalid_json_provider(): array {
        $model = fn(array $definition): string => json_encode(['models' => [$definition]]);
        $image = self::IMAGE_MODEL;
        return [
            'Not JSON' => ['{"models": [', 'models:error:json', 'Syntax error'],
            'Scalar' => ['"gpt-4o"', 'models:error:structure'],
            'No models list' => ['{"mode": "extend"}', 'models:error:structure'],
            'Models not a list' => ['{"models": {"name": "a"}}', 'models:error:structure'],
            'Bad mode' => ['{"mode": "merge", "models": []}', 'models:error:mode'],
            'Model not an object' => ['["gpt-4o"]', 'models:error:notobject'],
            'No name' => [$model(['type' => 'text']), 'models:error:name'],
            'Name with spaces' => [$model(['name' => ' a ', 'type' => 'text']), 'models:error:name'],
            'Reserved name' => [$model(['name' => 'custom', 'type' => 'text']), 'models:error:name'],
            'Duplicate' => ['[{"name": "a", "type": "text"}, {"name": "a", "type": "image"}]', 'models:error:duplicate'],
            'Bad type' => [$model(['name' => 'a', 'type' => 'audio']), 'models:error:type'],
            'Bad display name' => [$model(['name' => 'a', 'type' => 'text', 'displayname' => 1]), 'models:error:text',
                'displayname'],
            'Bad default' => [$model(['name' => 'a', 'type' => 'text', 'default' => 'yes']), 'models:error:default'],
            'Settings a list' => [$model(['name' => 'a', 'type' => 'text', 'settings' => [1]]), 'models:error:settings'],
            'Reserved setting' => [$model(['name' => 'a', 'type' => 'text', 'settings' => ['messages' => ['type' => 'text']]]),
                'models:error:settingname', 'messages'],
            'Bad setting name' => [$model(['name' => 'a', 'type' => 'text', 'settings' => ['Top-P' => ['type' => 'float']]]),
                'models:error:settingname', 'Top-P'],
            'Bad setting type' => [$model(['name' => 'a', 'type' => 'text', 'settings' => ['seed' => ['type' => 'bool']]]),
                'models:error:settingtype', 'seed'],
            'Select without options' => [
                $model(['name' => 'a', 'type' => 'text', 'settings' => ['effort' => ['type' => 'select']]]),
                'models:error:settingoptions', 'effort'],
            'Unknown language string' => [$model(['name' => 'a', 'type' => 'text', 'settings' => [
                'seed' => ['type' => 'int', 'label' => ['identifier' => 'nosuchstring', 'component' => 'aiprovider_genericopenai']],
            ]]), 'models:error:settingtext', 'seed'],
            'Image without sizes' => [$model(['sizes' => null] + $image), 'models:error:sizes'],
            'Image bad size' => [$model(['sizes' => ['square' => 'big'] + $image['sizes']] + $image), 'models:error:sizes'],
            'Image without quality' => [$model(['quality' => ['standard' => 'low']] + $image), 'models:error:quality'],
            'Image bad output format' => [$model(['outputformat' => false] + $image), 'models:error:text', 'outputformat'],
        ];
    }

    /**
     * Test the error names the model.
     */
    public function test_validate_json_names_model(): void {
        $errors = model_registry::validate_json('[{"name": "a", "type": "text"}, {"name": "b", "type": "audio"}]');

        $this->assertEquals([
            get_string('models:error:model', 'aiprovider_genericopenai', [
                'index' => 2,
                'name' => 'b',
                'error' => get_string('models:error:type', 'aiprovider_genericopenai'),
            ]),
        ], $errors);
    }

    /**
     * Test models listed by the server are offered as text models, and only until the endpoint or key changes.
     */
    public function test_server_models(): void {
        $this->resetAfterTest();
        $provider = $this->create_test_provider([['name' => 'flux-schnell'] + self::IMAGE_MODEL]);
        $this->assertEquals([], model_registry::get_server_models($provider));

        model_registry::set_server_models($provider, ['gpt-6-luna', 'flux-schnell', 'llama3.1:8b', 'whisper-1']);

        $this->assertEquals(
            ['gpt-6-luna', 'flux-schnell', 'llama3.1:8b', 'whisper-1'],
            model_registry::get_server_models($provider),
        );
        $text = model_registry::get_model_options(openai_base::MODEL_TYPE_TEXT, $provider);
        // Listed models keep their place and display name, and models of another known type are left out.
        $this->assertEquals(
            ['gpt-6.2-astra', 'gpt-6.2-sol', 'gpt-6.1-terra', 'gpt-6-luna', 'llama3.1:8b', 'whisper-1'],
            array_keys($text),
        );
        $this->assertEquals('GPT-6 Luna', $text['gpt-6-luna']);
        $this->assertEquals('llama3.1:8b', $text['llama3.1:8b']);
        // Models of unknown type are not offered for images.
        $this->assertEquals(
            ['gpt-image-2.6-sunburst', 'gpt-image-2.6-flare', 'flux-schnell'],
            array_keys(model_registry::get_model_options(openai_base::MODEL_TYPE_IMAGE, $provider)),
        );

        // A list from before the API key was changed is not used.
        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->update_provider_instance($provider, ['apikey' => 'changed'] + $provider->config);
        $this->assertEquals([], model_registry::get_server_models($provider));

        // Nothing is remembered for an unsaved instance.
        $unsaved = new provider(true, 'unsaved', json_encode(['apiendpoint' => 'http://x/v1', 'apikey' => 'x']));
        model_registry::set_server_models($unsaved, ['a']);
        $this->assertEquals([], model_registry::get_server_models($unsaved));
    }
}
