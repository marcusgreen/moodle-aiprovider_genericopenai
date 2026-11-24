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

/**
 * Strings for component aiprovider_genericopenai, language 'en'.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action:explain_text:endpoint'] = 'API endpoint';
$string['action:explain_text:endpoint_help'] = 'Optional. The full URL for this action. If empty, /chat/completions is appended to the provider API endpoint.';
$string['action:explain_text:model'] = 'AI model';
$string['action:explain_text:model_help'] = 'The model used to explain the provided text.';
$string['action:explain_text:systeminstruction'] = 'System instruction';
$string['action:explain_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.

If <strong>Allow plugins to override the system instruction</strong> is enabled in the provider instance settings, plugins such as the AI Text question type can replace this instruction, or send none, for their own requests.';
$string['action:generate_image:endpoint'] = 'API endpoint';
$string['action:generate_image:endpoint_help'] = 'Optional. The full URL for this action. If empty, /images/generations is appended to the provider API endpoint.';
$string['action:generate_image:model'] = 'AI model';
$string['action:generate_image:model_help'] = 'The model used to generate images from user prompts.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:endpoint_help'] = 'Optional. The full URL for this action. If empty, /chat/completions is appended to the provider API endpoint.';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_help'] = 'The model used to generate the text response.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.

If <strong>Allow plugins to override the system instruction</strong> is enabled in the provider instance settings, plugins such as the AI Text question type can replace this instruction, or send none, for their own requests.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:endpoint_help'] = 'Optional. The full URL for this action. If empty, /chat/completions is appended to the provider API endpoint.';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_help'] = 'The model used to summarise the provided text.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.

If <strong>Allow plugins to override the system instruction</strong> is enabled in the provider instance settings, plugins such as the AI Text question type can replace this instruction, or send none, for their own requests.';
$string['allowinstructionoverride'] = 'Allow plugins to override the system instruction';
$string['allowinstructionoverride_help'] = 'If enabled, plugins such as the AI Text question type can replace the system instruction set for an action with their own, or send none. The instruction is replaced only for that plugin\'s requests.';
$string['apiendpoint'] = 'API endpoint';
$string['apiendpoint_help'] = 'The base URL of the OpenAI compatible API, for example https://api.openai.com/v1 or http://localhost:11434/v1. Actions append their own path, such as /chat/completions, unless the action has its own endpoint set. A full URL ending in /chat/completions, /images/generations or /models is reduced to its base.';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'The API key for the OpenAI compatible service. It is sent as a Bearer token in the Authorization header.';
$string['cachedef_servermodels'] = 'Model names listed by the API server of each provider instance';
$string['connectiontest'] = 'Test connection and get models';
$string['connectiontest:actionblocked'] = 'The {$a->action} endpoint {$a->endpoint} is blocked by the HTTP security settings (Site administration > General > Security > HTTP security).';
$string['connectiontest:blocked'] = 'The API endpoint {$a} is blocked by the HTTP security settings. Check the blocked hosts and allowed ports under Site administration > General > Security > HTTP security.';
$string['connectiontest:httperror'] = 'The server at {$a->url} returned HTTP {$a->status}: {$a->error}';
$string['connectiontest:invalidprovider'] = 'The AI provider instance was not found.';
$string['connectiontest:invalidresponse'] = 'The server at {$a} responded, but not with a list of models. Check that the API endpoint is the base URL of an OpenAI compatible API, usually ending in /v1.';
$string['connectiontest:modelnotlisted'] = 'The model {$a->model} used by {$a->action} is not listed by the server.';
$string['connectiontest:models'] = 'Models available';
$string['connectiontest:noconnection'] = 'Could not connect to {$a->url}: {$a->error}';
$string['connectiontest:notconfigured'] = 'Set the API endpoint and API key, and save, before testing the connection.';
$string['connectiontest:success'] = 'Connection successful. The server lists {$a} models.';
$string['connectiontest:unauthorised'] = 'The server at {$a->url} rejected the API key (HTTP {$a->status}): {$a->error}';
$string['connectiontest_desc'] = 'Requests the list of models from the API endpoint, using the saved settings. Save any changes before testing. No tokens are used.';
$string['custom_model_name'] = 'Custom model name';
$string['custom_model_name_help'] = 'The model name exactly as the API server expects it, for example llama3.1:8b (Ollama) or meta-llama/Llama-3.1-8B-Instruct (vLLM). Use Test connection to see the models the server lists.';
$string['error:noimagedata'] = 'The API response did not contain any image data.';
$string['extraparams'] = 'Extra parameters';
$string['extraparams_help'] = 'Extra parameters can be configured here. We support JSON format. For example:
<pre>
{
    "temperature": 0.5,
    "max_completion_tokens": 100
}
</pre>';
$string['extraparams_placeholder'] = 'Example: {"temperature": 0.5, "max_completion_tokens": 100}';
$string['invalidjson'] = 'Invalid JSON string';
$string['json:download'] = 'Download JSON';
$string['json:format'] = 'Format JSON';
$string['json:invalid'] = 'Not valid JSON: {$a}';
$string['json:linecolumn'] = '(line {$a->line}, column {$a->column})';
$string['models'] = 'Models';
$string['models:error:default'] = 'default must be true or false.';
$string['models:error:duplicate'] = 'another model in the list has the same name.';
$string['models:error:json'] = 'The models are not valid JSON: {$a}';
$string['models:error:model'] = 'Model {$a->index} ({$a->name}): {$a->error}';
$string['models:error:name'] = 'name must be the model name used by the server, without spaces at the start or end, and not "custom".';
$string['models:error:notobject'] = 'must be a JSON object.';
$string['models:error:quality'] = 'quality must give an API value for standard and hd.';
$string['models:error:settingname'] = 'the setting name {$a} is not allowed. Use lower case letters, digits and underscores, and not a name set by the plugin, such as model or messages.';
$string['models:error:settingoptions'] = 'the setting {$a} needs a list of options.';
$string['models:error:settings'] = 'settings must be a JSON object, keyed by setting name.';
$string['models:error:settingtext'] = 'the setting {$a} has a label or help that is neither text nor an existing language string.';
$string['models:error:settingtype'] = 'the setting {$a} needs a type of int, float, text or select.';
$string['models:error:sizes'] = 'sizes must give a WIDTHxHEIGHT size, such as 1024x1024, for square, landscape and portrait.';
$string['models:error:structure'] = 'The models must be a JSON object with a "models" list, or a list of models.';
$string['models:error:text'] = '{$a} must be text.';
$string['models:error:type'] = 'type must be "text" or "image".';
$string['models_help'] = 'Optional. A JSON list of models to describe in the action settings. The AI model list always offers the models the API server lists, so this is only needed to give a model a display name, settings or default, or to offer an image model, as the server does not say which models make images.

<pre>
{
    "models": [
        {"name": "llama3.1:8b", "displayname": "Llama 3.1 8B", "type": "text", "default": true,
            "settings": {"max_completion_tokens": {"type": "int", "label": "Maximum tokens"}}}
    ]
}
</pre>

Each model needs a name (as the server expects it) and a type of "text" or "image". Optional: displayname, default (true or false) and settings. A setting has a type of int, float, text or select (with a list of options), and an optional label and help. Image models also need sizes for square, landscape and portrait, and quality values for standard and hd, and can set responseformat and outputformat.';
$string['models_placeholder'] = 'Example: [{"name": "llama3.1:8b", "type": "text", "default": true}]';
$string['modelsfile'] = 'Load models from file';
$string['modelsfile:invalidjson'] = 'The file is not valid JSON: {$a}';
$string['modelsfile:toobig'] = 'The file is larger than {$a} KB.';
$string['modelsfile_help'] = 'Choose a JSON file to put its content in the Models setting. The file is not stored. Save the form to use the models.';
$string['modelsheader'] = 'Model descriptions';
$string['nomodels:image'] = 'No image models are listed. Most API servers do not say which of their models make images, and this one has not listed any as image models. Choose Custom and enter the model name, or describe the image models in the Models setting of the <a href="{$a}">provider instance</a>.';
$string['nomodels:text'] = 'No models are listed, as the API server has not returned a list of models. Use Test connection and get models on the <a href="{$a}">provider instance</a> settings to see why, or choose Custom and enter the model name.';
$string['orgid'] = 'Organization ID';
$string['orgid_help'] = 'Optional. If the service supports it, this is sent in the OpenAI-Organization header. Leave empty if not required.';
$string['pluginname'] = 'Generic OpenAI API provider';
$string['privacy:metadata'] = 'The Generic OpenAI API provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_genericopenai:externalpurpose'] = 'This information is sent to the configured OpenAI compatible API endpoint in order for a response to be generated. The settings of the external service may change how it stores and retains this data. No user data is explicitly sent to the service or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_genericopenai:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_genericopenai:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_genericopenai:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_genericopenai:responseformat'] = 'The format of the response. When generating images.';
$string['ratelimitheader'] = 'Rate limits';
$string['settings'] = 'Settings';
$string['settings_frequency_penalty'] = 'frequency_penalty';
$string['settings_frequency_penalty_help'] = 'The frequency penalty adjusts how often words are repeated. The higher the penalty, the less repetitions in the generated text.';
$string['settings_help'] = 'Adjust the settings below to customise how requests are sent to the API endpoint.';
$string['settings_max_completion_tokens'] = 'max_completion_tokens';
$string['settings_max_completion_tokens_help'] = 'The maximum number of tokens used in the generated text.';
$string['settings_presence_penalty'] = 'presence_penalty';
$string['settings_presence_penalty_help'] = 'The presence penalty encourages the model to use new words by increasing the likelihood of choosing words it hasn\'t used before. A higher value makes the generated text more diverse, while a lower value allows more repetition.';
$string['settings_top_p'] = 'top_p';
$string['settings_top_p_help'] = 'top_p (nucleus sampling) determines how many possible words to consider. A high value (e.g. 0.9) means the model looks at more words, which makes the generated text more diverse.';
$string['task:refreshservermodels'] = 'Refresh the model lists of Generic OpenAI API provider instances';
