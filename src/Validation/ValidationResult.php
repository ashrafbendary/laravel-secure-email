<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

/**
 * Outcome of a single provider lookup.
 *
 * `deliverable` is three-state: true (accepted), false (hard reject),
 * null (no verdict: provider skipped, unavailable, or not configured).
 */
final readonly class ValidationResult
{
    public function __construct(
        public string $status,
        public ?bool $deliverable,
        public ?string $subStatus = null,
    ) {}

    public static function accepted(string $status, ?string $subStatus = null): self
    {
        return new self($status, true, $subStatus);
    }

    public static function rejected(string $status, ?string $subStatus = null): self
    {
        return new self($status, false, $subStatus);
    }

    /** No provider configured, so no lookup was made. */
    public static function skipped(): self
    {
        return new self('skipped', null);
    }

    /** The provider could not be reached or returned an error. */
    public static function unavailable(): self
    {
        return new self('unavailable', null);
    }
}
