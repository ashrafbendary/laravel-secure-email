<?php

namespace AshrafBendary\SecureEmail\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AshrafBendary\SecureEmail\Models\SesNotification;

class SesComplaintReceived
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
     * Get the email address that complained.
     */
    public function getEmail(): string
    {
        return $this->notification->email;
    }

    /**
     * Get the complaint feedback type.
     */
    public function getFeedbackType(): ?string
    {
        return $this->notification->complaint_feedback_type;
    }
}
