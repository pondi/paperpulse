<script setup>
import { computed } from 'vue'

const props = defineProps({
    file: {
        type: Object,
        default: null,
    },
})

const isImage = computed(() => {
    if (!props.file) return false
    return props.file.is_image || props.file.has_preview
})

const isPdf = computed(() => {
    return props.file?.is_pdf && props.file?.pdfUrl
})

const displayUrl = computed(() => {
    if (!props.file) return null
    if (props.file.has_preview && props.file.previewUrl) {
        return props.file.previewUrl
    }
    if (props.file.is_image) {
        return props.file.viewUrl
    }
    return null
})
</script>

<template>
    <div class="h-full flex items-center justify-center bg-zinc-50 dark:bg-zinc-900/50">
        <template v-if="!file">
            <div class="text-center text-zinc-400 dark:text-zinc-500">
                <svg class="mx-auto h-12 w-12 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <p class="text-sm">Select a file to preview</p>
            </div>
        </template>

        <template v-else-if="isPdf && !displayUrl">
            <iframe
                :src="file.pdfUrl"
                class="w-full h-full border-0"
                :title="file.name"
            />
        </template>

        <template v-else-if="displayUrl">
            <div class="relative w-full h-full flex items-center justify-center p-4">
                <img
                    :src="displayUrl"
                    :alt="file.name"
                    class="max-w-full max-h-full object-contain rounded"
                    @error="$event.target.style.display = 'none'"
                />
                <a
                    v-if="isPdf"
                    :href="file.pdfUrl"
                    target="_blank"
                    class="absolute bottom-4 right-4 inline-flex items-center gap-1.5 rounded-lg bg-zinc-900/80 dark:bg-zinc-700/90 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-900 dark:hover:bg-zinc-600 transition"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    View PDF
                </a>
            </div>
        </template>

        <template v-else>
            <div class="text-center text-zinc-400 dark:text-zinc-500 p-4">
                <svg class="mx-auto h-12 w-12 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <p class="text-sm mb-2">Preview not available</p>
                <a
                    :href="file.downloadUrl"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-500 transition"
                >
                    Download File
                </a>
            </div>
        </template>
    </div>
</template>
