<script setup>
import { useDateFormatter } from '@/Composables/useDateFormatter';

defineProps({ processing: { type: Object, required: true } });
const { formatDateTime } = useDateFormatter();
const states = { pending: 'Queued', queued: 'Queued', processing: 'Active', retrying: 'Waiting to retry', completed: 'Completed', failed: 'Failed', needs_review: 'Needs review' };
</script>

<template>
    <div class="flex flex-col gap-2 text-xs text-zinc-600 dark:text-zinc-400" aria-live="polite">
        <p class="font-medium">{{ states[processing.state] || processing.state }}<span v-if="processing.stage"> · Stage: {{ processing.stage }}</span></p>
        <p v-if="processing.queued_at">Queued {{ formatDateTime(processing.queued_at) }}</p>
        <p v-if="processing.started_at">Started {{ formatDateTime(processing.started_at) }}<span v-if="processing.elapsed_seconds != null"> · {{ Math.floor(processing.elapsed_seconds / 60) }} min {{ processing.elapsed_seconds % 60 }} sec elapsed</span></p>
        <p v-if="processing.finished_at">Finished {{ formatDateTime(processing.finished_at) }}</p>
        <p v-if="processing.attempt > 1">Attempt {{ processing.attempt }}</p>
        <progress v-if="['processing', 'retrying'].includes(processing.state)" :value="processing.progress" max="100" aria-label="Current processing stage progress" class="w-full" />
        <p v-if="['processing', 'retrying'].includes(processing.state) && processing.elapsed_seconds > 600">This is taking longer than usual. Check the processing record for retries or a failure before starting another upload.</p>
    </div>
</template>
