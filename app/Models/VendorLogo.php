<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorLogo extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'logo_data',
        'mime_type'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
} 