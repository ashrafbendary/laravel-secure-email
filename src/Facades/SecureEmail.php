<?php
// src/Facades/SecureEmail.php

namespace AshrafBendary\SecureEmail\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array send($mailable, string $to, ?string $name = null)
 * @method static array canSendEmail(string $email)
 * @method static array validateWithZeroBounce(string $email)
 * @method static array validateBatch(array $emails)
 * @method static int|null getCredits()
 * @method static array getStats()
 * @method static void blacklist(string $email, string $reason = 'manual')
 * @method static bool whitelist(string $email)
 *
 * @see \AshrafBendary\SecureEmail\Services\SecureEmailService
 */
class SecureEmail extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'secure-email';
    }
}