<?php

namespace App\Exceptions;

use App\Models\File;
use Exception;

class DuplicateFileException extends Exception
{
    protected ?File $existingFile = null;

    protected ?string $fileHash = null;

    public function __construct(File $existingFile, string $fileHash, string $message = 'Duplicate file detected')
    {
        parent::__construct($message);
        $this->existingFile = $existingFile;
        $this->fileHash = $fileHash;
    }

    public function getExistingFile(): ?File
    {
        return $this->existingFile;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    /**
     * Get data array for exception response
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'file_hash' => $this->fileHash,
            'existing_file' => [
                'id' => $this->existingFile->id,
                'guid' => $this->existingFile->guid,
                'fileName' => $this->existingFile->fileName,
                'fileType' => $this->existingFile->file_type,
                'uploaded_at' => $this->existingFile->uploaded_at?->toISOString(),
            ],
        ];
    }
}
