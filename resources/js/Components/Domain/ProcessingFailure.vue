<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ failure: { type: Object, default: () => ({}) }, processing: { type: Object, default: null }, fileId: { type: Number, required: true } });
const { formatDateTime } = useDateFormatter();
const causes = {
    unsupported_format: ['The source format is not supported.', 'Upload a supported PDF, image, office document or text file.'],
    file_too_large: ['The source exceeds the processing size limit.', 'Split or reduce the file and upload the smaller files.'],
    file_missing: ['The stored source file is unavailable.', 'Upload the original file again.'],
    usage_budget_exceeded: ['The processing usage limit was reached.', 'This file exceeded its processing allowance. Contact support before retrying.'],
    extraction_validation_failed: ['The extracted information could not be validated.', 'Check that the original is readable. Contact support if it persists.'],
    api_timeout: ['The extraction service timed out.', 'Temporary failures are retried automatically.'],
    api_rate_limited: ['The extraction service is temporarily at capacity.', 'Temporary failures are retried automatically.'],
    api_upload_failed: ['The extraction service could not receive the source.', 'Temporary failures are retried automatically.'],
    api_error: ['The extraction service could not complete the request.', 'The processing failure has been recorded. Contact support if it persists.'],
    provider_unavailable: ['The extraction service is unavailable.', 'Temporary failures are retried automatically.'],
};
const details = computed(() => causes[props.failure.category] || ['The failure cause was not recorded.', 'Contact support with the file number below.']);
</script>

<template>
    <div class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
        <h3 class="font-semibold">Processing stopped</h3>
        <p><span class="font-medium">What happened: </span>{{ details[0] }}</p>
        <p><span class="font-medium">Next step: </span>{{ details[1] }}</p>
        <details class="rounded-md border border-amber-200 p-3 dark:border-amber-800">
            <summary dusk="failure-diagnostics" class="cursor-pointer font-medium focus:outline-none focus:ring-2 focus:ring-amber-500">Failure diagnostics</summary>
            <dl class="mt-3 flex flex-col gap-2 text-xs">
                <div><dt class="font-semibold">Failed stage</dt><dd>{{ processing?.stage || 'Not recorded' }}</dd></div>
                <div><dt class="font-semibold">Failed at</dt><dd>{{ failure.timestamp ? formatDateTime(failure.timestamp) : 'Not recorded' }}</dd></div>
                <div><dt class="font-semibold">File number</dt><dd>{{ fileId }}</dd></div>
            </dl>
            <Link :href="route('files.index', { file_id: fileId })" class="mt-2 inline-block font-medium underline">Open processing record</Link>
        </details>
    </div>
</template>
