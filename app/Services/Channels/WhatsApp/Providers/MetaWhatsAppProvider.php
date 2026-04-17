<?php

namespace App\Services\Channels\WhatsApp\Providers;

use App\Services\Channels\WhatsApp\Support\WhatsAppPhoneNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppProvider
{
    public function __construct(
        protected WhatsAppPhoneNormalizer $phoneNormalizer,
    ) {
    }

    public function send(array $payload, array $config = []): array
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://graph.facebook.com'), '/');
        $version = (string) ($config['graph_version'] ?? 'v23.0');
        $phoneNumberId = (string) ($config['phone_number_id'] ?? '');
        $accessToken = (string) ($config['access_token'] ?? '');
        $timeout = (int) ($config['timeout'] ?? 20);
        $connectTimeout = (int) ($config['connect_timeout'] ?? 10);
        $retryTimes = max(0, (int) ($config['retry_times'] ?? 2));
        $retrySleepMs = max(100, (int) ($config['retry_sleep_ms'] ?? 400));

        if ($accessToken === '') {
            return $this->failureResponse(422, [
                'message' => 'Missing WhatsApp Meta access token',
            ], $payload);
        }

        if ($phoneNumberId === '') {
            return $this->failureResponse(422, [
                'message' => 'Missing WhatsApp Meta phone number ID',
            ], $payload);
        }

        if (isset($payload['to'])) {
            $payload['to'] = $this->phoneNormalizer->normalize((string) $payload['to']) ?? (string) $payload['to'];
        }

        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        try {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleepMs, function ($exception, $request) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (method_exists($exception, 'response') && $exception->response) {
                        $status = $exception->response->status();

                        return in_array($status, [429, 500, 502, 503, 504], true);
                    }

                    return false;
                }, throw: false)
                ->withOptions($this->httpOptions())
                ->post($url, $payload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json() ?: ['raw' => $response->body()],
                'message_id' => Arr::get($response->json(), 'messages.0.id'),
                'request_payload' => $payload,
                'request_url' => $url,
            ];
        } catch (ConnectionException $e) {
            return $this->failureResponse(500, [
                'message' => $e->getMessage(),
            ], $payload, $url);
        } catch (\Throwable $e) {
            return $this->failureResponse(500, [
                'message' => $e->getMessage(),
            ], $payload, $url);
        }
    }

    public function sendTemplate(
        string $to,
        string $templateName,
        string $languageCode,
        array $parameters = [],
        array $config = []
    ): array {
        $normalizedTo = $this->phoneNormalizer->normalize($to) ?? $to;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $normalizedTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode ?: 'en_US',
                ],
            ],
        ];

        $components = $this->normalizeTemplateComponents($parameters);

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->send($payload, $config);
    }

    protected function normalizeTemplateComponents(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        $first = $parameters[array_key_first($parameters)] ?? null;

        if (is_array($first) && array_key_exists('type', $first)) {
            return $parameters;
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(static function ($value): array {
                return [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            }, $parameters),
        ]];
    }

    protected function httpOptions(): array
    {
        if (app()->environment('local')) {
            return ['verify' => false];
        }

        return [];
    }

    protected function failureResponse(int $status, array $body, array $payload, ?string $url = null): array
    {
        return [
            'success' => false,
            'status' => $status,
            'body' => $body,
            'message_id' => null,
            'request_payload' => $payload,
            'request_url' => $url,
        ];
    }
}