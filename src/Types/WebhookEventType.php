<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

enum WebhookEventType: string
{
    case CHECKOUT_COMPLETED = 'checkout.completed';
    case CHECKOUT_EXPIRED = 'checkout.expired';
    case CHECKOUT_PARTIALLY_PAID = 'checkout.partially_paid';
}
