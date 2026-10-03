<?php

namespace App\Jobs;

use App\Models\ArchiveExport;
use App\Notifications\BulkOperationCompleted;
use App\Services\DocumentArchiveService;
use App\Services\MonetarySummaryService;
use App\Services\ReceiptExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateArchiveExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $exportId)
    {
        $this->onConnection('database')->onQueue('exports');
    }

    public function handle(ReceiptExportService $receipts, DocumentArchiveService $documents): void
    {
        $export = ArchiveExport::with('user')->findOrFail($this->exportId);
        if ($export->status === 'completed' || $export->expires_at->isPast()) {
            return;
        }
        $directory = Storage::disk('local')->path('private/exports/'.$export->user_id.'/'.$export->id);
        $work = $directory.'/work';
        File::ensureDirectoryExists($work, 0700);
        $export->update(['status' => 'processing', 'processed' => 0, 'error' => null]);
        try {
            $progress = fn (int $count) => $export->update(['processed' => $count]);
            if ($export->format === 'zip') {
                $path = $documents->write($export->user_id, $export->filters['ids'], $work, $progress);
            } else {
                $query = $receipts->query($export->user_id, $export->filters);
                $summary = app(MonetarySummaryService::class)->aggregate((clone $query)->withoutEagerLoads()->lazy(200), ['total' => 'total_amount'], 'receipt_date', $export->user->preference('currency', 'NOK'));
                $manifest = $work.'/parts.txt';
                $index = 0;
                $count = 0;
                $query->chunk(config('exports.chunk_size'), function ($chunk) use ($receipts, $export, $work, $manifest, $progress, $summary, &$index, &$count): void {
                    $part = $work.'/part-'.$index++.'.pdf';
                    $receipts->pdf($chunk, $export->user, $export->filters, $summary)->save($part);
                    File::append($manifest, '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $part).'"'.PHP_EOL);
                    $count += $chunk->count();
                    $progress($count);
                });
                if ($index === 0) {
                    throw new RuntimeException('No receipts remain available for export.');
                }
                $path = $work.'/archive.pdf';
                $result = Process::timeout(3300)->run([config('exports.pdf_binary'), '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite', '-sOutputFile='.$path, '@'.$manifest]);
                if (! $result->successful() || ! is_file($path) || filesize($path) === 0) {
                    throw new RuntimeException('Could not finish the PDF export.');
                }
            }
            $filename = 'private/exports/'.$export->user_id.'/'.$export->id.'/archive.'.$export->format;
            if (! rename($path, Storage::disk('local')->path($filename))) {
                throw new RuntimeException('Could not retain the generated export.');
            }
            $export->update(['status' => 'completed', 'path' => $filename]);
            $export->user->notify((new BulkOperationCompleted('export', $export->processed, ['export_id' => $export->id]))->onConnection('database'));
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error' => 'Export generation failed. Please try again.']);
            throw $exception;
        } finally {
            File::deleteDirectory($work);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = ArchiveExport::find($this->exportId);
        if ($export !== null && $export->status !== 'completed') {
            $export->update(['status' => 'failed', 'error' => 'Export generation failed. Please try again.']);
            Storage::disk('local')->deleteDirectory('private/exports/'.$export->user_id.'/'.$export->id);
        }
    }
}
