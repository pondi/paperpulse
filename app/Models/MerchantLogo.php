<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantLogo extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'logo_data',
        'mime_type'
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
} 