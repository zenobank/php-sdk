<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Exceptions;

class ZenobankError extends \Exception
{
    /** @var int */
    public readonly int $status;

    /** @var mixed */
    public readonly mixed $body;

    public function __construct(string $message, int $status, mixed $body = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->body = $body;
    }
}
