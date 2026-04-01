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
use Zenobank\Sdk\ZenobankClient;

$client = new ZenobankClient('your-api-key');
```

### Create a checkout

```php
$checkout = $client->checkouts->create([
    'orderId' => 'order-123',
    'priceAmount' => '10.00',
    'priceCurrency' => 'USD',
    'successRedirectUrl' => 'https://example.com/success',
]);

// Redirect the customer
header('Location: ' . $checkout->checkoutUrl);
```

The response object has the following properties:

| Property | Type | Description |
|---|---|---|
| `id` | `string` | Checkout ID |
| `orderId` | `string` | Your order identifier |
| `priceAmount` | `string` | Price amount |
| `priceCurrency` | `string` | Currency code (e.g. `USD`) |
| `status` | `CheckoutStatus` | `OPEN`, `COMPLETED`, `EXPIRED`, or `CANCELLED` |
| `checkoutUrl` | `string` | URL to redirect the customer to |
| `createdAt` | `string` | ISO 8601 timestamp |
| `expiresAt` | `?string` | ISO 8601 timestamp or null |
| `successRedirectUrl` | `?string` | Redirect URL after payment |

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

$isValid = $client->webhooks->isValid($payload, $secret, $headers);

if ($isValid) {
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

### Custom base URL

```php
$client = new ZenobankClient('your-api-key', [
    'baseUrl' => 'https://api.staging.zenobank.io',
]);
```
