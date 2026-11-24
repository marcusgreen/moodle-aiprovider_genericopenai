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

namespace aiprovider_genericopenai\test;

/**
 * Trait for test cases.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {

    /**
     * Create the provider object.
     *
     * @param string $actionclass The action class to use.
     * @param array $actionconfig The action configuration to use.
     */
    public function create_provider(
        string $actionclass,
        array $actionconfig = [],
    ): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $config = [
            'apiendpoint' => 'https://api.openai.com/v1',
            'apikey' => '123',
            'enableuserratelimit' => true,
            'userratelimit' => 1,
            'enableglobalratelimit' => true,
            'globalratelimit' => 1,
            // No models are built in, so the image model used by the tests is described here.
            'models' => json_encode([[
                'name' => 'image-model',
                'type' => 'image',
                'outputformat' => 'png',
                'sizes' => ['square' => '1024x1024', 'landscape' => '1536x1024', 'portrait' => '1024x1536'],
                'quality' => ['standard' => 'medium', 'hd' => 'high'],
            ]]),
        ];
        $defaultactionconfig = [
            $actionclass => [
                'settings' => [
                    'model' => 'gpt-4o',
                    'endpoint' => "https://api.openai.com/v1/chat/completions",
                ],
            ],
        ];
        foreach ($actionconfig as $key => $value) {
            $defaultactionconfig[$actionclass]['settings'][$key] = $value;
        }
        $provider = $manager->create_provider_instance(
            classname: '\aiprovider_genericopenai\provider',
            name: 'dummy',
            config: $config,
            actionconfig: $defaultactionconfig,
        );

        return $provider;
    }
}
