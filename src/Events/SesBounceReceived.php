<?php

namespace AshrafBendary\SecureEmail\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AshrafBendary\SecureEmail\Models\SesNotification;

class SesBounceReceived
{
    use Dispatchable, SerializesModels;

    /**
     * The SES notification instance.
     */
    public SesNotification $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(SesNotification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Get the notification data.
     */
    public function getNotification(): SesNotification
    {
        return $this->notification;
    }

    /**
     * Check if this is a permanent bounce.
     */
    public function isPermanent(): bool
    {
        return $this->notification->isPermanentBounce();
    }

    /**
     * Check if this is a transient bounce.
     */
    public function isTransient(): bool
    {
        return $this->notification->isTransientBounce();
    }

    /**
     * Get the bounced email address.
     */
    public function getEmail(): string
    {
        return $this->notification->email;
    }
}
