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
    extraction_validation_failed: ['The extracted information could not be validated.', 'Check that the original is readable, then retry extraction.'],
    api_timeout: ['The extraction service timed out.', 'Retry processing.'],
    api_rate_limited: ['The extraction service is temporarily at capacity.', 'Wait a few minutes, then retry processing.'],
    api_upload_failed: ['The extraction service could not receive the source.', 'Retry processing.'],
    api_error: ['The extraction service could not complete the request.', 'Retry processing. If it fails again, contact support.'],
    provider_unavailable: ['The extraction service is unavailable.', 'Wait a few minutes, then retry processing.'],
};
const details = computed(() => causes[props.failure.category] || ['The failure cause was not recorded.', 'Retry processing. If it fails again, contact support with the file number below.']);
</script>

<template>
    <div class="flex flex-col gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
        <h3 class="font-semibold">Processing stopped</h3>
        <p>{{ details[0] }}</p>
        <p>{{ details[1] }}</p>
        <p>{{ processing?.stage ? `Failed stage: ${processing.stage}` : 'Failure stage not recorded' }}</p>
        <p>{{ failure.timestamp ? `Failed at ${formatDateTime(failure.timestamp)}` : 'Failure time not recorded' }}</p>
        <Link :href="route('files.index', { file_id: fileId })" class="font-medium underline">Recovery actions for file #{{ fileId }}</Link>
    </div>
</template>
