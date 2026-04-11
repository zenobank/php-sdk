<?php

declare(strict_types=1);

namespace ZenoBank\Sdk\Resources;

use ZenoBank\Sdk\Exceptions\ZenoBankError;
use ZenoBank\Sdk\Types\Generated\CheckoutResponseDto;
use ZenoBank\Sdk\Types\Generated\CreateCheckoutDto;

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
        $dto = CreateCheckoutDto::from_array(self::snake_keys_to_camel($params));
        $data = $this->request('POST', '/api/v1/checkouts', $dto->to_array());

        return CheckoutResponseDto::from_array($data);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function snake_keys_to_camel(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $camel = (string) preg_replace_callback(
                '/_([a-z])/',
                static fn (array $m): string => strtoupper($m[1]),
                $key,
            );
            $out[$camel] = $value;
        }
        return $out;
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
     * @throws ZenoBankError
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
            throw new ZenoBankError('Request failed: ' . $error, 0);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = $status_code . ' ' . ($decoded['message'] ?? $response);
            throw new ZenoBankError($message, $status_code, $decoded ?? $response);
        }

        if (!is_array($decoded)) {
            throw new ZenoBankError('Invalid JSON response', $status_code, $response);
        }

        return $decoded;
    }
}
