<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function logo()
    {
        return $this->hasOne(VendorLogo::class);
    }

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return 'data:' . $this->logo->mime_type . ';base64,' . $this->logo->logo_data;
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    public function lineItems()
    {
        return $this->hasMany(LineItem::class);
    }

    public function receipts()
    {
        return $this->hasManyThrough(Receipt::class, LineItem::class);
    }
} 