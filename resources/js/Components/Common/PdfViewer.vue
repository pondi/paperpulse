<script setup lang="ts">
import { computed } from 'vue';
import { XMarkIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

interface Props {
    show: boolean;
    pdfUrl: string | null;
    title?: string;
    downloadUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Document Preview',
    downloadUrl: undefined
});

const emit = defineEmits<{
    close: [];
}>();

const effectiveDownloadUrl = computed(() => {
    return props.downloadUrl || props.pdfUrl;
});

const handleDownload = () => {
    if (effectiveDownloadUrl.value) {
        window.location.href = effectiveDownloadUrl.value;
    }
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 overflow-hidden"
                @keydown.esc="handleClose"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/75 backdrop-blur-sm"
                    @click="handleClose"
                ></div>

                <!-- Modal Container -->
                <div class="relative h-full w-full flex items-center justify-center p-4 sm:p-6 lg:p-8">
                    <!-- PDF Viewer Card -->
                    <div
                        class="relative bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-full h-full max-w-7xl max-h-[90vh] flex flex-col"
                        @click.stop
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white truncate pr-4">
                                {{ title }}
                            </h2>
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="effectiveDownloadUrl"
                                    @click="handleDownload"
                                    class="inline-flex items-center gap-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors duration-150 font-medium text-sm"
                                    title="Download PDF"
                                >
                                    <ArrowDownTrayIcon class="h-5 w-5" />
                                    <span class="hidden sm:inline">Download</span>
                                </button>
                                <button
                                    @click="handleClose"
                                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                    title="Close"
                                >
                                    <XMarkIcon class="h-6 w-6" />
                                </button>
                            </div>
                        </div>

                        <!-- PDF Content -->
                        <div class="flex-1 overflow-hidden bg-gray-100 dark:bg-gray-900">
                            <div v-if="pdfUrl" class="h-full w-full">
                                <iframe
                                    :src="pdfUrl"
                                    class="h-full w-full border-0"
                                    title="PDF Document Viewer"
                                ></iframe>
                            </div>
                            <div v-else class="flex items-center justify-center h-full">
                                <p class="text-gray-500 dark:text-gray-400">No PDF available to display</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
