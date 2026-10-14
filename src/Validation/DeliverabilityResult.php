<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

/**
 * Combined verdict from the local SES history and the configured provider.
 *
 * `reason` is null when the address passes, otherwise one of:
 * permanent_bounce, bounces, complaints, undeliverable.
 */
final readonly class DeliverabilityResult
{
    public function __construct(
        public bool $passes,
        public ?string $reason = null,
        public ?string $providerStatus = null,
    ) {}

    public static function accepted(?string $providerStatus = null): self
    {
        return new self(true, null, $providerStatus);
    }

    public static function rejected(string $reason, ?string $providerStatus = null): self
    {
        return new self(false, $reason, $providerStatus);
    }
}
