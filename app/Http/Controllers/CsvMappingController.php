<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCsvMappingRequest;
use App\Jobs\BankStatements\ProcessCsvImport;
use App\Models\BankStatement;
use App\Models\File;
use App\Services\BankStatements\CsvImportService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CsvMappingController extends Controller
{
    public function edit(File $file, CsvImportService $csv, StorageService $storage): Response
    {
        $this->checkFile($file);
        [$headers, $rows] = $this->readPreview($file, $csv, $storage);
        $error = null;
        $mapping = $file->meta['csv_mapping'] ?? null;
        if ($mapping === null) {
            try {
                $mapping = $csv->mapColumns($headers, $rows);
            } catch (ValidationException $exception) {
                $error = $exception->errors()['mapping'][0];
            }
        }

        return Inertia::render('BankStatements/CsvMapping', [
            'file' => ['id' => $file->id, 'name' => $file->original_filename ?? $file->fileName],
            'headers' => $headers,
            'rows' => $rows,
            'mapping' => $mapping,
            'mapping_error' => $error,
        ]);
    }

    public function update(UpdateCsvMappingRequest $request, File $file, CsvImportService $csv, StorageService $storage): RedirectResponse
    {
        $this->checkFile($file);
        abort_if(BankStatement::where('file_id', $file->id)->exists(), 409, 'This CSV has already been imported.');
        [$headers, $rows] = $this->readPreview($file, $csv, $storage);
        $mapping = $request->validated()['mapping'];
        $csv->validateMapping($headers, $rows, $mapping);
        $file->update(['meta' => array_merge($file->meta ?? [], ['csv_mapping' => $mapping]), 'status' => 'pending']);
        ProcessCsvImport::dispatch((string) Str::uuid(), $file->id);

        return redirect()->route('files.index')->with('success', 'CSV mapping saved. Import queued.');
    }

    private function checkFile(File $file): void
    {
        abort_unless($file->user_id === auth()->id(), 403);
        abort_unless(strtolower($file->fileExtension ?? '') === 'csv', 422, 'Choose a CSV file.');
    }

    /** @return array{0: list<string>, 1: list<list<string>>} */
    private function readPreview(File $file, CsvImportService $csv, StorageService $storage): array
    {
        $content = $storage->getFileByUserAndGuid($file->user_id, $file->guid, 'document', 'original', 'csv');
        abort_if($content === null, 404, 'CSV source is unavailable.');
        $delimiter = $csv->detectDelimiter($content);

        return [$csv->parseHeaders($content, $delimiter), $csv->sampleRows($content, 5, $delimiter)];
    }
}
