<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\User;

class CategoryResolver
{
    public static function resolve(
        array $data,
        ?User $user,
        ?Merchant $merchant,
        ReceiptEnricherContract $enricher,
        bool $autoCategorize,
        ?int $defaultCategoryId
    ): array {
        $categoryName = $autoCategorize ? ($data['merchant']['category'] ?? $data['receipt_category'] ?? null) : null;
        $categoryId = null;

        if ($autoCategorize && ! $categoryName && $merchant) {
            $categoryName = $enricher->categorizeMerchant($merchant->name);
        }

        if ($categoryName && $user) {
            $category = $enricher->findUserCategory($user, $categoryName);
            if ($category) {
                $categoryId = $category->id;
            }
        }

        if (! $categoryId && $defaultCategoryId) {
            $categoryId = $defaultCategoryId;
        }

        $category = $user && $categoryId ? Category::withoutGlobalScope('user')->where('user_id', $user->id)->find($categoryId) : null;

        return [$category?->name, $category?->id];
    }
}
