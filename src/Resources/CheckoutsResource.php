<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Resources;

use Zenobank\Sdk\Exceptions\ZenobankError;
use Zenobank\Sdk\Types\CheckoutResponseDto;

class CheckoutsResource
{
    private string $base_url;
    private string $api_key;

    public function __construct(string $base_url, string $api_key)
    {
        $this->base_url = $base_url;
        $this->api_key = $api_key;
    }

    /**
     * @param array{order_id: string, price_amount: string, price_currency: string, success_redirect_url?: string|null} $params
     */
    public function create(array $params): CheckoutResponseDto
    {
        $body = [
            'orderId' => $params['order_id'],
            'priceAmount' => $params['price_amount'],
            'priceCurrency' => $params['price_currency'],
            'successRedirectUrl' => $params['success_redirect_url'] ?? null,
        ];

        $data = $this->request('POST', '/api/v1/checkouts', $body);

        return CheckoutResponseDto::from_array($data);
    }

    public function get(string $checkout_id): CheckoutResponseDto
    {
        $data = $this->request('GET', '/api/v1/checkouts/' . urlencode($checkout_id));

        return CheckoutResponseDto::from_array($data);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     *
     * @throws ZenobankError
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->base_url . $path;

        $headers = [
            'X-API-Key: ' . $this->api_key,
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
        $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ZenobankError('Request failed: ' . $error, 0);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = $status_code . ' ' . ($decoded['message'] ?? $response);
            throw new ZenobankError($message, $status_code, $decoded ?? $response);
        }

        if (!is_array($decoded)) {
            throw new ZenobankError('Invalid JSON response', $status_code, $response);
        }

        return $decoded;
    }
}
