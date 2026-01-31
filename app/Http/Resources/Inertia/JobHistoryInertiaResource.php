<?php

namespace App\Http\Resources\Inertia;

use App\Services\Jobs\JobParentStatusCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class JobHistoryInertiaResource extends JsonResource
{
    protected bool $isChild = false;

    public static function asChild($resource): self
    {
        $instance = new self($resource);
        $instance->isChild = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $effectiveStatus = $this->isChild ? $this->status : JobParentStatusCalculator::calculate($this->resource);

        $data = [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $effectiveStatus,
            'queue' => $this->queue,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'progress' => (int) $this->progress,
            'attempt' => $this->attempt,
            'exception' => $this->exception,
            'duration' => $this->calculateDuration(),
            'order' => $this->order_in_chain,
        ];

        if (! $this->isChild) {
            $data['type'] = $this->determineJobType();
            $data['file_info'] = $this->resolveFileInfo();
            $data['steps'] = $this->buildSteps();
        }

        return $data;
    }

    private function calculateDuration(): ?int
    {
        if ($this->started_at && $this->finished_at) {
            return abs($this->finished_at->diffInSeconds($this->started_at));
        }

        return null;
    }

    private function resolveFileInfo(): ?array
    {
        if ($this->file_name) {
            return [
                'name' => $this->file_name,
                'extension' => $this->metadata['fileExtension'] ?? pathinfo($this->file_name, PATHINFO_EXTENSION),
                'size' => $this->metadata['fileSize'] ?? null,
                'job_name' => $this->metadata['jobName'] ?? $this->name,
            ];
        }

        $metadata = Cache::get("job.{$this->uuid}.fileMetaData");
        if ($metadata) {
            return [
                'name' => $metadata['fileName'] ?? 'Unknown File',
                'extension' => $metadata['fileExtension'] ?? null,
                'size' => $metadata['fileSize'] ?? null,
                'job_name' => $metadata['jobName'] ?? 'Processing Job',
            ];
        }

        return null;
    }

    private function determineJobType(): string
    {
        $tasks = $this->tasks;

        if (! $tasks || $tasks->count() === 0) {
            return 'unknown';
        }

        if ($tasks->contains('name', 'Process Receipt')) {
            return 'receipt';
        }

        if ($tasks->contains('name', 'Process Document')) {
            return 'document';
        }

        return 'unknown';
    }

    private function buildSteps(): array
    {
        $children = $this->tasks()->orderBy('order_in_chain')->get();

        $uniqueSteps = [];
        foreach ($children->groupBy('name') as $attempts) {
            $latestAttempt = $attempts->sortByDesc('created_at')->first();
            $uniqueSteps[] = self::asChild($latestAttempt)->toArray(request());
        }

        return collect($uniqueSteps)->sortBy('order')->values()->all();
    }
}
