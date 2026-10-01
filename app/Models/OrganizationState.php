<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationState extends Model
{
    use HasFactory;

    protected $attributes = ['revision' => 0, 'analyzed_revision' => 0];

    protected $fillable = ['user_id', 'revision', 'analyzed_revision', 'input_fingerprint', 'analyzed_fingerprint', 'debounce_until'];

    protected $casts = ['revision' => 'integer', 'analyzed_revision' => 'integer', 'debounce_until' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
