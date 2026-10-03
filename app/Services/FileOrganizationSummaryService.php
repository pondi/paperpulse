<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FileOrganizationSummaryService
{
    public function __construct(private OrganizationSummaryNormalizer $normalizer) {}

    public function capture(File $file, Model $entity): File
    {
        if ((int) $entity->getAttribute('user_id') !== (int) $file->user_id || (int) $entity->getAttribute('file_id') !== (int) $file->id) {
            throw ValidationException::withMessages(['file' => 'Organization evidence must belong to this file and owner.']);
        }
        $summary = $this->summarize($entity);

        return $file->getConnection()->transaction(function () use ($file, $summary): File {
            $locked = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->findOrFail($file->id);
            $locked->update(['organization_summary' => $summary]);

            return app(FolderOrganizationService::class)->placeFromSummary($locked);
        });
    }

    public function summarize(Model $entity, array $evidence = []): array
    {
        $data = $entity->attributesToArray();
        foreach (['contract_data', 'invoice_data', 'receipt_data', 'statement_data', 'warranty_data', 'voucher_data'] as $field) {
            $raw = $data[$field] ?? null;
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            if (is_array($raw)) {
                $data = array_replace($raw, $data);
            }
        }
        $type = strtolower(class_basename($entity));
        if ($evidence !== []) {
            $data['organization'] = $evidence;
        }

        return $this->normalizer->normalize($type, $data, $entity->getKey());
    }
}
