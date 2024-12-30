<template>
    <Head title="PaperPulse - Upload files" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Upload files</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Success Alert -->
                <div class="rounded-md bg-green-50 p-4 mb-4" v-if="uploadSuccess">
                    <div class="flex">
                        <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">File uploaded successfully</p>
                        </div>
                        <button type="button"
                            class="ml-auto -mx-1.5 -my-1.5 rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2"
                            @click="uploadSuccess = false">
                            <span class="sr-only">Dismiss</span>
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <!-- Error Alert -->
                <div class="rounded-md bg-red-50 p-4 mb-4" v-if="uploadError">
                    <div class="flex">
                        <XMarkIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ uploadError }}</p>
                        </div>
                        <button type="button"
                            class="ml-auto -mx-1.5 -my-1.5 rounded-md bg-red-50 p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                            @click="uploadError = null">
                            <span class="sr-only">Dismiss</span>
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 px-6 py-24 sm:py-32 rounded-lg shadow-sm">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-4xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-5xl">Upload Your Documents</h2>
                        <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-400">
                            Upload your receipts and documents. They will be automatically processed and organized for you.
                        </p>
                        
                        <form class="mt-10" ref="fileUpload" @submit.prevent="submit">
                            <div 
                                class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 dark:border-gray-700 px-6 py-10 relative"
                                :class="[
                                    { 'cursor-pointer': selectedFiles.length === 0 },
                                    { 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': isDragging }
                                ]"
                                @click="selectedFiles.length === 0 && $refs.fileInput?.click()"
                                v-bind="$attrs"
                                @drop.prevent="handleDrop"
                                @dragover.prevent
                                @dragenter.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                            >
                                <input 
                                    ref="fileInput"
                                    type="file" 
                                    multiple 
                                    class="sr-only" 
                                    @change="handleFileSelect"
                                    accept=".pdf,.png,.jpg,.jpeg"
                                />

                                <!-- Empty State -->
                                <div v-if="selectedFiles.length === 0" class="text-center">
                                    <PhotoIcon class="mx-auto h-12 w-12" :class="isDragging ? 'text-indigo-500' : 'text-gray-300'" aria-hidden="true" />
                                    <div class="mt-4 flex text-sm leading-6 text-gray-600 dark:text-gray-400">
                                        <span class="relative rounded-md bg-white dark:bg-gray-800 font-semibold text-indigo-600 dark:text-indigo-400 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500">
                                            Upload files
                                        </span>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs leading-5 text-gray-600 dark:text-gray-400">PDF, PNG, JPG up to 10MB</p>
                                </div>

                                <!-- File Preview -->
                                <div v-else class="w-full">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div v-for="(file, index) in selectedFiles" :key="file.name + index"
                                            class="relative flex items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex-shrink-0 h-16 w-16 relative">
                                                <img v-if="file.preview" 
                                                    :src="file.preview" 
                                                    class="h-16 w-16 object-cover rounded-lg"
                                                    alt="File preview"
                                                />
                                                <DocumentIcon v-else 
                                                    class="h-16 w-16 text-gray-400"
                                                />
                                            </div>
                                            <div class="ml-4 flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                    {{ file.name }}
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ file.size }}
                                                </p>
                                            </div>
                                            <button 
                                                @click.prevent="removeFile(index)" 
                                                class="ml-4 flex-shrink-0 p-1 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                                type="button"
                                            >
                                                <span class="sr-only">Remove file</span>
                                                <XMarkIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Add more files button -->
                                    <div class="flex justify-center mt-4">
                                        <label class="relative cursor-pointer rounded-md bg-white dark:bg-gray-800 font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                            <span>Add more files</span>
                                            <input 
                                                type="file" 
                                                multiple 
                                                class="sr-only" 
                                                @change="handleAdditionalFiles"
                                                accept=".pdf,.png,.jpg,.jpeg"
                                            />
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Upload Progress -->
                                <div v-if="form.progress" class="absolute inset-x-0 bottom-0 p-4 bg-white dark:bg-gray-800 border-t dark:border-gray-700">
                                    <div class="relative pt-1">
                                        <div class="flex mb-2 items-center justify-between">
                                            <div>
                                                <span class="text-xs font-semibold inline-block text-indigo-600 dark:text-indigo-400">
                                                    Uploading
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-semibold inline-block text-indigo-600 dark:text-indigo-400">
                                                    {{ form.progress.percentage }}%
                                                </span>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-indigo-200 dark:bg-indigo-900">
                                            <div
                                                :style="{ width: form.progress.percentage + '%' }"
                                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-300"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="mt-6 flex justify-center">
                                <button type="submit"
                                    :disabled="selectedFiles.length === 0 || form.processing"
                                    :class="[
                                        'rounded-md px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors duration-200',
                                        (selectedFiles.length === 0 || form.processing)
                                            ? 'bg-gray-400 cursor-not-allowed' 
                                            : 'bg-indigo-600 hover:bg-indigo-500'
                                    ]">
                                    <span v-if="form.processing">Uploading...</span>
                                    <span v-else>Upload {{ selectedFiles.length }} {{ selectedFiles.length === 1 ? 'file' : 'files' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { CheckCircleIcon, XMarkIcon, PhotoIcon, DocumentIcon } from '@heroicons/vue/20/solid'
import { ref, watch } from 'vue';
import { useFileUpload } from '@/Composables/useFileUpload';

interface FileObject {
    file: File;
    preview: string | null;
    name: string;
    size: string;
    type: string;
}

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];

const {
    selectedFiles,
    isDragging,
    handleDrop,
    handleFileSelect,
    handleAdditionalFiles,
    removeFile,
    resetFiles
} = useFileUpload({
    maxFileSize: MAX_FILE_SIZE,
    allowedTypes: ALLOWED_TYPES,
    onError: (error: string) => {
        uploadError.value = error;
        setTimeout(() => {
            uploadError.value = null;
        }, 5000);
    }
});

const uploadSuccess = ref(false);
const uploadError = ref<string | null>(null);
const fileUpload = ref<HTMLFormElement | null>(null);

const form = useForm({
    files: null as File[] | null,
});

watch(selectedFiles, (files) => {
    if (!files.length) {
        form.files = null;
        return;
    }
    
    const dt = new DataTransfer();
    files.forEach(fileObj => dt.items.add(fileObj.file));
    form.files = dt.files;
}, { deep: true });

async function submit() {
    if (!form.files?.length) return;
    
    try {
        await form.post('/documents/store', {
            preserveScroll: true,
            onSuccess: () => {
                resetFiles();
                form.reset();
                uploadSuccess.value = true;
                setTimeout(() => {
                    uploadSuccess.value = false;
                }, 5000);
            },
            onError: (errors) => {
                uploadError.value = Object.values(errors)[0] as string;
                setTimeout(() => {
                    uploadError.value = null;
                }, 5000);
            },
            onProgress: (event) => {
                if (event.total) {
                    form.progress = {
                        percentage: Math.round((event.loaded / event.total) * 100),
                    };
                }
            },
        });
    } catch (error) {
        uploadError.value = 'An unexpected error occurred during upload';
        setTimeout(() => {
            uploadError.value = null;
        }, 5000);
    }
}
</script>
