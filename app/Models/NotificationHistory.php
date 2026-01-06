<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationHistory extends Model
{
    use HasFactory;

    protected $table = 'notification_history';

    protected $fillable = [
        'user_id',
        'notification_type',
        'entity_type',
        'entity_id',
        'notified_at',
        'meta',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
