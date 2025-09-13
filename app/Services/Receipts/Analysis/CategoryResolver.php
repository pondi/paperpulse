<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
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
        $categoryName = $data['merchant']['category'] ?? $data['receipt_category'] ?? null;
        $categoryId = null;

        if ($autoCategorize && !$categoryName && $merchant) {
            $categoryName = $enricher->categorizeMerchant($merchant->name);
        }

        if ($categoryName && $user) {
            $category = $enricher->findUserCategory($user, $categoryName);
            if ($category) {
                $categoryId = $category->id;
            }
        }

        if (!$categoryId && $defaultCategoryId) {
            $categoryId = $defaultCategoryId;
        }

        return [$categoryName, $categoryId];
    }
}

