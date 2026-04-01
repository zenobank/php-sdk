<?php

declare(strict_types=1);

namespace Zenobank\Sdk\Types;

class WebhookEvent
{
    public function __construct(
        public readonly WebhookEventType $type,
        public readonly CheckoutResponseDto $data,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from_array(array $data): self
    {
        return new self(
            type: WebhookEventType::from($data['type']),
            data: CheckoutResponseDto::from_array($data['data']),
        );
    }
}
