<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Exceptions;

class MissingWebhookHeadersError extends WebhookVerificationError
{
    /** @var string[] */
    public readonly array $missing_headers;

    /**
     * @param string[] $missing_headers
     */
    public function __construct(array $missing_headers)
    {
        parent::__construct('Missing required webhook headers: ' . implode(', ', $missing_headers));
        $this->missing_headers = $missing_headers;
    }
}
