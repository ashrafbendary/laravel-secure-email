<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

/**
 * A real-time mailbox validation provider (ZeroBounce, or your own).
 *
 * Implementations must never throw for provider outages: return
 * ValidationResult::unavailable() so callers can soft-fail instead of
 * blocking users while a third-party API is down.
 */
interface EmailValidator
{
    public function validate(string $email): ValidationResult;
}
