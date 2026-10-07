<script setup>
import { computed } from 'vue';
import DocumentPreview from '@/Components/Domain/DocumentPreview.vue';
const props = defineProps({
    file: { type: Object, default: null },
    altText: { type: String, default: 'Receipt' },
    errorMessage: String,
    noImageMessage: { type: String, default: 'No preview available' },
    showPdfButton: Boolean,
    pdfButtonPosition: String,
});
const preview = computed(() => ({ ...props.file, name: props.altText, previewUrl: props.file?.previewUrl || props.file?.url }));
</script>
<template>
    <div class="min-w-0">
        <DocumentPreview v-if="file" :file="preview" />
        <p v-else class="p-6 text-center text-sm text-zinc-500">{{ noImageMessage }}</p>
    </div>
</template>
