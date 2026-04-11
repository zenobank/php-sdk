# ZenoBank PHP SDK

PHP SDK for the ZenoBank payment API. Accept crypto payments with checkout sessions and verify webhooks.

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
use ZenoBank\Sdk\ZenoBankClient;

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

### Get a checkout

```php
$checkout = $client->checkouts->get('ch_0gJfH4a9B2Eg1jpES');
```

### Verify webhooks

```php
use ZenoBank\Sdk\Exceptions\WebhookVerificationError;
use ZenoBank\Sdk\Types\Generated\CheckoutStatus;
use ZenoBank\Sdk\Types\WebhookEvent;

$payload = file_get_contents('php://input');
$headers = getallheaders();
$secret = 'whsec_...';

try {
    $client->webhooks->verify($payload, $secret, $headers);
    $event = WebhookEvent::from_array(json_decode($payload, true));

    if ($event->data->status === CheckoutStatus::COMPLETED) {
        // Payment received
    }
} catch (WebhookVerificationError $e) {
    // Invalid signature or missing headers
    http_response_code(400);
}
```

### Error handling

API errors throw `ZenoBankError` with the HTTP status code and response body:

```php
use ZenoBank\Sdk\Exceptions\ZenoBankError;

try {
    $checkout = $client->checkouts->create([...]);
} catch (ZenoBankError $e) {
    echo $e->status; // HTTP status code
    echo $e->body;   // Response body
}
```
