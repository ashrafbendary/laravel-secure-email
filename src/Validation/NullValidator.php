<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

/**
 * Default driver: no third-party lookup. Only the local SES bounce and
 * complaint history is used.
 */
final class NullValidator implements EmailValidator
{
    public function validate(string $email): ValidationResult
    {
        return ValidationResult::skipped();
    }
}
