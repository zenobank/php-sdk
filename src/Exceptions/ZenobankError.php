<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Exceptions;

class ZenobankError extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly mixed $body = null,
    ) {
        parent::__construct($message, $status);
    }
}
