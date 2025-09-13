<?php

namespace App\Services\PulseDav;

use App\Models\PulseDavFile;
use App\Models\PulseDavImportBatch;
use Illuminate\Support\Facades\Log;

class SelectionImportService
{
    public static function importSelected(\App\Models\User $user, array $selections, array $options = []): array
    {
        Log::info('[PulseDavSelection] Import started', [
            'user_id' => $user->id,
            'selections_count' => count($selections),
            'options' => $options,
        ]);

        $fileType = $options['file_type'] ?? 'receipt';
        $tagIds = $options['tag_ids'] ?? [];
        $notes = $options['notes'] ?? null;

        $batch = PulseDavImportBatch::create([
            'user_id' => $user->id,
            'imported_at' => now(),
            'file_count' => 0,
            'tag_ids' => $tagIds,
            'notes' => $notes,
        ]);

        $imported = 0;
        $skipped = 0;

        foreach ($selections as $selection) {
            $item = PulseDavFile::where('user_id', $user->id)
                ->where('s3_path', $selection['s3_path'])
                ->first();

            if (! $item) {
                $skipped++;
                continue;
            }

            if ($item->is_folder) {
                $files = PulseDavFile::where('user_id', $user->id)
                    ->where('folder_path', 'like', $item->folder_path.'%')
                    ->filesOnly()
                    ->whereIn('status', ['pending', 'failed'])
                    ->get();

                foreach ($files as $file) {
                    ImportService::importFile($file, $batch, $fileType, $tagIds);
                    $imported++;
                }
            } else {
                if ($item->isProcessable()) {
                    ImportService::importFile($item, $batch, $fileType, $tagIds);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        $batch->update(['file_count' => $imported]);

        Log::info('[PulseDavSelection] Import completed', [
            'batch_id' => $batch->id,
            'imported' => $imported,
            'skipped' => $skipped,
        ]);

        return ['batch_id' => $batch->id, 'imported' => $imported];
    }
}

