<?php

namespace App\Services;

use App\Models\Logo;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;

readonly class LogoService
{
    /**
     * Get the image URL for a model that has a logo
     *
     * @param  Model  $model  The model instance
     * @param  string|resource|null  $logoData  The logo data, which is already base64 encoded
     * @param  string|null  $mimeType  The MIME type of the logo
     */
    public function getImageUrl(Model $model, mixed $logoData = null, ?string $mimeType = null): string
    {
        if (! $logoData) {
            // Use internal logo generator instead of external service
            if ($model instanceof Merchant) {
                return route('merchants.logo', ['merchant' => $model->id]);
            }

            // For other models without ID or fallback
            return route('merchants.logo.generate', ['name' => $model->name]);
        }

        $processedLogoData = is_resource($logoData)
            ? stream_get_contents($logoData)
            : $logoData;

        return "data:{$mimeType};base64,".$processedLogoData;
    }

    /**
     * Update the logo for a model
     *
     * @param  Model  $model  The model instance
     * @param  string  $logoData  The raw binary logo data
     * @param  string  $mimeType  The MIME type of the logo
     */
    public function updateModelLogo(Model $model, string $logoData, string $mimeType): void
    {
        $encodedLogoData = base64_encode($logoData);

        $model->getConnection()->transaction(function () use ($model, $encodedLogoData, $mimeType): void {
            $model->logo()->updateOrCreate([], [
                'logo_data' => $encodedLogoData,
                'mime_type' => $mimeType,
                'hash' => Logo::generateHash($encodedLogoData),
            ]);
        });
        $model->unsetRelation('logo');
    }
}
