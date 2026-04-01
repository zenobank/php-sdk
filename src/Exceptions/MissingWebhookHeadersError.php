<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Exceptions;

class MissingWebhookHeadersError extends WebhookVerificationError
{
    /**
     * @param string[] $missingHeaders
     */
    public function __construct(public readonly array $missingHeaders)
    {
        parent::__construct('Missing required webhook headers: ' . implode(', ', $missingHeaders));
    }
}
