<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

enum CheckoutStatus: string
{
    case OPEN = 'OPEN';
    case COMPLETED = 'COMPLETED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
