<script setup>
import { computed, ref, watch } from 'vue';
import { DocumentIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ file: { type: Object, required: true } });
const failed = ref(false);
const pdf = computed(() => props.file.pdfUrl || (props.file.extension?.toLowerCase() === 'pdf' ? props.file.viewUrl || props.file.url : null));
const image = computed(() => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(props.file.extension?.toLowerCase())
    ? props.file.viewUrl || props.file.url : props.file.previewUrl);
watch(() => props.file.id, () => { failed.value = false; });
</script>

<template>
    <section aria-label="Document preview" class="flex min-h-[420px] flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Document preview</h2>
            <a :href="file.viewUrl || file.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-zinc-500 hover:text-amber-700 dark:hover:text-amber-400">Open original<ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /></a>
        </div>
        <iframe v-if="pdf" :src="`${pdf}#navpanes=0&amp;view=Fit`" :title="'Preview of ' + (file.name || 'document')" class="min-h-[520px] w-full flex-1 border-0 lg:min-h-[680px]" />
        <div v-else-if="image && !failed" class="flex flex-1 items-start justify-center overflow-auto p-4">
            <img :src="image" :alt="file.name || 'Document'" class="max-h-[760px] max-w-full rounded bg-white object-contain shadow-sm" @error="failed = true" />
        </div>
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
            <DocumentIcon class="h-10 w-10 text-zinc-400" aria-hidden="true" />
            <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ failed ? 'Preview could not load' : 'Preview unavailable' }}</h3>
            <p class="max-w-xs text-xs leading-5 text-zinc-500 dark:text-zinc-400">You can open the original file to view its contents.</p>
            <a :href="file.viewUrl || file.url" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-amber-700 dark:text-amber-400">Open original file</a>
        </div>
    </section>
</template>
