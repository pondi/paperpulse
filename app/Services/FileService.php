<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;
use App\Models\File;
use App\Jobs\ProcessReceipt;
use App\Jobs\MatchMerchant;
use App\Jobs\ProcessFile;
use App\Jobs\DeleteWorkingFiles;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FileService
{

    public function processUpload($incomingFile, $fileType = 'receipt')
    {
        $jobID    = (string) Str::uuid();

        $fileGUID = (string) Str::uuid();

        $fileMetaData = $this->createFileModel($incomingFile, $fileGUID);
        Cache::put("job.{$jobID}.fileMetaData", $fileMetaData, now()->addMinutes(5));

        Log::info('FileService - processUpload Complete - jobID: ' . $jobID . ' - fileMetaData:', $fileMetaData);

        Bus::chain([
            new ProcessFile($jobID),
            new ProcessReceipt($jobID),
            new MatchMerchant($jobID),
            new DeleteWorkingFiles($jobID),
            ])->dispatch();

        return true;
    }

    private function getFile(string $path)
    {
        try {
            return Storage::disk('local')->get($path);
        } catch (\Exception $e) {
            Log::error('Error getting file: ' . $e->getMessage());
            throw $e;
        }
    }

    private function storeWorkingFile($incomingFile, string $fileGUID) : string
    {
        try {
            $fileName = $fileGUID . '.' . $incomingFile->getClientOriginalExtension();
            $storedFile = $incomingFile->storeAs('uploads', $fileName, 'local');
            Log::info('FileService - storeWorkingFile Complete - storedFile: ' . $fileName);
            return Storage::disk('local')->path($storedFile);
        } catch (\Exception $e) {
            Log::error('Error storing file: ' . $e->getMessage());
            throw $e;
        }
    }

    public function convertPdfToImage(string $storedFilePath, string $fileGUID)
    {
        try {
            $spatiePDF = new Pdf($storedFilePath);
            $spatiePDF->setPage(1)->saveImage(storage_path('app/uploads/' . $fileGUID . '.jpg'));
            Log::info('FileService - convertPdfToImage Complete - storedFilePath: ' . $storedFilePath . ' - guid: ' . $fileGUID);
            return $this->getFile('uploads/' . $fileGUID . '.jpg');
        } catch (\Exception $e) {
            Log::error('Error converting PDF to image: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createFileModel($incomingFile, $fileGUID) : array
    {
        $filePath = $this->storeWorkingFile($incomingFile, $fileGUID);
        $fileExtension = $incomingFile->getClientOriginalExtension();
        $fileName = $incomingFile->getClientOriginalName();
        $fileType = $incomingFile->getClientMimeType();
        $fileSize = $incomingFile->getSize();

        $fileModel = new File;
        $fileModel->fileName = $fileName;
        $fileModel->fileExtension = $fileExtension;
        $fileModel->fileType = $fileType;
        $fileModel->fileSize = $fileSize;
        $fileModel->guid = $fileGUID;
        $fileModel->uploaded_at = now();
        $fileModel->save();

        Log::info('FileService crated file in DB - fileModel:', $fileModel->toArray());

        // Return an array of the fileModel ID, fileGUID, and filePath
        return [
            'fileID' => $fileModel->id,
            'fileGUID' => $fileGUID,
            'filePath' => $filePath,
            'fileExtension' => $fileExtension,
        ];
    }

}
