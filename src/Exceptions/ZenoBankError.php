<?php

declare(strict_types=1);

namespace ZenoBank\Sdk\Exceptions;

class ZenoBankError extends \Exception
{
    public readonly int $status;

    public readonly mixed $body;

    public function __construct(string $message, int $status, mixed $body = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->body = $body;
    }
}
