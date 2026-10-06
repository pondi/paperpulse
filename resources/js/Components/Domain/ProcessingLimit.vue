<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ review: { type: Object, required: true } });
</script>

<template>
    <div class="flex flex-col gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
        <h3 class="font-semibold">Processing blocked by a limit</h3>
        <p>No pages were extracted from this file. This is a processing limit, not an uncertain extraction.</p>
        <p v-if="review.page_limit">Split the original into PDFs of {{ review.page_limit }} pages or fewer, then upload each part.</p>
        <p v-if="review.text_limit_bytes">Split the original into text files of {{ Math.floor(review.text_limit_bytes / 1000) }} KB or fewer, then upload each part.</p>
        <p>Retrying this unchanged file will not remove the limit. The original remains available in the workspace.</p>
        <Link :href="route('documents.upload')" class="font-medium underline">Upload split files</Link>
    </div>
</template>
