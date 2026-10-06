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
    usage_budget_exceeded: ['The processing usage limit was reached.', 'Processing resumes when its usage allowance is available.'],
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
        <p>{{ details[0] }}</p>
        <p>{{ details[1] }}</p>
        <p>{{ processing?.stage ? `Failed stage: ${processing.stage}` : 'Failure stage not recorded' }}</p>
        <p>{{ failure.timestamp ? `Failed at ${formatDateTime(failure.timestamp)}` : 'Failure time not recorded' }}</p>
        <Link :href="route('files.index', { file_id: fileId })" class="font-medium underline">Processing details for file #{{ fileId }}</Link>
    </div>
</template>
