<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

enum CheckoutStatus: string
{
    case OPEN = 'OPEN';
    case COMPLETED = 'COMPLETED';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
