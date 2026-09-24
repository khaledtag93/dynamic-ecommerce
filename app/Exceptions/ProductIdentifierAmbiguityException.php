<?php

namespace App\Exceptions;

use RuntimeException;

class ProductIdentifierAmbiguityException extends RuntimeException
{
    public function __construct(public readonly string $identifier)
    {
        parent::__construct(__('Barcode :barcode matches multiple catalog records.', [
            'barcode' => $identifier,
        ]));
    }
}
