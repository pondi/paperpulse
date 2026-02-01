<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'vat_number',
        'email',
        'phone',
        'website',
    ];

    protected $appends = ['logo_url'];

    protected $casts = [
        'deleted_reason' => DeletedReason::class,
    ];

    public function logo(): MorphOne
    {
        return $this->morphOne(Logo::class, 'logoable');
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo?->getUrl()
            ?? route('merchants.logo', ['merchant' => $this->id]);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
