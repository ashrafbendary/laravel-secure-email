<?php

declare(strict_types=1);

namespace AshrafBendary\SecureEmail\Validation;

use AshrafBendary\SecureEmail\SesMonitorService;

/**
 * Answers "is this address safe to store?" at the point it is entered
 * (registration, profile update, admin forms), as opposed to the
 * MessageSending listener, which guards each outgoing send.
 *
 * 1. Local SES history (cheap, database) using the same bounce and
 *    complaint rules as the send-time listener.
 * 2. The configured real-time provider (network, cached, soft-fails).
 */
final class DeliverabilityChecker
{
    public function __construct(
        private readonly SesMonitorService $history,
        private readonly EmailValidator $validator,
    ) {}

    public function check(string $email): DeliverabilityResult
    {
        $email = trim($email);

        if ($email === '' || ! config('secure-email.enabled', true)) {
            return DeliverabilityResult::accepted();
        }

        if (config('secure-email.validation.check_history', true)) {
            $reason = $this->historyReason($email);
            if ($reason !== null) {
                return DeliverabilityResult::rejected($reason);
            }
        }

        $result = $this->validator->validate($email);

        return $result->deliverable === false
            ? DeliverabilityResult::rejected('undeliverable', $result->status)
            : DeliverabilityResult::accepted($result->status);
    }

    public function passes(string $email): bool
    {
        return $this->check($email)->passes;
    }

    private function historyReason(string $email): ?string
    {
        $bounces = (array) config('secure-email.rules.bounces', []);

        if ($bounces['enabled'] ?? true) {
            if (($bounces['block_permanent_bounces'] ?? true) && $this->history->hasPermanentBounce($email)) {
                return 'permanent_bounce';
            }

            $count = $this->history->countBouncesForEmail($email, null, (int) ($bounces['days_to_check'] ?? 30));
            if ($count >= (int) ($bounces['max_bounces'] ?? 3)) {
                return 'bounces';
            }
        }

        $complaints = (array) config('secure-email.rules.complaints', []);

        if ($complaints['enabled'] ?? true) {
            $count = $this->history->countComplaintsForEmail($email, null, (int) ($complaints['days_to_check'] ?? 0));
            if ($count >= (int) ($complaints['max_complaints'] ?? 1)) {
                return 'complaints';
            }
        }

        return null;
    }
}
