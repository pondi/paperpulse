<?php

namespace App\Models;

use App\Enums\DeletedReason;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'website',
        'contact_email',
        'contact_phone',
        'description',
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
            ?? route('merchants.logo.generate', ['name' => $this->name]);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(LineItem::class);
    }

    public function receipts(): HasManyThrough
    {
        return $this->hasManyThrough(Receipt::class, LineItem::class);
    }
}
