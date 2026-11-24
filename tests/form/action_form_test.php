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
     * @return action_generate_text_form
     */
    private function submit_form(array $data): action_generate_text_form {
        action_generate_text_form::mock_submit($data + [
            'action' => generate_text::class,
            'provider' => 'aiprovider_genericopenai',
            'providerid' => 0,
            'systeminstruction' => 'Be brief.',
        ]);
        return new action_generate_text_form(customdata: [
            'actionname' => 'generate_text',
            'action' => generate_text::class,
            'providername' => 'aiprovider_genericopenai',
        ]);
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

        $form = $this->submit_form(['modeltemplate' => 'gpt-6-luna', 'custommodel' => '']);

        $this->assertEquals('gpt-6-luna', $form->get_data()->model);
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
                'models' => json_encode(['mode' => 'replace', 'models' => [
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
}
