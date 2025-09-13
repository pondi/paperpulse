<?php

namespace App\Services\Receipts\Analysis;

use App\Models\User;

class UserPreferencesLoader
{
    public static function load(int $userId): array
    {
        $user = User::find($userId);

        return [
            'user' => $user,
            'default_currency' => $user ? $user->preference('currency', 'NOK') : 'NOK',
            'auto_categorize' => $user ? $user->preference('auto_categorize', true) : true,
            'extract_line_items' => $user ? $user->preference('extract_line_items', true) : true,
            'default_category_id' => $user ? $user->preference('default_category_id', null) : null,
        ];
    }
}
