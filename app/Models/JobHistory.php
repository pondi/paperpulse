<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobHistory extends Model
{
    protected $fillable = [
        'job_id',
        'job_uuid',
        'name',
        'status',
        'queue',
        'payload',
        'exception',
        'attempts',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempts' => 'integer'
    ];
} 