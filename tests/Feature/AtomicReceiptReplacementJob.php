<?php

namespace Tests\Feature;

use App\Jobs\Receipts\ProcessReceipt;
use App\Models\Receipt;
use RuntimeException;

class AtomicReceiptReplacementJob extends ProcessReceipt
{
    public bool $shouldFail = false;

    protected function handleJob(): void
    {
        $metadata = $this->getMetadata();
        Receipt::factory()->create(['user_id' => $metadata['userId'], 'file_id' => $metadata['fileId'], 'total_amount' => 200]);
        if ($this->shouldFail) {
            throw new RuntimeException('Extraction failed');
        }
    }
}
