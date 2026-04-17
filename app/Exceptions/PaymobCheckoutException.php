<?php

namespace App\Exceptions;

use RuntimeException;

class PaymobCheckoutException extends RuntimeException
{
    public function __construct(
        string $message,
        protected array $diagnostics = [],
        protected ?string $userMessage = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function userMessage(): string
    {
        return $this->userMessage ?: __('Unable to start online payment right now. Please try again shortly.');
    }
}
