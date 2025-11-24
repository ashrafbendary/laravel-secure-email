<?php

namespace AshrafBendary\SecureEmail\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AshrafBendary\SecureEmail\Models\SesNotification;

class SesDeliveryReceived
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
     * Get the delivered email address.
     */
    public function getEmail(): string
    {
        return $this->notification->email;
    }
}
