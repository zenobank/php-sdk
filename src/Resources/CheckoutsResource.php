<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Resources;

use Zenobank\Sdk\Exceptions\ZenobankError;
use Zenobank\Sdk\Types\CheckoutResponseDto;

class CheckoutsResource
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    /**
     * @param array{orderId: string, priceAmount: string, priceCurrency: string, successRedirectUrl?: string|null} $params
     */
    public function create(array $params): CheckoutResponseDto
    {
        $data = $this->request('POST', '/api/v1/checkouts', $params);

        return CheckoutResponseDto::fromArray($data);
    }

    public function get(string $checkoutId): CheckoutResponseDto
    {
        $data = $this->request('GET', '/api/v1/checkouts/' . urlencode($checkoutId));

        return CheckoutResponseDto::fromArray($data);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     *
     * @throws ZenobankError
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ZenobankError('Request failed: ' . $error, 0);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $statusCode . ' ' . ($decoded['message'] ?? $response);
            throw new ZenobankError($message, $statusCode, $decoded ?? $response);
        }

        if (!is_array($decoded)) {
            throw new ZenobankError('Invalid JSON response', $statusCode, $response);
        }

        return $decoded;
    }
}
