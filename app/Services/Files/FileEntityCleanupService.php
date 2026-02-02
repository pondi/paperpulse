<?php

namespace App\Services\Files;

use App\Enums\DeletedReason;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LineItem;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Handles cleanup of extracted entities during file reprocessing.
 *
 * This service soft-deletes entities and removes them from search indexes
 * before new processing begins, then hard-deletes them after successful
 * reprocessing completes.
 */
class FileEntityCleanupService
{
    /**
     * Entity types that have a direct file_id relationship.
     * Order matters: parent entities that may have children should come first.
     *
     * @var array<class-string<Model>>
     */
    protected array $entityTypesWithFileId = [
        Receipt::class,
        Document::class,
        Invoice::class,
        BankStatement::class,
        Contract::class,
        Voucher::class,
        Warranty::class,
        ReturnPolicy::class,
    ];

    /**
     * Soft-delete ALL entities associated with a file and remove from search index.
     *
     * This queries each entity type directly by file_id to ensure ALL entities
     * are deleted, including any duplicates that may have been created.
     *
     * @return array{entities: array<array{type: string, id: int}>, count: int}
     */
    public function softDeleteAndUnindexEntities(File $file): array
    {
        $deletedEntities = [];

        Log::info('[FileEntityCleanup] Starting entity cleanup', [
            'file_id' => $file->id,
            'file_guid' => $file->guid,
        ]);

        // Delete each entity type directly by file_id (catches ALL entities including duplicates)
        foreach ($this->entityTypesWithFileId as $entityClass) {
            $entities = $entityClass::where('file_id', $file->id)->get();

            foreach ($entities as $entity) {
                // Delete child entities first
                $childInfo = $this->softDeleteChildEntities($entity);
                $deletedEntities = array_merge($deletedEntities, $childInfo);

                // Remove from search index before soft-deleting
                if (method_exists($entity, 'unsearchable')) {
                    try {
                        $entity->unsearchable();
                    } catch (\Exception $e) {
                        Log::warning('[FileEntityCleanup] Failed to remove entity from search index', [
                            'entity_type' => $entityClass,
                            'entity_id' => $entity->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Soft-delete the entity
                $entity->deleted_reason = DeletedReason::Reprocess;
                $entity->save();
                $entity->delete();

                $deletedEntities[] = [
                    'type' => $entityClass,
                    'id' => $entity->id,
                ];

                Log::debug('[FileEntityCleanup] Soft-deleted entity', [
                    'type' => $entityClass,
                    'id' => $entity->id,
                ]);
            }
        }

        // Also clean up ExtractableEntity junction records
        $extractableEntities = ExtractableEntity::where('file_id', $file->id)->get();
        foreach ($extractableEntities as $extractableEntity) {
            $extractableEntity->deleted_reason = DeletedReason::Reprocess;
            $extractableEntity->save();
            $extractableEntity->delete();

            $deletedEntities[] = [
                'type' => ExtractableEntity::class,
                'id' => $extractableEntity->id,
            ];
        }

        Log::info('[FileEntityCleanup] Entity cleanup completed', [
            'file_id' => $file->id,
            'entities_deleted' => count($deletedEntities),
        ]);

        return [
            'entities' => $deletedEntities,
            'count' => count($deletedEntities),
        ];
    }

    /**
     * Soft-delete child entities (LineItem, InvoiceLineItem, BankTransaction).
     *
     * @param  Receipt|Document|Invoice|BankStatement|Contract|Voucher|Warranty|ReturnPolicy  $entity
     * @return array<array{type: string, id: int}>
     */
    protected function softDeleteChildEntities(mixed $entity): array
    {
        $deletedChildren = [];

        // Receipt -> LineItems
        if ($entity instanceof Receipt) {
            foreach ($entity->lineItems as $lineItem) {
                // Remove from search index
                if (method_exists($lineItem, 'unsearchable')) {
                    try {
                        $lineItem->unsearchable();
                    } catch (\Exception $e) {
                        Log::warning('[FileEntityCleanup] Failed to remove line item from search index', [
                            'line_item_id' => $lineItem->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $lineItem->deleted_reason = DeletedReason::Reprocess;
                $lineItem->save();
                $lineItem->delete();

                $deletedChildren[] = [
                    'type' => LineItem::class,
                    'id' => $lineItem->id,
                ];
            }
        }

        // Invoice -> InvoiceLineItems
        if ($entity instanceof Invoice) {
            foreach ($entity->lineItems as $lineItem) {
                $lineItem->deleted_reason = DeletedReason::Reprocess;
                $lineItem->save();
                $lineItem->delete();

                $deletedChildren[] = [
                    'type' => InvoiceLineItem::class,
                    'id' => $lineItem->id,
                ];
            }
        }

        // BankStatement -> BankTransactions
        if ($entity instanceof BankStatement) {
            foreach ($entity->transactions as $transaction) {
                $transaction->deleted_reason = DeletedReason::Reprocess;
                $transaction->save();
                $transaction->delete();

                $deletedChildren[] = [
                    'type' => BankTransaction::class,
                    'id' => $transaction->id,
                ];
            }
        }

        return $deletedChildren;
    }

    /**
     * Permanently delete previously soft-deleted entities.
     *
     * Called after successful reprocessing to clean up old records.
     *
     * @param  array<string, mixed>  $entityInfo  Expected keys: 'entities' and 'count'
     */
    public function hardDeleteEntities(array $entityInfo): void
    {
        /** @var array<array{type: string, id: int}> $entities */
        $entities = $entityInfo['entities'] ?? [];

        if ($entities === []) {
            return;
        }

        Log::info('[FileEntityCleanup] Starting hard delete of old entities', [
            'entity_count' => count($entities),
        ]);

        // Delete in correct order: children first, then parents, then junction
        $orderedTypes = [
            // Children first
            LineItem::class,
            InvoiceLineItem::class,
            BankTransaction::class,
            // Parents
            Receipt::class,
            Document::class,
            Invoice::class,
            Contract::class,
            BankStatement::class,
            Voucher::class,
            Warranty::class,
            ReturnPolicy::class,
            // Junction table last
            ExtractableEntity::class,
        ];

        $grouped = [];
        foreach ($entities as $entity) {
            $type = $entity['type'];
            if (! isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $entity['id'];
        }

        foreach ($orderedTypes as $type) {
            if (! isset($grouped[$type]) || $grouped[$type] === []) {
                continue;
            }

            try {
                // Use withTrashed to find soft-deleted records and forceDelete them
                $type::withTrashed()
                    ->whereIn('id', $grouped[$type])
                    ->where('deleted_reason', DeletedReason::Reprocess)
                    ->forceDelete();

                Log::debug('[FileEntityCleanup] Hard-deleted entities', [
                    'type' => $type,
                    'count' => count($grouped[$type]),
                ]);
            } catch (\Exception $e) {
                Log::warning('[FileEntityCleanup] Failed to hard-delete entities', [
                    'type' => $type,
                    'ids' => $grouped[$type],
                    'error' => $e->getMessage(),
                ]);
                // Continue with other types - don't fail the whole operation
            }
        }

        Log::info('[FileEntityCleanup] Hard delete completed');
    }
}
