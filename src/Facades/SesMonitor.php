<?php

namespace AshrafBendary\SecureEmail\Facades;

use Illuminate\Support\Facades\Facade;
use AshrafBendary\SecureEmail\Models\SesNotification;

/**
 * @method static bool hasPermanentBounce(string $email)
 * @method static int countBouncesForEmail(string $email, ?string $subject = null, int $days = 0)
 * @method static int countComplaintsForEmail(string $email, ?string $subject = null, int $days = 0)
 * @method static bool shouldBlockEmail(string $email, ?string $subject = null)
 * @method static bool isDeliverable(string $email)
 * @method static \AshrafBendary\SecureEmail\Validation\DeliverabilityResult checkDeliverability(string $email)
 * 
 * @see \AshrafBendary\SecureEmail\SesMonitorService
 */
class SesMonitor extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'secure-email';
    }
}
