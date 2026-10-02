<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklySummaryDelivery extends Model
{
    protected $fillable = ['user_id', 'period_start', 'period_end', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
