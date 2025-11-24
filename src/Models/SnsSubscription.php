<?php

namespace AshrafBendary\SecureEmail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SnsSubscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'topic_arn',
        'subscription_arn',
        'type',
        'subscribe_url',
        'token',
        'confirmed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the table name from config.
     */
    public function getTable(): string
    {
        return config('secure-email.table_names.subscriptions', 'sns_subscriptions');
    }

    /**
     * Get the connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return config('secure-email.database_connection');
    }

    /**
     * Check if the subscription is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Mark the subscription as confirmed.
     */
    public function markAsConfirmed(string $subscriptionArn): bool
    {
        return $this->update([
            'subscription_arn' => $subscriptionArn,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Scope to get only confirmed subscriptions.
     */
    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at');
    }

    /**
     * Scope to get only unconfirmed subscriptions.
     */
    public function scopeUnconfirmed($query)
    {
        return $query->whereNull('confirmed_at');
    }

    /**
     * Scope to get subscriptions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
