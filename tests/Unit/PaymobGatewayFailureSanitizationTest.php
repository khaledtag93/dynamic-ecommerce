<?php

namespace Tests\Unit;

use App\Exceptions\PaymobCheckoutException;
use App\Services\Payments\PaymobGatewayService;
use Tests\TestCase;

class PaymobGatewayFailureSanitizationTest extends TestCase
{
    public function test_failed_gateway_response_is_redacted_in_logs_and_persistable_diagnostics(): void
    {
        $gatewayResponse = [
            'error' => 'validation_failed',
            'details' => [
                'client_secret' => 'provider-secret-should-not-persist',
                'email' => 'customer-private@example.test',
                'CVV' => 'synthetic-security-code',
                'message' => 'Payment could not be processed.',
            ],
        ];

        $service = $this->gatewayWithResponse($gatewayResponse);

        try {
            $service->sendFailure();
            $this->fail('Expected the simulated unsuccessful gateway response to throw.');
        } catch (PaymobCheckoutException $exception) {
            $this->assertSame(
                '[redacted]',
                data_get($exception->diagnostics(), 'body.details.client_secret'),
                'Checkout diagnostics are persisted to payment metadata and must not contain provider secrets.'
            );
            $this->assertSame('[redacted]', data_get($exception->diagnostics(), 'body.details.email'));
            $this->assertSame('[redacted]', data_get($exception->diagnostics(), 'body.details.CVV'));
        }

        $this->assertSame('[redacted]', data_get($service->errors, '0.body.details.client_secret'));
        $this->assertSame('[redacted]', data_get($service->errors, '0.body.details.email'));
        $this->assertSame('[redacted]', data_get($service->errors, '0.body.details.CVV'));
        $this->assertSame('validation_failed', data_get($service->errors, '0.body.error'));
    }

    public function test_plaintext_gateway_error_is_not_retained_in_logs_or_diagnostics(): void
    {
        $service = $this->gatewayWithResponse(null, 'Sensitive provider value: synthetic-token-123');

        try {
            $service->sendFailure();
            $this->fail('Expected the simulated unsuccessful gateway response to throw.');
        } catch (PaymobCheckoutException $exception) {
            $this->assertSame('[non-json gateway response]', $exception->diagnostics()['body']);
        }

        $this->assertSame('[non-json gateway response]', data_get($service->errors, '0.body'));
    }

    private function gatewayWithResponse(?array $gatewayResponse, string $rawBody = '{}'): PaymobGatewayService
    {
        return new class($gatewayResponse, $rawBody) extends PaymobGatewayService {
            public array $errors = [];

            public function __construct(private ?array $gatewayResponse, private string $rawBody)
            {
                $this->baseUrl = 'https://example.test';
                $this->integrationId = 'fake-integration';
                $this->iframeId = 'fake-iframe';
                $this->currency = 'EGP';
                $this->settingsSource = [];
            }

            protected function client()
            {
                return new class($this->gatewayResponse, $this->rawBody) {
                    public function __construct(private ?array $response, private string $rawBody) {}

                    public function post(string $url, array $payload): object
                    {
                        return new class($this->response, $this->rawBody) {
                            public function __construct(private ?array $response, private string $rawBody) {}
                            public function successful(): bool { return false; }
                            public function status(): int { return 422; }
                            public function json(): ?array { return $this->response; }
                            public function body(): string { return $this->rawBody; }
                        };
                    }
                };
            }

            protected function logInfo(string $message, array $context = []): void
            {
            }

            protected function logError(string $message, array $context = []): void
            {
                $this->errors[] = $context;
            }

            public function sendFailure(): array
            {
                return $this->post('simulated-failure', ['order_id' => 7]);
            }
        };
    }
}
