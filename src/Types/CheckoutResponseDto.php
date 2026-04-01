<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

class CheckoutResponseDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $order_id,
        public readonly string $price_currency,
        public readonly string $price_amount,
        public readonly CheckoutStatus $status,
        public readonly ?string $expires_at,
        public readonly string $checkout_url,
        public readonly string $created_at,
        public readonly ?string $success_redirect_url,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from_array(array $data): self
    {
        return new self(
            id: $data['id'],
            order_id: $data['orderId'],
            price_currency: $data['priceCurrency'],
            price_amount: $data['priceAmount'],
            status: CheckoutStatus::from($data['status']),
            expires_at: $data['expiresAt'] ?? null,
            checkout_url: $data['checkoutUrl'],
            created_at: $data['createdAt'],
            success_redirect_url: $data['successRedirectUrl'] ?? null,
        );
    }
}
