<?php

namespace AshrafBendary\SecureEmail;

use AshrafBendary\SecureEmail\Models\SesNotification;

class SesMonitorService
{
    /**
     * Check if an email has a permanent bounce.
     */
    public function hasPermanentBounce(string $email): bool
    {
        return SesNotification::hasPermanentBounce($email);
    }

    /**
     * Count bounces for an email.
     */
    public function countBouncesForEmail(
        string $email,
        ?string $subject = null,
        int $days = 0
    ): int {
        return SesNotification::countBouncesForEmail($email, $subject, $days);
    }

    /**
     * Count complaints for an email.
     */
    public function countComplaintsForEmail(
        string $email,
        ?string $subject = null,
        int $days = 0
    ): int {
        return SesNotification::countComplaintsForEmail($email, $subject, $days);
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
     * Get all bounces.
     */
    public function getBounces()
    {
        return SesNotification::bounces()->get();
    }

    /**
     * Get all complaints.
     */
    public function getComplaints()
    {
        return SesNotification::complaints()->get();
    }

    /**
     * Get all deliveries.
     */
    public function getDeliveries()
    {
        return SesNotification::deliveries()->get();
    }

    /**
     * Get recent notifications.
     */
    public function getRecentNotifications(int $days = 30)
    {
        return SesNotification::recent($days)->get();
    }
}
