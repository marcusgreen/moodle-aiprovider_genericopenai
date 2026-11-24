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

use core\files\curl_security_helper;
use core\http_client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;

/**
 * Checks that a provider instance can reach its OpenAI compatible API.
 *
 * The check requests the list of models from {apiendpoint}/models. A successful response confirms the
 * endpoint is reachable, not blocked by the Moodle HTTP security settings and accepts the API key.
 * No tokens are used.
 *
 * @package    aiprovider_genericopenai
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class connection_tester {
    /** @var int Timeout in seconds for the models request. */
    private const TIMEOUT = 20;

    /**
     * Constructor.
     *
     * @param provider $provider The provider instance to test.
     */
    public function __construct(
        /** @var provider The provider instance to test. */
        private readonly provider $provider,
    ) {
    }

    /**
     * Get the URL used to list the models.
     *
     * @return string
     */
    public function get_models_url(): string {
        return $this->provider->get_api_endpoint() . '/models';
    }

    /**
     * Run the connection test.
     *
     * Messages are plain text. Model names and messages from the remote server are not escaped.
     *
     * @return array{success: bool, message: string, httpstatus: int, models: string[], warnings: string[]}
     */
    public function test(): array {
        $result = [
            'success' => false,
            'message' => '',
            'httpstatus' => 0,
            'models' => [],
            'warnings' => [],
        ];

        if (!$this->provider->is_provider_configured()) {
            $result['message'] = get_string('connectiontest:notconfigured', 'aiprovider_genericopenai');
            return $result;
        }

        $url = $this->get_models_url();
        $helper = new curl_security_helper();
        if ($helper->is_enabled()) {
            if ($helper->url_is_blocked($url)) {
                $result['message'] = get_string('connectiontest:blocked', 'aiprovider_genericopenai', $url);
                return $result;
            }
            // Endpoints set on actions are not used for the models request, but are checked for blocking.
            foreach ($this->get_action_endpoints() as $actionname => $endpoint) {
                if ($helper->url_is_blocked($endpoint)) {
                    $result['warnings'][] = get_string(
                        'connectiontest:actionblocked',
                        'aiprovider_genericopenai',
                        ['action' => $actionname, 'endpoint' => $endpoint],
                    );
                }
            }
        }

        $request = $this->provider->add_authentication_headers(new Request('GET', $url));
        $client = \core\di::get(http_client::class);
        try {
            $response = $client->send($request, [
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => self::TIMEOUT,
            ]);
        } catch (GuzzleException $e) {
            $result['message'] = get_string(
                'connectiontest:noconnection',
                'aiprovider_genericopenai',
                ['url' => $url, 'error' => $e->getMessage()],
            );
            return $result;
        }

        $status = $response->getStatusCode();
        $result['httpstatus'] = $status;
        $body = (string) $response->getBody();
        if ($status !== 200) {
            $detail = helper::get_error_detail($body);
            if ($detail === '') {
                $detail = $response->getReasonPhrase();
            }
            $identifier = ($status === 401 || $status === 403) ? 'connectiontest:unauthorised' : 'connectiontest:httperror';
            $result['message'] = get_string(
                $identifier,
                'aiprovider_genericopenai',
                ['url' => $url, 'status' => $status, 'error' => $detail],
            );
            return $result;
        }

        $models = $this->parse_models($body);
        if ($models === null) {
            $result['message'] = get_string('connectiontest:invalidresponse', 'aiprovider_genericopenai', $url);
            return $result;
        }

        $result['success'] = true;
        $result['models'] = $models;
        model_registry::set_server_models($this->provider, $models);
        $result['message'] = get_string('connectiontest:success', 'aiprovider_genericopenai', count($models));

        // A server that lists no models gives nothing to compare against.
        if ($models) {
            foreach ($this->get_action_models() as $actionname => $model) {
                if (!in_array($model, $models, true)) {
                    $result['warnings'][] = get_string(
                        'connectiontest:modelnotlisted',
                        'aiprovider_genericopenai',
                        ['action' => $actionname, 'model' => $model],
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Get the model names from a models response body.
     *
     * The OpenAI format is {"data": [{"id": "gpt-4o", ...}, ...]}. Ollama, vLLM, LM Studio and LiteLLM use the same format.
     *
     * @param string $body The response body.
     * @return string[]|null The sorted model names, or null if the body is not a models list.
     */
    private function parse_models(string $body): ?array {
        $bodyobj = json_decode($body);
        if (!is_object($bodyobj) || !isset($bodyobj->data) || !is_array($bodyobj->data)) {
            return null;
        }
        $models = [];
        foreach ($bodyobj->data as $model) {
            if (is_object($model) && isset($model->id) && is_string($model->id)) {
                $models[] = $model->id;
            }
        }
        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);
        return $models;
    }

    /**
     * Get the endpoints set on enabled actions, keyed by action name.
     *
     * @return string[]
     */
    private function get_action_endpoints(): array {
        $endpoints = [];
        foreach ($this->get_enabled_action_settings() as $actionname => $settings) {
            $endpoint = trim($settings['endpoint'] ?? '');
            if ($endpoint !== '') {
                $endpoints[$actionname] = $endpoint;
            }
        }
        return $endpoints;
    }

    /**
     * Get the models set on enabled actions, keyed by action name.
     *
     * @return string[]
     */
    private function get_action_models(): array {
        $models = [];
        foreach ($this->get_enabled_action_settings() as $actionname => $settings) {
            $model = trim($settings['model'] ?? '');
            if ($model !== '') {
                $models[$actionname] = $model;
            }
        }
        return $models;
    }

    /**
     * Get the settings of the enabled actions, keyed by the localised action name.
     *
     * @return array[]
     */
    private function get_enabled_action_settings(): array {
        $settings = [];
        foreach ($this->provider->actionconfig as $actionclass => $config) {
            if (empty($config['enabled']) || !class_exists($actionclass)) {
                continue;
            }
            $settings[$actionclass::get_name()] = $config['settings'] ?? [];
        }
        return $settings;
    }
}
