<?php

namespace App\Services\Receipts;

use App\Models\Receipt;

class ReceiptTransformer
{
    public static function forIndex(Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'merchant' => $receipt->merchant,
            'category' => $receipt->category,
            'category_id' => $receipt->category_id,
            'receipt_date' => $receipt->receipt_date,
            'tax_amount' => $receipt->tax_amount,
            'total_amount' => $receipt->total_amount,
            'currency' => $receipt->currency,
            'receipt_category' => $receipt->receipt_category,
            'receipt_description' => $receipt->receipt_description,
            'file' => $receipt->file ? [
                'id' => $receipt->file->id,
                'url' => route('receipts.showImage', $receipt->id),
                'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
                'extension' => $receipt->file->fileExtension ?? 'jpg',
                'mime_type' => $receipt->file->mime_type,
            ] : null,
            'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->text,
                    'sku' => $item->sku,
                    'quantity' => $item->qty,
                    'unit_price' => $item->price,
                    'total_amount' => $item->total,
                ];
            }) : [],
            'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }) : [],
        ];
    }

    public static function forShow(Receipt $receipt): array
    {
        $fileInfo = $receipt->file ? [
            'id' => $receipt->file->id,
            'url' => route('receipts.showImage', $receipt->id),
            'pdfUrl' => $receipt->file->guid ? route('receipts.showPdf', $receipt->id) : null,
            'extension' => $receipt->file->fileExtension ?? 'jpg',
            'mime_type' => $receipt->file->mime_type,
        ] : null;

        $isOwner = auth()->id() === $receipt->user_id;
        $sharedUsers = [];
        if ($isOwner && $receipt->relationLoaded('sharedUsers')) {
            $sharedUsers = $receipt->sharedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permission' => $user->pivot->permission ?? 'view',
                    'shared_at' => optional($user->pivot->shared_at)->toIso8601String(),
                ];
            })->values();
        }

        return [
            'id' => $receipt->id,
            'merchant' => $receipt->merchant,
            'receipt_date' => $receipt->receipt_date,
            'tax_amount' => $receipt->tax_amount,
            'total_amount' => $receipt->total_amount,
            'currency' => $receipt->currency,
            'receipt_category' => $receipt->receipt_category,
            'receipt_description' => $receipt->receipt_description,
            'file' => $fileInfo,
            'lineItems' => $receipt->lineItems ? $receipt->lineItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->text,
                    'sku' => $item->sku,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            }) : [],
            'tags' => $receipt->tags ? $receipt->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }) : [],
            'shared_users' => $sharedUsers,
        ];
    }
}
