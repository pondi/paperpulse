<?php

namespace App\Services\PulseDav\Support;

use Illuminate\Support\Facades\Log;

class S3ListService
{
    public static function listUserFiles($s3Client, string $bucket, string $prefix): array
    {
        try {
            $objects = $s3Client->listObjectsV2(['Bucket' => $bucket, 'Prefix' => $prefix]);
            $files = [];
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    if (substr($object['Key'], -1) === '/') {
                        continue;
                    }
                    $files[] = [
                        's3_path' => $object['Key'],
                        'filename' => basename($object['Key']),
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            return $files;
        } catch (\Exception $e) {
            Log::error('Failed to list S3 files', ['prefix' => $prefix, 'error' => $e->getMessage()]);

            return [];
        }
    }

    public static function listUserFilesWithFolders($s3Client, string $bucket, string $prefix): array
    {
        try {
            $objects = $s3Client->listObjectsV2(['Bucket' => $bucket, 'Prefix' => $prefix]);
            $items = [];
            $folders = [];

            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $object) {
                    $key = $object['Key'];
                    $relativePath = str_replace($prefix, '', $key);
                    if ($relativePath === '') {
                        continue;
                    }

                    $parts = explode('/', $relativePath);
                    $currentPath = '';
                    for ($i = 0; $i < count($parts) - 1; $i++) {
                        $currentPath .= ($i > 0 ? '/' : '').$parts[$i];
                        $parentPath = $i > 0 ? implode('/', array_slice($parts, 0, $i)) : null;
                        if (! isset($folders[$currentPath])) {
                            $folders[$currentPath] = [
                                's3_path' => $prefix.$currentPath.'/',
                                'filename' => $parts[$i],
                                'folder_path' => $currentPath,
                                'parent_folder' => $parentPath,
                                'depth' => $i,
                                'is_folder' => true,
                                'size' => 0,
                                'uploaded_at' => null,
                            ];
                        }
                    }

                    $folderPath = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : null;
                    $items[] = [
                        's3_path' => $object['Key'],
                        'filename' => basename($object['Key']),
                        'folder_path' => $folderPath,
                        'parent_folder' => $folderPath ? basename($folderPath) : null,
                        'depth' => count($parts) - 1,
                        'is_folder' => false,
                        'size' => $object['Size'],
                        'uploaded_at' => $object['LastModified'],
                    ];
                }
            }

            return array_merge(array_values($folders), $items);
        } catch (\Exception $e) {
            Log::error('Failed to list S3 files with folders', ['prefix' => $prefix, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
