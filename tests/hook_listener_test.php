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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the fields added to the provider instance form.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_genericopenai\hook_listener
 */
#[CoversClass(hook_listener::class)]
final class hook_listener_test extends \advanced_testcase {
    /**
     * Submit the provider instance form.
     *
     * @param string $models The models setting.
     * @return \core_ai\form\ai_provider_form
     */
    private function submit_provider_form(string $models): \core_ai\form\ai_provider_form {
        $data = [
            'aiprovider' => 'aiprovider_genericopenai',
            'name' => 'Test',
            'apiendpoint' => 'http://localhost:11434/v1',
            'apikey' => 'x',
            'models' => $models,
        ];
        \core_ai\form\ai_provider_form::mock_submit($data);
        // The models rule message is worked out from the request, as on a real submission.
        $_POST += $data;
        return new \core_ai\form\ai_provider_form(customdata: ['providerconfigs' => ['aiprovider' => 'aiprovider_genericopenai']]);
    }

    /**
     * Test valid models are saved with the instance config.
     */
    public function test_models_valid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $models = '[{"name": "llama3.1:8b", "type": "text"}]';

        $data = $this->submit_provider_form($models)->get_data();

        $this->assertNotNull($data);
        $this->assertEquals($models, $data->models);
    }

    /**
     * Test invalid models stop the form being saved, with the reason shown on the field.
     */
    public function test_models_invalid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = $this->submit_provider_form('[{"name": "llama<3>", "type": "audio"}]');

        $this->assertNull($form->get_data());
        $error = (fn() => $this->_form->getElementError('models'))->call($form);
        $this->assertStringContainsString(s(get_string('models:error:type', 'aiprovider_genericopenai')), $error);
        // The model name from the submitted JSON is escaped.
        $this->assertStringContainsString('llama&lt;3&gt;', $error);
    }

    /**
     * Test the models section starts collapsed when empty, and the core rate limit settings stay in a section of their own.
     */
    public function test_models_section(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $collapsed = fn(\core_ai\form\ai_provider_form $form): array => (fn() => [
            $this->_form->_collapsibleElements,
            array_keys($this->_form->_elementIndex),
        ])->call($form);

        [$states, $order] = $collapsed(new \core_ai\form\ai_provider_form(
            customdata: ['providerconfigs' => ['aiprovider' => 'aiprovider_genericopenai']],
        ));
        $this->assertTrue($states['modelsheader']);
        $this->assertFalse($states['ratelimitheader']);
        $this->assertLessThan(array_search('modelsheader', $order), array_search('connectiontest', $order) ?: -1);
        $this->assertGreaterThan(array_search('modelsheader', $order), array_search('models', $order));
        $this->assertGreaterThan(array_search('ratelimitheader', $order), array_search('enableglobalratelimit', $order));

        // A submitted value opens the section.
        [$states] = $collapsed($this->submit_provider_form('[{"name": "llama3.1:8b", "type": "text"}]'));
        $this->assertFalse($states['modelsheader']);
    }
}
