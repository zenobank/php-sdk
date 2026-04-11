<?php

declare(strict_types=1);

namespace ZenoBank\Sdk;

use ZenoBank\Sdk\Resources\CheckoutsResource;
use ZenoBank\Sdk\Resources\WebhooksResource;

class ZenoBankClient
{
    private const DEFAULT_BASE_URL = 'https://api.zenobank.io';

    public readonly CheckoutsResource $checkouts;
    public readonly WebhooksResource $webhooks;

    /**
     * @param string $apiKey Your ZenoBank API key
     * @param array{baseUrl?: string} $opts
     */
    public function __construct(string $apiKey, array $opts = [])
    {
        $baseUrl = rtrim($opts['baseUrl'] ?? self::DEFAULT_BASE_URL, '/');

        $this->checkouts = new CheckoutsResource($baseUrl, $apiKey);
        $this->webhooks = new WebhooksResource();
    }
}
