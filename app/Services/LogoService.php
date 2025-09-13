<?php

namespace App\Services;

use App\Models\Logo;
use App\Models\Merchant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class LogoService
{
    public function __construct(
        private array $commonTerms = [
            'inc', 'inc.', 'ltd', 'ltd.', 'llc', 'llc.',
            'corporation', 'corp', 'corp.', 'company', 'co', 'co.',
        ]
    ) {}

    /**
     * Find or create a logo, checking for potential matches based on entity names
     *
     * @param  string  $logoData  The binary logo data
     * @param  string  $mimeType  The MIME type of the logo
     * @param  class-string<Model>  $entityType  The class name of the entity (Merchant or Vendor)
     * @param  string  $entityName  The name of the entity
     */
    public function findOrCreateLogo(
        string $logoData,
        string $mimeType,
        string $entityType,
        string $entityName,
    ): Logo {
        $hash = Logo::generateHash($logoData);

        // First, try to find an exact match by hash
        $existingLogo = Logo::findByHash($hash);
        if ($existingLogo instanceof Logo) {
            return $existingLogo;
        }

        // If no exact match, look for logos from entities with similar names
        $potentialMatches = $this->findPotentialLogoMatches($entityType, $entityName);

        return $potentialMatches->first() ?? Logo::create([
            'logo_data' => $logoData,
            'mime_type' => $mimeType,
            'hash' => $hash,
            'logoable_type' => $entityType,
        ]);
    }

    /**
     * Find potential logo matches based on entity name
     *
     * @param  class-string<Model>  $entityType
     */
    private function findPotentialLogoMatches(
        string $entityType,
        string $entityName,
    ): Collection {
        $normalizedName = $this->normalizeName($entityName);

        return Logo::query()
            ->with('logoable')
            ->select('logos.*')
            ->selectRaw('COUNT(*) as usage_count')
            ->whereHas('logoable', function ($query) use ($normalizedName) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($normalizedName).'%']);
            })
            ->where('logoable_type', $entityType)
            ->groupBy('logos.id')
            ->orderByDesc('usage_count')
            ->get()
            ->filter(fn (Logo $logo) => $logo->logoable &&
                $this->areNamesSimilar(
                    $this->normalizeName($logo->logoable->name),
                    $normalizedName
                )
            );
    }

    /**
     * Normalize a name for comparison
     */
    private function normalizeName(string $name): string
    {
        $normalized = Str::lower($name);
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);

        return trim(str_replace($this->commonTerms, '', $normalized));
    }

    /**
     * Check if two names are similar using various methods
     */
    private function areNamesSimilar(string $name1, string $name2): bool
    {
        if ($name1 === $name2) {
            return true;
        }

        if (str_contains($name1, $name2) || str_contains($name2, $name1)) {
            return true;
        }

        $levenshtein = levenshtein($name1, $name2);
        $maxLength = max(strlen($name1), strlen($name2));

        return $levenshtein <= min(3, $maxLength * 0.2);
    }

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
        $hash = Logo::generateHash($encodedLogoData);

        DB::transaction(function () use ($model, $encodedLogoData, $mimeType, $hash): void {
            // Delete any existing logo relationship first
            $model->logo()->delete();

            // First, try to find an exact match by hash
            $logo = Logo::where('hash', $hash)->first();

            if (! $logo) {
                // If no exact match, look for logos from entities with similar names
                $potentialMatches = $this->findPotentialLogoMatches($model::class, $model->name);
                $logo = $potentialMatches->first();
            }

            if ($logo) {
                // If we found an existing logo, attach it to the model
                $model->logo()->save($logo);
            } else {
                // If no matches found, create a new logo
                $model->logo()->create([
                    'logo_data' => $encodedLogoData,
                    'mime_type' => $mimeType,
                    'hash' => $hash,
                ]);
            }
        });
    }
}
