# Generic OpenAI compatible API provider – User guide

Component: `aiprovider_genericopenai`

## Overview

This plugin is a Moodle AI provider. It connects the Moodle AI subsystem to any service that speaks the
OpenAI REST API. That includes OpenAI itself, and also self-hosted or third party servers that offer an
OpenAI compatible API, such as Ollama, vLLM, LM Studio or LiteLLM.

Unlike the core OpenAI provider, the base URL is configurable, so requests can go to a server on your own
network instead of `api.openai.com`.

### Supported AI actions

| Action         | Default path          | Notes                                 |
|----------------|-----------------------|---------------------------------------|
| Generate text  | `/chat/completions`   | Uses a system instruction             |
| Summarise text | `/chat/completions`   | Uses a system instruction             |
| Explain text   | `/chat/completions`   | Uses a system instruction             |
| Generate image | `/images/generations` | Images are watermarked and saved as draft files |

AI placements (for example the course assistance or the TinyMCE AI tools) use these actions. Other plugins,
such as the AI Text question type, can call them too.

## Requirements

- Moodle 5.2 or later (`$plugin->requires = 2026041000`).
- An API endpoint that implements the OpenAI chat completions API (and the images API, if you want to
  generate images).
- An API key for that service.
- The Moodle server must be able to reach the endpoint. If the endpoint is on a local or private network,
  check that its host and port are not blocked by **Site administration > General > Security > HTTP security**
  (`curlsecurityblockedhosts` and `curlsecurityallowedport`).

## Installation

1. Copy the plugin to `ai/provider/genericopenai` in your Moodle code (under `public/` if your site uses
   the `public` directory layout).
2. Go to **Site administration > Notifications** and complete the upgrade.

## Creating a provider instance

1. Go to **Site administration > General > AI > AI providers**.
2. Click **Create a new provider instance** and choose **Generic OpenAI compatible API provider**.
3. Fill in the settings below and save.
4. Enable the instance with the toggle in the provider list.

You can create more than one instance. For example, one instance can point at OpenAI and another at a local
Ollama server.

### Provider settings

