<?php

namespace App\Services;

use App\Models\File as SourceFile;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class DocumentArchiveService
{
    public function __construct(private StorageService $storage) {}

    public function write(int $userId, array $ids, string $directory, ?callable $progress = null): string
    {
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/archive.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP archive.');
        }
        $processed = 0;
        try {
            foreach (SourceFile::withoutGlobalScope('user')->where('user_id', $userId)->whereIn('id', $ids)->lazyById(100) as $file) {
                $extension = $file->fileExtension ?: 'txt';
                $source = StoragePathBuilder::variantPath($file, 'original');
                $stream = $source === null ? null : $this->storage->readStream($source);
                if ($stream !== null) {
                    $staged = $directory.'/'.$file->id;
                    $output = fopen($staged, 'wb');
                    try {
                        if (stream_copy_to_stream($stream, $output) === false) {
                            throw new RuntimeException('Could not stage a document for export.');
                        }
                    } finally {
                        fclose($stream);
                        fclose($output);
                    }
                    $name = preg_replace('/[^\pL\pN _.-]/u', '_', $file->fileName);
                    $base = str_ends_with(strtolower($name), '.'.strtolower($extension)) ? $name : $name.'.'.$extension;
                    $name = $base;
                    $counter = 1;
                    while ($zip->locateName($name) !== false) {
                        $name = pathinfo($base, PATHINFO_FILENAME).'_'.$counter++.'.'.pathinfo($base, PATHINFO_EXTENSION);
                    }
                    if (! $zip->addFile($staged, $name)) {
                        throw new RuntimeException('Could not add a document to the ZIP archive.');
                    }
                }
                $processed++;
                if ($progress !== null) {
                    $progress($processed);
                }
            }
            $empty = $zip->numFiles === 0;
            if (! $zip->close()) {
                throw new RuntimeException('Could not finish the ZIP archive.');
            }
            if ($empty) {
                File::put($path, "PK\x05\x06".str_repeat("\0", 18));
            }
        } catch (\Throwable $exception) {
            unset($zip);
            throw $exception;
        }

        return $path;
    }
}
