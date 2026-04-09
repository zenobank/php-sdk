# Zenobank PHP SDK

PHP SDK for the Zenobank payment API. Accept crypto payments with checkout sessions and verify webhooks.

## Requirements

- PHP 8.1+
- cURL extension
- JSON extension

## Installation

```bash
composer require zenobank/sdk
```

## Usage

### Initialize the client

```php
use Zenobank\Sdk\ZenoBankClient;

$client = new ZenoBankClient('your-api-key');
```

### Create a checkout

```php
$checkout = $client->checkouts->create([
    'order_id' => 'order-123',
    'price_amount' => '10.00',
    'price_currency' => 'USD',
    'success_redirect_url' => 'https://example.com/success',
]);

// Redirect the customer
header('Location: ' . $checkout->checkout_url);
```

The response object has the following properties:

| Property | Type | Description |
|---|---|---|
| `id` | `string` | Checkout ID |
| `order_id` | `string` | Your order identifier |
| `price_amount` | `string` | Price amount |
| `price_currency` | `string` | Currency code (e.g. `USD`) |
| `status` | `CheckoutStatus` | `OPEN`, `COMPLETED`, `PARTIALLY_PAID`, `EXPIRED`, or `CANCELLED` |
| `checkout_url` | `string` | URL to redirect the customer to |
| `created_at` | `string` | ISO 8601 timestamp |
| `expires_at` | `?string` | ISO 8601 timestamp or null |
| `success_redirect_url` | `?string` | Redirect URL after payment |

### Get a checkout

```php
$checkout = $client->checkouts->get('ch_0gJfH4a9B2Eg1jpES');

if ($checkout->status === CheckoutStatus::COMPLETED) {
    // Payment received
}
```

### Verify webhooks

```php
$payload = file_get_contents('php://input');
$headers = getallheaders();
$secret = 'whsec_...';

$is_valid = $client->webhooks->is_valid($payload, $secret, $headers);

if ($is_valid) {
    $event = json_decode($payload, true);
    // Handle the event
}
```

### Error handling

API errors throw `ZenobankError` with the HTTP status code and response body:

```php
use Zenobank\Sdk\Exceptions\ZenobankError;

try {
    $checkout = $client->checkouts->create([...]);
} catch (ZenobankError $e) {
    echo $e->status; // HTTP status code
    echo $e->body;   // Response body
}
```
