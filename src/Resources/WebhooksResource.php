<?php

declare(strict_types=1);

namespace ZenoBank\Sdk\Resources;

use ZenoBank\Sdk\Exceptions\MissingWebhookHeadersError;
use ZenoBank\Sdk\Exceptions\WebhookVerificationError;

class WebhooksResource
{
    private const HEADER_KEYS = ['id', 'timestamp', 'signature'];
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    /**
     * Verify a webhook signature.
     *
     * @param string $raw_body The raw request body
     * @param string $secret Your webhook signing secret
     * @param array<string, string|string[]|null> $headers All request headers
     *
     * @throws MissingWebhookHeadersError
     * @throws WebhookVerificationError
     */
    public function verify(string $raw_body, string $secret, array $headers): void
    {
        $extracted = $this->extract_headers($headers);
        $this->verify_signature($secret, $raw_body, $extracted);
    }

    /**
     * @param array<string, string> $headers Extracted headers (id, timestamp, signature)
     *
     * @throws WebhookVerificationError
     */
    private function verify_signature(string $secret, string $raw_body, array $headers): void
    {
        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, 6);
        }
        $secret_bytes = base64_decode($secret, true);

        if ($secret_bytes === false) {
            throw new WebhookVerificationError('Invalid webhook secret');
        }

        $msg_id = $headers['id'];
        $timestamp = $headers['timestamp'];
        $signature = $headers['signature'];

        // Timestamp tolerance check
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > self::TIMESTAMP_TOLERANCE) {
            throw new WebhookVerificationError('Webhook timestamp is too old or too new');
        }

        // Compute expected signature
        $to_sign = "{$msg_id}.{$timestamp}.{$raw_body}";
        $hash = hash_hmac('sha256', $to_sign, $secret_bytes, true);
        $expected = 'v1,' . base64_encode($hash);

        // Signatures can be space-separated (multiple versions)
        $provided_signatures = explode(' ', $signature);
        foreach ($provided_signatures as $sig) {
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
    private function extract_headers(array $headers): array
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
                $missing[] = "svix-{$key}";
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
