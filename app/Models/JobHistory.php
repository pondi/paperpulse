<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobHistory extends Model
{
    protected $table = 'job_history';

    protected $fillable = [
        'uuid',
        'parent_uuid',
        'name',
        'queue',
        'payload',
        'status',
        'attempt',
        'progress',
        'order_in_chain',
        'exception',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get all tasks for this job
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(JobHistory::class, 'parent_uuid', 'uuid')
            ->orderBy('order_in_chain');
    }

    /**
     * Get the parent job
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(JobHistory::class, 'parent_uuid', 'uuid');
    }

    /**
     * Scope a query to only include parent jobs
     */
    public function scopeParentJobs($query)
    {
        return $query->whereNull('parent_uuid')
            ->orderBy('created_at', 'desc');
    }
}