<script setup>
import { computed, ref, watch } from 'vue';
import { DocumentIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ file: { type: Object, required: true }, framed: Boolean });
const attempt = ref(0);
const retry = ref(0);
const loading = ref(false);
const fullPdf = ref(false);
const pdf = computed(() => props.file.pdfUrl || (props.file.extension?.toLowerCase() === 'pdf' ? props.file.viewUrl || props.file.url : null));
const originalImage = computed(() => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(props.file.extension?.toLowerCase()) ? props.file.viewUrl || props.file.url : null);
const sources = computed(() => {
    const images = [...new Set([props.file.previewUrl, originalImage.value].filter(Boolean))].map(url => ({ kind: 'image', url }));
    return [...images, ...(pdf.value ? [{ kind: 'pdf', url: pdf.value }] : [])];
});
const source = computed(() => fullPdf.value ? { kind: 'pdf', url: pdf.value } : sources.value[attempt.value]);
const canSwitchPdf = computed(() => pdf.value && sources.value.some(item => item.kind === 'image'));
const imagePage = computed(() => source.value?.kind === 'image' && pdf.value && source.value.url === props.file.previewUrl);
function reset() {
    attempt.value = 0;
    fullPdf.value = false;
    retry.value++;
}
function failed() {
    if (fullPdf.value) {
        fullPdf.value = false;
        attempt.value = 0;
    } else {
        attempt.value++;
    }
}
function pdfLoaded(event) {
    try {
        const document = event.target.contentDocument;
        if (document && (document.contentType === 'application/json' || (document.contentType === 'text/html' && !document.querySelector('embed[type="application/pdf"]')))) {
            failed();
            return;
        }
    } catch {
        // Native PDF viewers can isolate their document from the embedding page.
    }
    loading.value = false;
}
watch(() => [props.file.id, props.file.previewUrl, originalImage.value, pdf.value], reset, { immediate: true });
watch([source, retry], () => { loading.value = Boolean(source.value); }, { immediate: true });
</script>

<template>
    <section aria-label="Document preview" :class="{ 'preview-framed': framed }" class="relative flex min-h-[280px] flex-col overflow-hidden rounded-none border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-950" :aria-busy="loading">
        <div v-if="!framed" class="flex shrink-0 items-center justify-between gap-3 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Document preview</h2>
            <a :href="file.viewUrl || file.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-zinc-500 hover:text-orange-700 dark:hover:text-orange-400">Open original<ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /></a>
        </div>
        <div class="relative flex min-h-0 flex-1 flex-col">
            <div v-if="source?.kind === 'image'" class="flex min-h-0 flex-1 items-start justify-center overflow-auto p-4">
                <img :key="source.url + retry" :src="source.url" :alt="file.name || 'Document'" class="max-h-[480px] max-w-full bg-white object-contain" @load="loading = false" @error="failed" />
            </div>
            <iframe v-else-if="source?.kind === 'pdf'" :key="source.url + retry" :src="`${source.url}#${framed ? 'toolbar=0&' : ''}navpanes=0&view=Fit`" :title="'Preview of ' + (file.name || 'document')" class="min-h-[380px] w-full flex-1 border-0" @load="pdfLoaded" @error="failed" />
            <div v-else class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 overflow-auto p-5 text-center">
                <DocumentIcon class="h-8 w-8 shrink-0 text-zinc-400" aria-hidden="true" />
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ file.preview_pending ? 'Preparing preview…' : sources.length ? 'Preview could not load' : 'Preview unavailable' }}</h3>
                <p class="max-w-xs text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ file.preview_pending ? 'The preview will appear when it is ready.' : 'Use Open original to view the file, or try loading the preview again.' }}</p>
                <button v-if="sources.length" type="button" class="workspace-button" @click="reset">Retry preview</button>
            </div>
            <p v-if="loading" role="status" class="pointer-events-none absolute bottom-3 left-3 rounded bg-white/95 px-3 py-2 text-xs text-zinc-600 shadow-sm dark:bg-zinc-900/95 dark:text-zinc-300">Loading preview…</p>
            <span v-else-if="imagePage" class="pointer-events-none absolute bottom-3 left-3 rounded bg-white/95 px-2 py-1 text-xs text-zinc-500 dark:bg-zinc-900/95 dark:text-zinc-400">Page 1 preview</span>
            <button v-if="source && canSwitchPdf" type="button" class="workspace-button absolute right-2 top-2 !min-h-0 !px-2 !py-1.5 !text-xs shadow-sm" @click="fullPdf = source.kind !== 'pdf'; attempt = 0">{{ source.kind === 'pdf' ? 'Image preview' : 'View full PDF' }}</button>
        </div>
    </section>
</template>

<style scoped>
.preview-framed { @apply min-h-0; }
.preview-framed img { @apply h-full max-h-full w-full object-contain; }
.preview-framed iframe { @apply min-h-0; }
</style>
