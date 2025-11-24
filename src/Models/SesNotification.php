<?php
// src/Models/SesNotification.php

namespace AshrafBendary\SecureEmail\Models;

use Illuminate\Database\Eloquent\Model;

class SesNotification extends Model
{
    protected $table;

    protected $fillable = [
        'message_id',
        'email',
        'type',
        'status',
        'raw_notification',
    ];

    protected $casts = [
        'raw_notification' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('secure-email.tables.notifications', 'ses_notifications');
    }
}