| Setting | Required | Description |
|---------|----------|-------------|
| Name | Yes | A name for this instance, shown in the provider list. |
| API endpoint | Yes | The base URL of the API, without the action path. Defaults to `https://api.openai.com/v1`. A trailing slash is removed. |
| API key | Yes | Sent as `Authorization: Bearer <key>`. If your server does not check keys (for example a local Ollama), enter any value. |
| Organization ID | No | Sent in the `OpenAI-Organization` header. Leave empty if your service does not use it. |
| Allow plugins to override the system instruction | No | Off by default. See [System instruction override](#system-instruction-override). |
| Models | No | JSON list of models to offer in the action settings. Empty means the bundled OpenAI models. See [Model lists](#model-lists). |
| Load models from file | No | Puts the content of a JSON file into **Models**. The file is not stored. |

The instance counts as configured only when both the API endpoint and the API key are set.

#### Example endpoints

| Service    | API endpoint                   |
|------------|--------------------------------|
| OpenAI     | `https://api.openai.com/v1`    |
| Ollama     | `http://localhost:11434/v1`    |
| LM Studio  | `http://localhost:1234/v1`     |
| vLLM       | `http://localhost:8000/v1`     |

Use the host name the Moodle server can reach. `localhost` means the Moodle server itself.

## Action settings

After the instance is created, open its settings and use the **Settings** link next to each action.

### Text actions (generate, summarise, explain)

| Setting | Description |
|---------|-------------|
| AI model | Pick a listed model or **Custom**. The bundled list is GPT-6.2 Astra, GPT-6.2 Sol, GPT-6.1 Terra and GPT-6 Luna (default). Models are taken off the list once OpenAI announces a shutdown date. An instance can change it with its [model list](#model-lists). |
| Custom model name | Shown when **Custom** is chosen. Enter the model name exactly as the service expects it, for example `llama3.1:8b` or `meta-llama/Llama-3.1-8B-Instruct`. |
| API endpoint | Optional. A full URL for this action only. If empty, `/chat/completions` is added to the provider API endpoint. |
| System instruction | Sent to the model as a `system` message before the user prompt. If empty, no system message is sent. Changing it is not recommended unless needed. Plugins can replace it if the instance allows it (see [System instruction override](#system-instruction-override)). |

#### Model settings

Listed models show extra settings that are sent with each request. Leave a field empty to use the
service default.

| Setting | Description |
|---------|-------------|
| `max_completion_tokens` | The most tokens the model may generate. |

A [model list](#model-lists) can add other settings, such as `top_p`, `temperature` or `reasoning_effort`.

#### Extra parameters (custom models)

With **Custom** chosen, the **Extra parameters** field takes a JSON object. Each key is added to the request
body as is. For example:

```json
{
    "temperature": 0.5,
    "max_completion_tokens": 100
}
```

The form rejects text that is not valid JSON. Only use parameters your service supports, as some servers
reject unknown keys.

### Generate image action

| Setting | Description |
|---------|-------------|
| AI model | Pick a listed model or **Custom**. The bundled list is GPT Image 2.6 Sunburst (default) and GPT Image 2.6 Flare. An instance can change it with its [model list](#model-lists). |
| Custom model name | Model name for a custom image model. |
| API endpoint | Optional. A full URL for this action only. If empty, `/images/generations` is added to the provider API endpoint. |
| Extra parameters | JSON, as for text actions (custom models only). |

The aspect ratio and quality chosen by the user are mapped to values the model accepts:

| Option           | GPT Image 2.6 / custom |
|------------------|------------------------|
| Square           | `1024x1024`            |
| Landscape        | `1536x1024`            |
| Portrait         | `1024x1536`            |
| Standard quality | `medium`               |
| HD quality       | `high`                 |

A [model list](#model-lists) can set other values for its image models.

The plugin accepts an image returned either as base64 (`b64_json`) or as a URL. A URL is downloaded through
Moodle's HTTP client, so the HTTP security settings apply. Every image gets the AI watermark and is saved to
the user's draft file area.

## Model lists

The models in the **AI model** dropdowns are defined in JSON. The plugin bundles the OpenAI models in
`db/models.json`. Each provider instance can add to that list, or replace it, with its **Models** setting.
This is useful for servers such as Ollama or vLLM, whose models are not in the bundled list.

Type the JSON into **Models**, or choose a file with **Load models from file**. The JSON is checked when the
instance is saved, and any problems are shown on the field.

```json
{
    "mode": "replace",
    "models": [
        {
            "name": "llama3.1:8b",
            "displayname": "Llama 3.1 8B",
            "type": "text",
            "default": true,
            "settings": {
                "max_completion_tokens": {"type": "int", "label": "Maximum tokens"},
                "temperature": {"type": "float", "label": "Temperature", "help": "From 0 to 2."},
                "reasoning_effort": {"type": "select", "options": ["low", "medium", "high"]}
            }
        },
        {
            "name": "flux-schnell",
            "type": "image",
            "outputformat": "png",
            "sizes": {"square": "1024x1024", "landscape": "1536x1024", "portrait": "1024x1536"},
            "quality": {"standard": "medium", "hd": "high"}
        }
    ]
}
```

| Key | Description |
|-----|-------------|
| `mode` | `extend` (the default) adds to the bundled models, and a model with the name of a bundled model replaces it. `replace` offers only these models. A plain list of models, without `mode`, means `extend`. |
| `name` | Required. The model name exactly as the server expects it. `custom` is reserved. |
| `type` | Required. `text` or `image`. |
| `displayname` | The name shown in the dropdown. Defaults to `name`. |
| `default` | `true` to make this the default for its type. Without one, the bundled default is used (`extend`) or the first model of the type (`replace`). |
| `settings` | Fields shown under **Settings** when the model is chosen. Each key is sent as a request parameter. `type` is `int`, `float`, `text` or `select` (with `options`). `label` and `help` are optional text. Names the plugin sets itself, such as `model` or `messages`, are not allowed. |
| `sizes` | Image models only, required. The `WIDTHxHEIGHT` size sent for the square, landscape and portrait aspect ratios. |
| `quality` | Image models only, required. The API value sent for standard and hd quality. |
| `outputformat` | Image models only. Sent as `output_format`. Leave out if the server does not accept it. |
| `responseformat` | Image models only. Sent as `response_format`, for example `b64_json`. Leave out if the server does not accept it. |

A saved action keeps its model if the model is later removed from the list. The model is then shown as
**Custom**. **Test connection and get models** lists the model names the server has, which helps when writing the list.

## Testing the connection

Once an instance is saved, a **Test connection and get models** button appears on the provider instance settings page and on
each action settings page. It needs the `moodle/site:config` capability.

The test asks the server for its list of models (`GET {API endpoint}/models`). That confirms in one request that:

- the API endpoint is set and points at an OpenAI compatible API,
- the endpoint is not blocked by the Moodle HTTP security settings,
- the Moodle server can reach it,
- the API key is accepted.

No text or images are generated, so no tokens are used. The test uses the **saved** settings, so save any
changes first.

The result shows:

- whether the connection worked, with the error from the server if not,
- the models the server lists,
- a warning for each enabled action whose model is not in that list (for example a misspelt Ollama model name),
- a warning for each per-action API endpoint that the HTTP security settings block.

On an action settings page, a successful test also adds the server's models to the **AI model** dropdown, so
you can pick one and save. The page is not reloaded, so other changes on it are kept. The list is remembered
for an hour, or until the API endpoint or key is changed. Models in the bundled list or the instance
[model list](#model-lists) keep their display name and settings, and stay in the dropdown for their type. The
server does not say which of its other models are for text and which for images, so those are added to the
text dropdown only. To offer one of them for images, add it to the instance model list as an image model.

The models request always uses the provider API endpoint. A per-action endpoint is only checked against the
HTTP security settings.

## Enabling placements

A provider does nothing on its own. Go to **Site administration > General > AI > AI placements**, enable the
placements you want, and in each placement turn on the actions this provider should handle.

## System instruction override

Some plugins need their own system instruction. For example, the AI Text question type sends grading
instructions. By default the instruction in the action settings is always used.

If **Allow plugins to override the system instruction** is on for an instance, a plugin can replace the
instruction, or send none, for its own requests. Other requests are not affected.

### For developers

Set the override on the action object before passing it to the AI manager:

```php
$action = new \core_ai\aiactions\generate_text(
    contextid: $contextid,
    userid: $USER->id,
    prompttext: $prompt,
);
if (class_exists(\aiprovider_genericopenai\system_instruction::class)) {
    \aiprovider_genericopenai\system_instruction::set_for_action($action, 'My instruction');
}
$response = \core\di::get(\core_ai\manager::class)->process_action($action);
```

- An empty string sends no system message.
- The override is ignored unless the instance allows it.
- The override is held against the action object, so it ends when that object is destroyed.
- Only the text actions (generate, summarise, explain) use system instructions.

## Troubleshooting

Start with **Test connection and get models** (see [Testing the connection](#testing-the-connection)). It reports most of
the problems below.

| Problem | What to check |
|---------|---------------|
| Provider shows as not configured | Both API endpoint and API key must be set. |
| Connection errors | The Moodle server can reach the endpoint. The host and port are not blocked in HTTP security settings. |
| HTTP 404 | The API endpoint usually needs to end in `/v1`. Check any per-action endpoint is a full URL. |
| HTTP 401 / 403 | The API key, and the Organization ID if used. |
| Model not found | For custom models, the name must match the service exactly (for Ollama, see `ollama list`). |
| Unknown parameter errors | Remove settings or extra parameters the service does not support. |
| "The API response did not contain any image data." | The image endpoint returned no `b64_json` or `url`. Check the model supports image generation. |

Error messages from the service are shown to the user. OpenAI, Ollama, vLLM and FastAPI error formats are
recognised. With developer debugging on, the HTTP status and endpoint are also shown in a debugging message.

## Privacy

The plugin stores no personal data. The prompt text, model name and, for images, the number of images and
response format are sent to the configured endpoint. The `user` field holds a SHA-256 hash of the site
identifier and user ID, not the Moodle user ID itself. What the external service keeps depends on that
service. Review its data policy before use, particularly for services outside your organisation.
