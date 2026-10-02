<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Logo extends Model
{
    use HasFactory;

    protected $fillable = [
        'logo_data',
        'mime_type',
        'hash',
        'logoable_type',
    ];

    /**
     * Get the parent logoable model (Merchant or Vendor).
     */
    public function logoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the data URL for the logo.
     */
    public function getUrl(): string
    {
        return "data:{$this->mime_type};base64,".base64_encode($this->logo_data);
    }

    /**
     * Generate a hash for the logo data.
     */
    public static function generateHash(string $logoData): string
    {
        return hash('sha256', $logoData);
    }

    /**
     * Get the logo data, handling resource streams if necessary.
     */
    public function getLogoDataAttribute($value): string
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $value;
    }

    /**
     * Set the logo data, ensuring it's stored as a string.
     */
    public function setLogoDataAttribute($value): void
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        $this->attributes['logo_data'] = $value;
    }
}
