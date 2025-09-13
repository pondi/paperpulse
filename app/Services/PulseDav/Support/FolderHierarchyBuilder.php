<?php

namespace App\Services\PulseDav\Support;

class FolderHierarchyBuilder
{
    public static function build(array $items): array
    {
        $hierarchy = [];
        $folderMap = [];

        foreach ($items as $item) {
            if (!empty($item['is_folder'])) {
                $folderMap[$item['folder_path']] = [
                    'path' => $item['folder_path'],
                    'name' => $item['filename'],
                    'is_folder' => true,
                    'children' => [],
                    'files' => [],
                    'metadata' => $item,
                ];
            }
        }

        foreach ($folderMap as $path => $folder) {
            $parentPath = $folder['metadata']['parent_folder'];
            if ($parentPath === null) {
                $hierarchy[] = &$folderMap[$path];
            } elseif (isset($folderMap[$parentPath])) {
                $folderMap[$parentPath]['children'][] = &$folderMap[$path];
            }
        }

        foreach ($items as $item) {
            if (empty($item['is_folder'])) {
                if ($item['folder_path'] === null) {
                    $hierarchy[] = $item;
                } elseif (isset($folderMap[$item['folder_path']])) {
                    $folderMap[$item['folder_path']]['files'][] = $item;
                }
            }
        }

        return $hierarchy;
    }
}

