<?php

namespace AshrafBendary\SecureEmail\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use AshrafBendary\SecureEmail\Models\SesNotification;


class CheckEmailBeforeSending
{
    protected function notificationModel(): string
    {
        return config('secure-email.models.notification') ?: SesNotification::class;
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSending $event): bool
    {
        if (!config('secure-email.enabled', true)) {
            return true;
        }

        $message = $event->message;
        $recipients = $this->extractRecipients($message);
        $subject = $message->getHeaders()->get('Subject')?->getBodyAsString() ?? '';

        foreach ($recipients as $email) {
            if ($this->shouldBlockEmail($email, $subject)) {
                Log::info('Email blocked by SES Monitor', [
                    'email' => $email,
                    'subject' => $subject,
                ]);
                
                // Return false to prevent email from being sent
                return false;
            }
        }

        return true;
    }

    /**
     * Extract recipients from the message.
     */
    protected function extractRecipients($message): array
    {
        $recipients = [];
        $headers = $message->getHeaders();

        // Get To recipients
        if ($to = $headers->get('To')) {
            foreach ($to->getAddresses() as $address) {
                $recipients[] = $address->getAddress();
            }
        }

        // Get Cc recipients
        if ($cc = $headers->get('Cc')) {
            foreach ($cc->getAddresses() as $address) {
                $recipients[] = $address->getAddress();
            }
        }

        // Get Bcc recipients
        if ($bcc = $headers->get('Bcc')) {
            foreach ($bcc->getAddresses() as $address) {
                $recipients[] = $address->getAddress();
            }
        }

        return array_unique($recipients);
    }

    /**
     * Check if email should be blocked.
     */
    protected function shouldBlockEmail(string $email, string $subject): bool
    {
        // Check bounce rules
        if ($this->shouldBlockDueToBounces($email, $subject)) {
            return true;
        }

        // Check complaint rules
        if ($this->shouldBlockDueToComplaints($email, $subject)) {
            return true;
        }

        return false;
    }

    /**
     * Check if email should be blocked due to bounces.
     */
    protected function shouldBlockDueToBounces(string $email, string $subject): bool
    {
        $bounceRules = config('secure-email.rules.bounces', []);

        if (!($bounceRules['enabled'] ?? true)) {
            return false;
        }
        
        $notificationModel = $this->notificationModel();

        // Block permanent bounces immediately if configured
        if (($bounceRules['block_permanent_bounces'] ?? true) && 
            $notificationModel::hasPermanentBounce($email)) {
            return true;
        }

        // Check bounce count
        $maxBounces = $bounceRules['max_bounces'] ?? 3;
        $checkBySubject = $bounceRules['check_by_subject'] ?? false;
        $daysToCheck = $bounceRules['days_to_check'] ?? 30;

        $bounceCount = $notificationModel::countBouncesForEmail(
            $email,
            $checkBySubject ? $subject : null,
            $daysToCheck
        );

        return $bounceCount >= $maxBounces;
    }

    /**
     * Check if email should be blocked due to complaints.
     */
    protected function shouldBlockDueToComplaints(string $email, string $subject): bool
    {
        $complaintRules = config('secure-email.rules.complaints', []);

        if (!($complaintRules['enabled'] ?? true)) {
            return false;
        }

        $maxComplaints = $complaintRules['max_complaints'] ?? 1;
        $checkBySubject = $complaintRules['check_by_subject'] ?? true;
        $daysToCheck = $complaintRules['days_to_check'] ?? 0;

        $notificationModel = $this->notificationModel();
        
        $complaintCount = $notificationModel::countComplaintsForEmail(
            $email,
            $checkBySubject ? $subject : null,
            $daysToCheck
        );

        return $complaintCount >= $maxComplaints;
    }
}
