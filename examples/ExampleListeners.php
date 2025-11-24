<?php

namespace AshrafBendary\SecureEmail\Examples;

use AshrafBendary\SecureEmail\Events\SesBounceReceived;
use AshrafBendary\SecureEmail\Events\SesComplaintReceived;
use AshrafBendary\SecureEmail\Events\SesDeliveryReceived;

/**
 * Example listener implementations for SES Monitor events.
 * 
 * Copy these to your app/Listeners directory and register them
 * in your EventServiceProvider.
 */

class ExampleBounceListener
{
    /**
     * Handle the bounce event.
     */
    public function handle(SesBounceReceived $event): void
    {
        $notification = $event->getNotification();
        $email = $event->getEmail();

        // Log the bounce
        \Log::warning('Email bounced', [
            'email' => $email,
            'type' => $notification->bounce_type,
            'sub_type' => $notification->bounce_sub_type,
        ]);

        // If permanent bounce, mark user as unsubscribed
        if ($event->isPermanent()) {
            // Example: Update user record
            // User::where('email', $email)->update(['subscribed' => false]);
            
            \Log::error('Permanent bounce detected', ['email' => $email]);
        }

        // Send notification to admin
        // Mail::to('[email protected]')->send(new BounceNotification($notification));
    }
}

class ExampleComplaintListener
{
    /**
     * Handle the complaint event.
     */
    public function handle(SesComplaintReceived $event): void
    {
        $notification = $event->getNotification();
        $email = $event->getEmail();

        // Log the complaint
        \Log::error('Email complaint received', [
            'email' => $email,
            'feedback_type' => $event->getFeedbackType(),
        ]);

        // Immediately unsubscribe the user
        // User::where('email', $email)->update(['subscribed' => false]);

        // Send urgent notification to admin
        // Mail::to('[email protected]')->send(new ComplaintNotification($notification));

        // Consider triggering a review of recent emails sent to this user
        // ReviewEmailsJob::dispatch($email);
    }
}

class ExampleDeliveryListener
{
    /**
     * Handle the delivery event.
     */
    public function handle(SesDeliveryReceived $event): void
    {
        $notification = $event->getNotification();
        $email = $event->getEmail();

        // Log successful delivery
        \Log::info('Email delivered successfully', [
            'email' => $email,
            'subject' => $notification->subject,
        ]);

        // Update email campaign statistics
        // EmailCampaign::where('message_id', $notification->message_id)
        //     ->increment('delivered_count');

        // Track engagement metrics
        // Analytics::track('email_delivered', [
        //     'email' => $email,
        //     'timestamp' => now(),
        // ]);
    }
}
