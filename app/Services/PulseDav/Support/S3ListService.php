<?php

namespace App\Services\PulseDav\Support;

use App\Models\PulseDavFile;
use App\Services\PulseDav\Import\S3PathResolver;
use Aws\S3\S3Client;
use Generator;
use UnexpectedValueException;

class S3ListService
{
    public static function pages(S3Client $s3Client, string $bucket, string $prefix, int $userId, ?string $delimiter = null): Generator
    {
        $parameters = ['Bucket' => $bucket, 'Prefix' => $prefix];
        if ($delimiter !== null) {
            $parameters['Delimiter'] = $delimiter;
        }

        do {
            $page = $s3Client->listObjectsV2($parameters);
            foreach (array_merge($page['Contents'] ?? [], $page['CommonPrefixes'] ?? []) as $object) {
                $path = $object['Key'] ?? $object['Prefix'];
                if (! str_starts_with($path, $prefix)) {
                    throw new UnexpectedValueException('Scanner listing returned a path outside the requested prefix.');
                }
                if ($path !== $prefix) {
                    S3PathResolver::validateOwnedPath($path, $userId);
                }
            }

            yield $page;

            if (! ($page['IsTruncated'] ?? false)) {
                return;
            }
            $token = $page['NextContinuationToken'] ?? null;
            if (! is_string($token) || $token === '' || $token === ($parameters['ContinuationToken'] ?? null)) {
                throw new UnexpectedValueException('Scanner listing did not provide a valid continuation token.');
            }
            $parameters['ContinuationToken'] = $token;
        } while (true);
    }

    public static function files(S3Client $s3Client, string $bucket, string $prefix, int $userId, bool $withFolders = false): Generator
    {
        foreach (self::pages($s3Client, $bucket, $prefix, $userId) as $page) {
            $folders = [];
            foreach ($page['Contents'] ?? [] as $object) {
                $key = $object['Key'];
                if ($key === $prefix) {
                    continue;
                }
                $isFolder = str_ends_with($key, '/');
                if ($withFolders) {
                    $parts = explode('/', substr($key, strlen($prefix)));
                    for ($i = 0; $i < count($parts) - 1; $i++) {
                        $folderPath = implode('/', array_slice($parts, 0, $i + 1));
                        if (! isset($folders[$folderPath])) {
                            $folders[$folderPath] = true;
                            yield $prefix.$folderPath.'/' => array_merge([
                                's3_path' => $prefix.$folderPath.'/',
                                'filename' => $parts[$i],
                                'is_folder' => true,
                                'size' => 0,
                                'uploaded_at' => null,
                            ], PulseDavFile::extractFolderInfo($prefix.$folderPath.'/', $prefix));
                        }
                    }
                }
                if ($isFolder) {
                    continue;
                }
                $file = [
                    's3_path' => $key,
                    'filename' => basename($key),
                    'size' => $object['Size'],
                    'uploaded_at' => $object['LastModified'],
                ];
                if ($withFolders) {
                    $folderPath = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : null;
                    $file = array_merge($file, [
                        'is_folder' => false,
                        'folder_path' => $folderPath,
                        'parent_folder' => $folderPath ? basename($folderPath) : null,
                        'depth' => count($parts) - 1,
                    ]);
                }
                yield $key => $file;
            }
        }
    }
}
