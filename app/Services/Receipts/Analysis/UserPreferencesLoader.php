<?php

namespace App\Services\Receipts\Analysis;

use App\Models\Category;
use App\Models\File;
use App\Models\User;
use App\Models\UserPreference;

class UserPreferencesLoader
{
    public static function load(int $userId, ?File $file = null): array
    {
        $user = User::findOrFail($userId);
        $generation = $file?->meta['processing_generation'] ?? 'initial';
        $snapshot = $file?->meta['processing_preferences'] ?? [];
        if (($snapshot['generation'] ?? null) === $generation) {
            $values = $snapshot['values'];
        } else {
            $values = [
                'default_currency' => $user->preference('currency', 'NOK'),
                'auto_categorize' => (bool) $user->preference('auto_categorize', true),
                'extract_line_items' => (bool) $user->preference('extract_line_items', true),
                'default_category_id' => $user->preference('default_category_id'),
            ];
        }
        if (! array_key_exists($values['default_currency'], UserPreference::getOptions()['currencies'])) {
            $values['default_currency'] = 'NOK';
        }
        $categoryId = $values['default_category_id'];
        $values['default_category_id'] = $categoryId && Category::withoutGlobalScope('user')->where('user_id', $userId)->whereKey($categoryId)->exists()
            ? (int) $categoryId : null;
        if ($file !== null) {
            $file->meta = array_merge($file->meta ?? [], ['processing_preferences' => ['generation' => $generation, 'values' => $values]]);
            $file->save();
        }

        return ['user' => $user, ...$values];
    }
}
