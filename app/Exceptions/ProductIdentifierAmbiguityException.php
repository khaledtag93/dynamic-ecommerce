<?php

namespace App\Exceptions;

use RuntimeException;

class ProductIdentifierAmbiguityException extends RuntimeException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct(__('Identifier :identifier matches multiple catalog records.', [
            'identifier' => $identifier,
        ]));
    }
}
