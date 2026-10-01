<?php

namespace App\Contracts;

interface DocumentConverter
{
    /** @return list<string> */
    public function supportedMimeTypes(): array;

    public function convert(string $source, string $destination, string $mimeType): void;
}
