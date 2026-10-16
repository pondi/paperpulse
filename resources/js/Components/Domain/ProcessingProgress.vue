<script setup>
import { useDateFormatter } from '@/Composables/useDateFormatter';

defineProps({ processing: { type: Object, required: true } });
const { formatDateTime } = useDateFormatter();
const states = { pending: 'Queued', queued: 'Queued', waiting: 'Waiting for processing allowance', processing: 'Active', retrying: 'Waiting to retry', completed: 'Ready', failed: 'Failed', needs_review: 'Needs review' };
</script>

<template>
    <div class="flex flex-col gap-2 text-xs text-zinc-600 dark:text-zinc-400" aria-live="polite">
        <p class="font-medium">{{ states[processing.state] || processing.state }}<span v-if="processing.stage"> · Stage: {{ processing.stage }}</span></p>
        <p v-if="processing.state === 'waiting' && processing.resume_at">Processing will resume automatically after {{ formatDateTime(processing.resume_at) }}.</p>
        <progress v-if="['processing', 'retrying'].includes(processing.state)" :value="processing.progress" max="100" aria-label="Current processing stage progress" class="w-full" />
        <p v-if="['processing', 'retrying'].includes(processing.state) && processing.elapsed_seconds > 600">This is taking longer than usual. Check the processing record for retries or a failure before starting another upload.</p>
        <details v-if="processing.queued_at || processing.started_at || processing.finished_at || processing.attempt > 1" class="rounded-md border border-zinc-200 p-2 dark:border-zinc-700">
            <summary class="cursor-pointer font-medium focus:outline-none focus:ring-2 focus:ring-amber-500">Processing timeline</summary>
            <div class="mt-2 flex flex-col gap-1">
                <p v-if="processing.queued_at">Queued {{ formatDateTime(processing.queued_at) }}</p>
                <p v-if="processing.started_at">Started {{ formatDateTime(processing.started_at) }}<span v-if="processing.elapsed_seconds != null"> · {{ Math.floor(processing.elapsed_seconds / 60) }} min {{ processing.elapsed_seconds % 60 }} sec elapsed</span></p>
                <p v-if="processing.finished_at">Finished {{ formatDateTime(processing.finished_at) }}</p>
                <p v-if="processing.attempt > 1">Attempt {{ processing.attempt }}</p>
            </div>
        </details>
    </div>
</template>
