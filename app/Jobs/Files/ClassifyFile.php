<?php

namespace App\Jobs\Files;

use App\Jobs\BaseJob;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Services\AI\TypeClassification\AutomaticTypeResolver;
use App\Services\Jobs\JobChainPlan;
use RuntimeException;

class ClassifyFile extends BaseJob
{
    public int $tries = 3;

    public int $timeout = 180;

    public $backoff = [30, 120, 300];

    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Detect File Type';
    }

    protected function handleJob(): void
    {
        $metadata = $this->getMetadata();
        $file = File::withoutGlobalScope('user')->where('user_id', $metadata['userId'])->findOrFail($metadata['fileId']);
        $text = $metadata['extractedText'] ?? '';
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Automatic file classification requires extracted source text.');
        }
        $classification = app(AutomaticTypeResolver::class)->fromText($file, $text, $this->jobID);
        $type = $classification->type === 'receipt' ? 'receipt' : 'document';
        $file->update(['file_type' => $type]);
        $metadata['fileType'] = $type;
        $metadata['automaticClassification'] = $classification->toArray();
        $steps = $type === 'receipt' ? [new ProcessReceipt($this->jobID), new MatchMerchant($this->jobID)]
            : [new ProcessDocument($this->jobID), new AnalyzeDocument($this->jobID)];
        $metadata['classificationSteps'] = array_map(fn (BaseJob $step): array => ['uuid' => $step->uuid, 'class' => $step::class], $steps);
        $this->storeMetadata($metadata);
        $this->prependProcessingSteps($steps);
        $this->updateProgress(100);
    }

    /** @param list<BaseJob> $steps */
    private function prependProcessingSteps(array $steps): void
    {
        foreach (array_reverse($steps) as $step) {
            $step->onQueue($this->queue ?? 'documents');
            JobChainPlan::prependStep($this->jobID, $this->uuid, $step);
            $this->prependToChain($step);
        }
    }

    protected function restoreCompletedDelivery(): void
    {
        $steps = [];
        foreach ($this->getMetadata()['classificationSteps'] ?? [] as $stored) {
            $class = $stored['class'];
            if (! in_array($class, [ProcessReceipt::class, MatchMerchant::class, ProcessDocument::class, AnalyzeDocument::class], true)) {
                throw new RuntimeException('Invalid automatic processing step.');
            }
            if (collect($this->chained)->contains(fn (string $serialized): bool => unserialize($serialized, ['allowed_classes' => [$class]]) instanceof $class)) {
                continue;
            }
            $step = new $class($this->jobID);
            $step->uuid = $stored['uuid'];
            $steps[] = $step;
        }
        $this->prependProcessingSteps($steps);
    }
}
