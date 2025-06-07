<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'vat_number',
        'email',
        'phone',
        'website',
    ];

    protected $appends = ['logo_url'];

    public function logo(): MorphOne
    {
        return $this->morphOne(Logo::class, 'logoable');
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo?->getUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }
}
