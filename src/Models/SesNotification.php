<?php

namespace AshrafBendary\SecureEmail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class SesNotification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'message_id',
        'type',
        'notification_type',
        'email',
        'subject',
        'bounce_type',
        'bounce_sub_type',
        'complaint_feedback_type',
        'notification_data',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notification_data' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the table name from config.
     */
    public function getTable(): string
    {
        return config('ses-monitor.table_names.notifications', 'ses_notifications');
    }

    /**
     * Get the connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('ses-monitor.database_connection');
    }

    /**
     * Check if the notification is a bounce.
     */
    public function isBounce(): bool
    {
        return $this->type === 'Bounce';
    }

    /**
     * Check if the notification is a complaint.
     */
    public function isComplaint(): bool
    {
        return $this->type === 'Complaint';
    }

    /**
     * Check if the notification is a delivery.
     */
    public function isDelivery(): bool
    {
        return $this->type === 'Delivery';
    }

    /**
     * Check if the bounce is permanent.
     */
    public function isPermanentBounce(): bool
    {
        return $this->isBounce() && $this->bounce_type === 'Permanent';
    }

    /**
     * Check if the bounce is transient.
     */
    public function isTransientBounce(): bool
    {
        return $this->isBounce() && $this->bounce_type === 'Transient';
    }

    /**
     * Scope to get only bounces.
     */
    public function scopeBounces(Builder $query): Builder
    {
        return $query->where('type', 'Bounce');
    }

    /**
     * Scope to get only complaints.
     */
    public function scopeComplaints(Builder $query): Builder
    {
        return $query->where('type', 'Complaint');
    }

    /**
     * Scope to get only deliveries.
     */
    public function scopeDeliveries(Builder $query): Builder
    {
        return $query->where('type', 'Delivery');
    }

    /**
     * Scope to get notifications for a specific email.
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to get notifications with a specific subject.
     */
    public function scopeWithSubject(Builder $query, string $subject): Builder
    {
        return $query->where('subject', $subject);
    }

    /**
     * Scope to get permanent bounces.
     */
    public function scopePermanentBounces(Builder $query): Builder
    {
        return $query->where('type', 'Bounce')
                     ->where('bounce_type', 'Permanent');
    }

    /**
     * Scope to get recent notifications within days.
     */
    public function scopeRecent(Builder $query, int $days): Builder
    {
        if ($days > 0) {
            return $query->where('created_at', '>=', now()->subDays($days));
        }
        
        return $query;
    }

    /**
     * Count bounces for an email.
     */
    public static function countBouncesForEmail(
        string $email, 
        ?string $subject = null, 
        int $days = 0
    ): int {
        $query = static::bounces()->forEmail($email);

        if ($subject) {
            $query->withSubject($subject);
        }

        return $query->recent($days)->count();
    }

    /**
     * Count complaints for an email.
     */
    public static function countComplaintsForEmail(
        string $email, 
        ?string $subject = null, 
        int $days = 0
    ): int {
        $query = static::complaints()->forEmail($email);

        if ($subject) {
            $query->withSubject($subject);
        }

        return $query->recent($days)->count();
    }

    /**
     * Check if email has permanent bounce.
     */
    public static function hasPermanentBounce(string $email): bool
    {
        return static::permanentBounces()->forEmail($email)->exists();
    }
}
