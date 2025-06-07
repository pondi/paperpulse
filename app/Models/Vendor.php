<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website',
        'contact_email',
        'contact_phone',
        'description'
    ];

    protected $appends = ['logo_url'];

    public function logo(): MorphOne
    {
        return $this->morphOne(Logo::class, 'logoable');
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo?->getUrl() 
            ?? "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&color=7F9CF5&background=EBF4FF";
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