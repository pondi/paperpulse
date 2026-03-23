<?php

declare(strict_types=1);

namespace App\Http\Resources\Inertia;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin File
 */
class PublicCollectionFileResource extends JsonResource
{
    private string $token;

    public function __construct($resource, string $token)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        $extension = $this->fileExtension ?? 'pdf';
        $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        $isPdf = strtolower($extension) === 'pdf' || ! empty($this->s3_archive_path);

        $serveBaseUrl = route('shared.collections.file', ['token' => $this->token, 'guid' => $this->guid]);
        $previewUrl = $this->has_image_preview ? $serveBaseUrl.'?variant=preview' : null;
        $viewUrl = $serveBaseUrl.'?variant=original';
        $pdfUrl = $isPdf ? $serveBaseUrl.'?variant='.(! empty($this->s3_archive_path) ? 'archive' : 'original').'&ext=pdf' : null;

        $entityData = $this->buildEntityData();

        return [
            'id' => $this->id,
            'guid' => $this->guid,
            'name' => $this->fileName,
            'entity_title' => $entityData['title'],
            'file_type' => $this->file_type,
            'extension' => $extension,
            'has_preview' => (bool) $this->has_image_preview,
            'is_image' => $isImage,
            'is_pdf' => $isPdf,
            'previewUrl' => $previewUrl,
            'viewUrl' => $viewUrl,
            'pdfUrl' => $pdfUrl,
            'downloadUrl' => $serveBaseUrl.'?download=1',
            'entity_type' => $entityData['type'],
            'entity_details' => $entityData['details'],
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])),
        ];
    }

    /**
     * @return array{type: string|null, title: string|null, details: array<string, mixed>}
     */
    private function buildEntityData(): array
    {
        $primaryEntity = $this->relationLoaded('primaryEntity') ? $this->primaryEntity : null;

        if (! $primaryEntity || ! $primaryEntity->entity) {
            return ['type' => null, 'title' => null, 'details' => []];
        }

        $entity = $primaryEntity->entity;
        $type = $primaryEntity->entity_type;

        $parties = is_array($entity->parties ?? null) ? $entity->parties : [];

        $title = match ($type) {
            'receipt' => $entity->merchant_name ?? null,
            'document' => $entity->title ?? null,
            'invoice' => $entity->invoice_number
                ? 'Invoice #'.$entity->invoice_number
                : ($entity->from_name ?? null),
            'contract' => $entity->contract_title ?? null,
            'voucher' => $entity->code ?? null,
            'bank_statement' => $entity->bank_name
                ? 'Statement - '.$entity->bank_name
                : null,
            default => null,
        };

        return [
            'type' => $type,
            'title' => $title,
            'details' => match ($type) {
                'receipt' => [
                    'merchant_name' => $entity->merchant_name ?? null,
                    'purchase_date' => $entity->purchase_date?->toDateString(),
                    'total' => $entity->total ?? null,
                    'currency' => $entity->currency ?? null,
                ],
                'document' => [
                    'title' => $entity->title ?? null,
                    'summary' => $entity->summary ?? null,
                ],
                'invoice' => [
                    'vendor_name' => $entity->from_name ?? null,
                    'invoice_number' => $entity->invoice_number ?? null,
                    'due_date' => $entity->due_date?->toDateString(),
                    'total_amount' => $entity->total_amount ?? null,
                    'currency' => $entity->currency ?? null,
                ],
                'contract' => [
                    'title' => $entity->contract_title ?? null,
                    'parties' => ! empty($parties) ? implode(' & ', $parties) : null,
                    'effective_date' => $entity->effective_date?->toDateString(),
                ],
                'voucher' => [
                    'code' => $entity->code ?? null,
                    'value' => $entity->current_value ?? $entity->original_value ?? null,
                    'currency' => $entity->currency ?? null,
                    'expires_at' => $entity->expiry_date?->toDateString(),
                ],
                'bank_statement' => [
                    'bank_name' => $entity->bank_name ?? null,
                    'account_number_masked' => $entity->account_number ? '****'.substr($entity->account_number, -4) : null,
                    'statement_period' => $entity->statement_period_start?->toDateString().' - '.$entity->statement_period_end?->toDateString(),
                    'currency' => $entity->currency ?? null,
                ],
                default => [],
            },
        ];
    }
}
