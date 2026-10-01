<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInputChange extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'change_key', 'entity_type', 'entity_id', 'revision'];

    protected $casts = ['revision' => 'integer', 'entity_id' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
