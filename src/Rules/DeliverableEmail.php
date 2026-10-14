<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Rules;

use AshrafBendary\SecureEmail\Validation\DeliverabilityChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Form validation rule backed by DeliverabilityChecker.
 *
 *     'email' => ['required', 'email:rfc,strict', new DeliverableEmail],
 */
final class DeliverableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $result = app(DeliverabilityChecker::class)->check($value);

        if (! $result->passes) {
            $fail('secure-email::validation.'.$result->reason)->translate();
        }
    }
}
