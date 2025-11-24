<?php
// src/Models/EmailBlacklist.php

namespace AshrafBendary\SecureEmail\Models;

use Illuminate\Database\Eloquent\Model;

class EmailBlacklist extends Model
{
    protected $table;

    protected $fillable = [
        'email',
        'reason',
        'bounce_type',
        'bounce_count',
        'details',
        'last_bounce_at',
    ];

    protected $casts = [
        'last_bounce_at' => 'datetime',
        'details' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('secure-email.tables.blacklist', 'email_blacklist');
    }

    public static function isBlacklisted(string $email): bool
    {
        $record = self::where('email', strtolower($email))->first();

        if (!$record) {
            return false;
        }

        $threshold = config('secure-email.blacklist.soft_bounce_threshold', 3);

        // Manual, invalid, and complaints are considered blacklisted
        if (in_array($record->reason, ['manual', 'invalid', 'complaint'])) {
            return true;
        }

        // Hard bounces are blacklisted
        if ($record->bounce_type === 'hard') {
            return true;
        }

        // Soft bounces are blacklisted only when they exceed threshold
        if (($record->bounce_count ?? 0) >= $threshold) {
            return true;
        }

        return false;
    }

    public static function addToBlacklist(string $email, string $reason, array $details = []): void
    {
        $email = strtolower($email);
        $bounceType = isset($details['bounce_type']) ? strtolower($details['bounce_type']) : null;
        $now = now();

        $record = self::where('email', $email)->first();

        // Normalize bounce type to our enum values
        if ($bounceType === 'permanent' || $bounceType === 'hard') {
            $normalizedBounceType = 'hard';
        } elseif ($bounceType === 'transient' || $bounceType === 'soft') {
            $normalizedBounceType = 'soft';
        } else {
            $normalizedBounceType = $bounceType;
        }

        // If no existing record, create one
        if (!$record) {
            self::create([
                'email' => $email,
                'reason' => $reason,
                'bounce_type' => $normalizedBounceType,
                'bounce_count' => 1,
                'details' => $details,
                'last_bounce_at' => $now,
            ]);

            return;
        }

        // Existing record: handle soft bounces differently (increment only)
        if ($reason === 'bounce' && $normalizedBounceType === 'soft') {
            $record->increment('bounce_count');
            $record->details = array_merge($record->details ?? [], $details);
            $record->last_bounce_at = $now;
            $record->save();
            return;
        }

        // For hard bounces, complaints, invalids, or manual actions, update and increment
        $record->update([
            'reason' => $reason,
            'bounce_type' => $normalizedBounceType,
            'details' => array_merge($record->details ?? [], $details),
            'last_bounce_at' => $now,
        ]);

        $record->increment('bounce_count');
    }

    public static function removeFromBlacklist(string $email): bool
    {
        return self::where('email', strtolower($email))->delete();
    }
}