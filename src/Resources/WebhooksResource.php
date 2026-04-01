<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Resources;

use Zenobank\Sdk\Exceptions\MissingWebhookHeadersError;
use Zenobank\Sdk\Exceptions\WebhookVerificationError;
use Zenobank\Sdk\Types\WebhookEvent;

class WebhooksResource
{
    private const HEADER_KEYS = ['id', 'timestamp', 'signature'];
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    /**
     * Verify a webhook signature.
     *
     * @param string $rawBody The raw request body
     * @param string $secret Your webhook signing secret
     * @param array<string, string|string[]|null> $headers All request headers
     */
    public function isValid(string $rawBody, string $secret, array $headers): bool
    {
        try {
            $extracted = $this->extractHeaders($headers);
            $this->verifySignature($secret, $rawBody, $extracted);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, string> $headers Extracted headers (id, timestamp, signature)
     *
     * @throws WebhookVerificationError
     */
    private function verifySignature(string $secret, string $rawBody, array $headers): void
    {
        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, 6);
        }
        $secretBytes = base64_decode($secret, true);

        if ($secretBytes === false) {
            throw new WebhookVerificationError('Invalid webhook secret');
        }

        $msgId = $headers['id'];
        $timestamp = $headers['timestamp'];
        $signature = $headers['signature'];

        // Timestamp tolerance check
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > self::TIMESTAMP_TOLERANCE) {
            throw new WebhookVerificationError('Webhook timestamp is too old or too new');
        }

        // Compute expected signature
        $toSign = "{$msgId}.{$timestamp}.{$rawBody}";
        $hash = hash_hmac('sha256', $toSign, $secretBytes, true);
        $expected = 'v1,' . base64_encode($hash);

        // Signatures can be space-separated (multiple versions)
        $providedSignatures = explode(' ', $signature);
        foreach ($providedSignatures as $sig) {
            if (hash_equals($expected, trim($sig))) {
                return;
            }
        }

        throw new WebhookVerificationError('Invalid webhook signature');
    }

    /**
     * @param array<string, string|string[]|null> $headers
     * @return array<string, string>
     *
     * @throws MissingWebhookHeadersError
     */
    private function extractHeaders(array $headers): array
    {
        // Normalize header keys to lowercase
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        $out = [];
        $missing = [];

        foreach (self::HEADER_KEYS as $key) {
            $value = $normalized["zeno-{$key}"] ?? $normalized["svix-{$key}"] ?? null;

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if ($value === null || $value === '') {
                $missing[] = "zeno-{$key} / svix-{$key}";
            } else {
                $out[$key] = (string) $value;
            }
        }

        if (!empty($missing)) {
            throw new MissingWebhookHeadersError($missing);
        }

        return $out;
    }
}
