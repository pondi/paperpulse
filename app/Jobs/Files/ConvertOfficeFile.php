<?php

namespace App\Jobs\Files;

use App\Jobs\BaseJob;
use App\Models\FileConversion;
use App\Services\Documents\ConversionService;
use App\Services\Jobs\JobMetadataPersistence;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ConvertOfficeFile extends BaseJob
{
    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(public int $conversionId, ?string $chainId = null)
    {
        parent::__construct($chainId ?? (string) Str::uuid());
        $this->onConnection('database')->onQueue('conversions');
    }

    protected function handleJob(): void
    {
        $record = FileConversion::findOrFail($this->conversionId);
        app(ConversionService::class)->convert($record);
        if (! $record->refresh()->isCompleted()) {
            throw new RuntimeException('Extraction cannot resume before conversion completes.');
        }
        if ($metadata = JobMetadataPersistence::retrieve($this->jobID)) {
            $metadata['s3ArchivePath'] = $record->output_s3_path;
            $metadata['originalExtension'] ??= $metadata['fileExtension'];
            $metadata['fileExtension'] = 'pdf';
            $metadata['originalLocalPath'] ??= $metadata['filePath'] ?? null;
            $metadata['filePath'] = null;
            JobMetadataPersistence::store($this->jobID, $metadata);
        }
    }

    public function failed(Throwable $exception): void
    {
        $record = FileConversion::find($this->conversionId);
        if ($record && ! $record->isCompleted()) {
            $record->markAsFailed('Office conversion exhausted its retries.');
        }
        parent::failed($exception);
    }
}
