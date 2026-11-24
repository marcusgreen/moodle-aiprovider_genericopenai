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

namespace aiprovider_genericopenai\form;

use core_ai\aiactions\generate_text;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the action settings form.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\form\action_form
 */
#[CoversClass(action_form::class)]
final class action_form_test extends \advanced_testcase {
    /**
     * Submit the generate text action form.
     *
     * @param array $data Submitted values.
     * @param int $providerid The provider instance id, or 0 for none.
     * @return action_generate_text_form
     */
    private function submit_form(array $data, int $providerid = 0): action_generate_text_form {
        action_generate_text_form::mock_submit($data + [
            'action' => generate_text::class,
            'provider' => 'aiprovider_genericopenai',
            'providerid' => $providerid,
            'systeminstruction' => 'Be brief.',
        ]);
        return new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providerid' => $providerid,
            'providername' => 'aiprovider_genericopenai',
        ]);
    }

    /**
     * Create a provider instance whose server has listed its models.
     *
     * @param string[] $servermodels The models the server lists.
     * @return \aiprovider_genericopenai\provider
     */
    private function create_listed_provider(array $servermodels): \aiprovider_genericopenai\provider {
        $provider = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: \aiprovider_genericopenai\provider::class,
            name: 'groq',
            config: ['apiendpoint' => 'https://api.groq.com/openai/v1', 'apikey' => 'x'],
        );
        \aiprovider_genericopenai\model_registry::set_server_models($provider, $servermodels);
        return $provider;
    }

    /**
     * Test spaces around a custom model name are removed.
     */
    public function test_custom_model_trimmed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->submit_form([
            'modeltemplate' => 'custom',
            'custommodel' => "  meta-llama/Llama-3.1-8B-Instruct \t",
            'modelextraparams' => '',
        ]);

        $this->assertEquals('meta-llama/Llama-3.1-8B-Instruct', $form->get_data()->model);
    }

    /**
     * Test a custom model name of only spaces is rejected.
     */
    public function test_custom_model_blank(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->submit_form(['modeltemplate' => 'custom', 'custommodel' => '   ']);

        $this->assertNull($form->get_data());
        $errors = $form->validation(['modeltemplate' => 'custom', 'custommodel' => '   '], []);
        $this->assertEquals(get_string('required'), $errors['custommodel']);
    }

    /**
     * Test a listed model is saved as selected.
     */
    public function test_listed_model(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $provider = $this->create_listed_provider(['qwen2.5']);

        $form = $this->submit_form(['modeltemplate' => 'qwen2.5', 'custommodel' => ''], $provider->id);

        $this->assertEquals('qwen2.5', $form->get_data()->model);
    }

    /**
     * Test the model list and default come from the models setting of the provider instance.
     */
    public function test_instance_models(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: \aiprovider_genericopenai\provider::class,
            name: 'ollama',
            config: [
                'apiendpoint' => 'http://localhost:11434/v1',
                'apikey' => 'x',
                'models' => json_encode(['models' => [
                    ['name' => 'qwen2.5', 'type' => 'text'],
                    ['name' => 'llama3.1:8b', 'type' => 'text', 'default' => true],
                ]]),
            ],
            // No model saved yet, so the form shows the default.
            actionconfig: [generate_text::class => ['enabled' => true, 'settings' => []]],
        );

        $form = new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providerid' => $provider->id,
            'providername' => 'aiprovider_genericopenai',
            'actionconfig' => ['settings' => []],
        ]);
        $html = $form->render();

        $this->assertMatchesRegularExpression('/<option value="qwen2.5"\s*>qwen2.5</', $html);
        $this->assertMatchesRegularExpression('/<option value="llama3.1:8b"\s*selected\s*>/', $html);
        $this->assertStringNotContainsString('value="gpt-4o"', $html);

        // After a connection test, the models the server lists are offered too.
        \aiprovider_genericopenai\model_registry::set_server_models($provider, ['qwen2.5', 'phi4']);
        $form = new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providerid' => $provider->id,
            'providername' => 'aiprovider_genericopenai',
            'actionconfig' => ['settings' => ['model' => 'phi4']],
        ]);
        $this->assertMatchesRegularExpression('/<option value="phi4"\s*selected\s*>phi4</', $form->render());
    }

    /**
     * Test a new action defaults to the first model the server lists, and a saved model it does not list is kept as custom.
     */
    public function test_server_listed_models(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = $this->create_listed_provider(['allam-2-7b', 'qwen/qwen3.6-27b']);
        $render = fn(string $model): string => (new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providerid' => $provider->id,
            'providername' => 'aiprovider_genericopenai',
            'actionconfig' => ['settings' => ['model' => $model]],
        ]))->render();

        $html = $render('');
        $this->assertMatchesRegularExpression('/<option value="allam-2-7b"\s*selected\s*>/', $html);
        $this->assertMatchesRegularExpression('/<option value="qwen\/qwen3.6-27b"\s*>/', $html);

        $html = $render('my-finetune');
        $this->assertMatchesRegularExpression('/<option value="custom"\s*selected\s*>/', $html);
        $this->assertStringContainsString('value="my-finetune"', $html);
    }

    /**
     * Test the default settings of an action do not save Custom as the model name when no models are known.
     */
    public function test_defaults_without_models(): void {
        $this->resetAfterTest();

        $defaults = \aiprovider_genericopenai\provider::get_action_setting_defaults(generate_text::class);

        $this->assertEquals('', $defaults['model']);
    }

    /**
     * Test a note explains an AI model list with only Custom in it.
     */
    public function test_no_models_note(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = $this->create_listed_provider(['llama3.1:8b']);
        $customdata = [
            'actionconfig' => ['settings' => []],
            'providerid' => $provider->id,
            'providername' => 'aiprovider_genericopenai',
        ];
        $url = (new \moodle_url('/ai/configure.php', ['id' => $provider->id]))->out();

        // The server lists a text model, but cannot say it makes images.
        $image = (new action_generate_image_form(customdata: $customdata + [
            'actionname' => 'generate_image',
            'action' => \core_ai\aiactions\generate_image::class,
        ]))->render();
        $this->assertStringContainsString(get_string('nomodels:image', 'aiprovider_genericopenai', $url), $image);

        $text = (new action_generate_text_form(customdata: $customdata + [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
        ]))->render();
        $this->assertStringNotContainsString(get_string('nomodels:text', 'aiprovider_genericopenai', $url), $text);
        // The connection is tested on the provider instance page, not on action pages.
        $this->assertStringNotContainsString('aiprovider_genericopenai-testconnection', $text);
        $this->assertStringNotContainsString('aiprovider_genericopenai-testconnection', $image);

        // A failed request leaves no models for text either.
        \aiprovider_genericopenai\model_registry::set_server_models($provider, []);
        $text = (new action_generate_text_form(customdata: $customdata + [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
        ]))->render();
        $this->assertStringContainsString(get_string('nomodels:text', 'aiprovider_genericopenai', $url), $text);
    }

    /**
     * Test a model the server lists can have extra parameters, which are kept when the form is shown again.
     */
    public function test_listed_model_extra_params(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = $this->create_listed_provider(['qwen2.5']);
        $params = '{"temperature": 0.2}';

        $data = $this->submit_form([
            'modeltemplate' => 'qwen2.5',
            'custommodel' => '',
            'modelextraparams' => $params,
        ], $provider->id)->get_data();

        $this->assertEquals('qwen2.5', $data->model);
        $this->assertEquals($params, $data->modelextraparams);
        $this->assertEquals(['modelextraparams' => $params], $data->modelsettings['qwen2.5']);

        // The saved model stays selected rather than being shown as Custom.
        $html = (new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providerid' => $provider->id,
            'providername' => 'aiprovider_genericopenai',
            'actionconfig' => ['settings' => ['model' => 'qwen2.5', 'modelextraparams' => $params]],
        ]))->render();
        $this->assertMatchesRegularExpression('/<option value="qwen2.5"\s*selected\s*>/', $html);
        $this->assertStringContainsString('name="modelextraparams"', $html);
    }
}
