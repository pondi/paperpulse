<?php

namespace App\Http\Resources\Inertia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptInertiaResource extends JsonResource
{
    protected bool $detailed = false;

    public static function forIndex($resource): self
    {
        return new self($resource);
    }

    public static function forShow($resource): self
    {
        $instance = new self($resource);
        $instance->detailed = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $fileInfo = $this->buildFileInfo();

        if (! $this->detailed) {
            return [
                'id' => $this->id,
                'merchant' => $this->merchant,
                'category' => $this->category,
                'category_id' => $this->category_id,
                'receipt_date' => $this->receipt_date,
                'tax_amount' => $this->tax_amount,
                'total_amount' => $this->total_amount,
                'currency' => $this->currency,
                'receipt_category' => $this->receipt_category,
                'receipt_description' => $this->receipt_description,
                'note' => $this->note,
                'file' => $fileInfo,
                'lineItems' => $this->mapLineItemsForIndex(),
                'tags' => $this->mapTags(),
            ];
        }

        $isOwner = auth()->id() === $this->user_id;
        $sharedUsers = [];
        if ($isOwner && $this->relationLoaded('sharedUsers')) {
            $sharedUsers = $this->sharedUsers->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permission' => $user->pivot->permission ?? 'view',
                'shared_at' => optional($user->pivot->shared_at),
            ])->values();
        }

        $collections = [];
        if ($this->file && $this->file->relationLoaded('collections')) {
            $collections = $this->file->collections->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'color' => $c->color,
            ])->values();
        }

        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'merchant' => $this->merchant,
            'receipt_date' => $this->receipt_date,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'receipt_category' => $this->receipt_category,
            'receipt_description' => $this->receipt_description,
            'note' => $this->note,
            'file' => $fileInfo,
            'lineItems' => $this->mapLineItemsForShow(),
            'tags' => $this->mapTags(),
            'collections' => $collections,
            'shared_users' => $sharedUsers,
        ];
    }

    private function buildFileInfo(): ?array
    {
        if (! $this->file) {
            return null;
        }

        return [
            'id' => $this->file->id,
            'url' => route('receipts.showImage', $this->id),
            'pdfUrl' => $this->file->guid && $this->file->fileExtension === 'pdf'
                ? route('receipts.showPdf', $this->id)
                : null,
            'extension' => $this->file->fileExtension ?? 'jpg',
            'mime_type' => $this->file->mime_type,
            'has_preview' => $this->file->has_image_preview,
            'is_pdf' => strtolower($this->file->fileExtension ?? '') === 'pdf',
        ];
    }

    private function mapLineItemsForIndex(): array
    {
        if (! $this->lineItems) {
            return [];
        }

        return $this->lineItems->map(fn ($item) => [
            'id' => $item->id,
            'description' => $item->text,
            'sku' => $item->sku,
            'quantity' => $item->qty,
            'unit_price' => $item->price,
            'total_amount' => $item->total,
        ])->all();
    }

    private function mapLineItemsForShow(): array
    {
        if (! $this->lineItems) {
            return [];
        }

        return $this->lineItems->map(fn ($item) => [
            'id' => $item->id,
            'text' => $item->text,
            'sku' => $item->sku,
            'qty' => $item->qty,
            'price' => $item->price,
            'total' => $item->total,
        ])->all();
    }

    private function mapTags(): array
    {
        if (! $this->relationLoaded('tags')) {
            return [];
        }

        return $this->getRelation('tags')->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->all();
    }
}
