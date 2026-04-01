<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

class CheckoutResponseDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $orderId,
        public readonly string $priceCurrency,
        public readonly string $priceAmount,
        public readonly CheckoutStatus $status,
        public readonly ?string $expiresAt,
        public readonly string $checkoutUrl,
        public readonly string $createdAt,
        public readonly ?string $successRedirectUrl,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            orderId: $data['orderId'],
            priceCurrency: $data['priceCurrency'],
            priceAmount: $data['priceAmount'],
            status: CheckoutStatus::from($data['status']),
            expiresAt: $data['expiresAt'] ?? null,
            checkoutUrl: $data['checkoutUrl'],
            createdAt: $data['createdAt'],
            successRedirectUrl: $data['successRedirectUrl'] ?? null,
        );
    }
}
