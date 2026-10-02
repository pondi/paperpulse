<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class FileExtractionCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $userId, public int $fileId, public string $generation) {}
}
