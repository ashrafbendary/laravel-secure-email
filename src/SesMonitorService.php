<?php

namespace AshrafBendary\SecureEmail;

use AshrafBendary\SecureEmail\Models\SesNotification;
use AshrafBendary\SecureEmail\Validation\DeliverabilityChecker;
use AshrafBendary\SecureEmail\Validation\DeliverabilityResult;

class SesMonitorService
{
    protected function notificationModel(): string
    {
        return config('secure-email.models.notification') ?: SesNotification::class;
    }

    /**
     * Check if an email has a permanent bounce.
     */
    public function hasPermanentBounce(string $email): bool
    {
        return $this->notificationModel()::hasPermanentBounce($email);
    }

    /**
     * Count bounces for an email.
     */
    public function countBouncesForEmail(
        string $email,
        ?string $subject = null,
        int $days = 0
    ): int {
        return $this->notificationModel()::countBouncesForEmail($email, $subject, $days);
    }

    /**
     * Count complaints for an email.
     */
    public function countComplaintsForEmail(
        string $email,
        ?string $subject = null,
        int $days = 0
    ): int {
        return $this->notificationModel()::countComplaintsForEmail($email, $subject, $days);
    }

    /**
     * Check if an email should be blocked based on rules.
     */
    public function shouldBlockEmail(string $email, ?string $subject = null): bool
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
    protected function shouldBlockDueToBounces(string $email, ?string $subject): bool
    {
        $bounceRules = config('secure-email.rules.bounces', []);

        if (!($bounceRules['enabled'] ?? true)) {
            return false;
        }

        // Block permanent bounces immediately if configured
        if (($bounceRules['block_permanent_bounces'] ?? true) && 
            $this->hasPermanentBounce($email)) {
            return true;
        }

        // Check bounce count
        $maxBounces = $bounceRules['max_bounces'] ?? 3;
        $checkBySubject = $bounceRules['check_by_subject'] ?? false;
        $daysToCheck = $bounceRules['days_to_check'] ?? 30;

        $bounceCount = $this->countBouncesForEmail(
            $email,
            $checkBySubject ? $subject : null,
            $daysToCheck
        );

        return $bounceCount >= $maxBounces;
    }

    /**
     * Check if email should be blocked due to complaints.
     */
    protected function shouldBlockDueToComplaints(string $email, ?string $subject): bool
    {
        $complaintRules = config('secure-email.rules.complaints', []);

        if (!($complaintRules['enabled'] ?? true)) {
            return false;
        }

        $maxComplaints = $complaintRules['max_complaints'] ?? 1;
        $checkBySubject = $complaintRules['check_by_subject'] ?? true;
        $daysToCheck = $complaintRules['days_to_check'] ?? 0;

        $complaintCount = $this->countComplaintsForEmail(
            $email,
            $checkBySubject ? $subject : null,
            $daysToCheck
        );

        return $complaintCount >= $maxComplaints;
    }

    /**
     * Full entry-time check: SES history plus the configured provider.
     */
    public function checkDeliverability(string $email): DeliverabilityResult
    {
        return app(DeliverabilityChecker::class)->check($email);
    }

    /**
     * Whether an address is safe to store (see checkDeliverability()).
     */
    public function isDeliverable(string $email): bool
    {
        return $this->checkDeliverability($email)->passes;
    }

    /**
     * Get all bounces.
     */
    public function getBounces()
    {
        return $this->notificationModel()::bounces()->get();
    }

    /**
     * Get all complaints.
     */
    public function getComplaints()
    {
        return $this->notificationModel()::complaints()->get();
    }

    /**
     * Get all deliveries.
     */
    public function getDeliveries()
    {
        return $this->notificationModel()::deliveries()->get();
    }

    /**
     * Get recent notifications.
     */
    public function getRecentNotifications(int $days = 30)
    {
        return $this->notificationModel()::recent($days)->get();
    }
}
