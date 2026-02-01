<template>
    <Head title="PaperPulse - Upload files" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">Upload files</h2>
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

                <div class="bg-white dark:bg-zinc-800 px-6 py-24 sm:py-32 rounded-lg shadow-sm">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-4xl font-semibold tracking-tight text-zinc-900 dark:text-white sm:text-5xl">Upload Your Documents</h2>
                        <p class="mt-6 text-lg leading-8 text-zinc-600 dark:text-zinc-400">
                            Upload your receipts and documents. They will be automatically processed and organized for you.
                        </p>

                        <!-- File Type Selection -->
                        <div class="mt-8 flex justify-center">
                            <div class="inline-flex rounded-md shadow-sm" role="group">
                                <button
                                    type="button"
                                    @click="fileType = 'receipt'"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-l-lg border',
                                        fileType === 'receipt'
                                            ? 'bg-zinc-900 dark:bg-amber-600 text-white border-amber-600 z-10'
                                            : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300 dark:border-zinc-600 hover:bg-amber-50 dark:hover:bg-zinc-700'
                                    ]"
                                >
                                    <ReceiptRefundIcon class="h-5 w-5 inline-block mr-2" />
                                    Receipt
                                </button>
                                <button
                                    type="button"
                                    @click="fileType = 'document'"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-r-lg border',
                                        fileType === 'document'
                                            ? 'bg-zinc-900 dark:bg-amber-600 text-white border-amber-600 z-10'
                                            : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300 dark:border-zinc-600 hover:bg-amber-50 dark:hover:bg-zinc-700'
                                    ]"
                                >
                                    <DocumentIcon class="h-5 w-5 inline-block mr-2" />
                                    Document
                                </button>
                            </div>
                        </div>
                        
                        <form class="mt-6" ref="fileUpload" @submit.prevent="submit">
                            <div 
                                class="mt-2 flex justify-center rounded-lg border border-dashed border-zinc-900/25 dark:border-zinc-700 px-6 py-10 relative"
                                :class="[
                                    { 'cursor-pointer': selectedFiles.length === 0 },
                                    { 'border-amber-500 bg-amber-50 dark:bg-zinc-900/20': isDragging }
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
                                    :accept="fileType === 'receipt' ? '.pdf,.png,.jpg,.jpeg' : '.pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv'"
                                />

                                <!-- Empty State -->
                                <div v-if="selectedFiles.length === 0" class="text-center">
                                    <PhotoIcon class="mx-auto h-12 w-12" :class="isDragging ? 'text-amber-500' : 'text-zinc-300'" aria-hidden="true" />
                                    <div class="mt-4 flex text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        <span class="relative rounded-md bg-white dark:bg-zinc-800 font-semibold text-amber-600 dark:text-amber-400 focus-within:outline-none focus-within:ring-2 focus-within:ring-amber-600 focus-within:ring-offset-2 hover:text-amber-500">
                                            Upload files
                                        </span>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                                        {{ fileType === 'receipt' 
                                            ? 'PDF, PNG, JPG up to 10MB' 
                                            : 'PDF, PNG, JPG, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV up to 50MB' 
                                        }}
                                    </p>
                                </div>

                                <!-- File Preview -->
                                <div v-else class="w-full">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div v-for="(file, index) in selectedFiles" :key="file.name + index"
                                            class="relative flex items-center p-4 bg-amber-50 dark:bg-zinc-700 rounded-lg">
                                            <div class="flex-shrink-0 h-16 w-16 relative">
                                                <img v-if="file.preview" 
                                                    :src="file.preview" 
                                                    class="h-16 w-16 object-cover rounded-lg"
                                                    alt="File preview"
                                                />
                                                <DocumentIcon v-else 
                                                    class="h-16 w-16 text-zinc-400"
                                                />
                                            </div>
                                            <div class="ml-4 flex-1 min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                                    {{ file.name }}
                                                </p>
                                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ file.size }}
                                                </p>
                                            </div>
                                            <button 
                                                @click.prevent="removeFile(index)" 
                                                class="ml-4 flex-shrink-0 p-1 rounded-full text-zinc-400 hover:text-zinc-500 dark:hover:text-zinc-300"
                                                type="button"
                                            >
                                                <span class="sr-only">Remove file</span>
                                                <XMarkIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Add more files button -->
                                    <div class="flex justify-center mt-4">
                                        <label class="relative cursor-pointer rounded-md bg-white dark:bg-zinc-800 font-semibold text-amber-600 dark:text-amber-400 hover:text-amber-500">
                                            <span>Add more files</span>
                                            <input 
                                                type="file" 
                                                multiple 
                                                class="sr-only" 
                                                @change="handleAdditionalFiles"
                                                :accept="fileType === 'receipt' ? '.pdf,.png,.jpg,.jpeg' : '.pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv'"
                                            />
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Upload Progress -->
                                <div v-if="isUploading" class="absolute inset-x-0 bottom-0 p-4 bg-white dark:bg-zinc-800 border-t dark:border-zinc-700">
                                    <div class="relative pt-1">
                                        <div class="flex mb-2 items-center justify-between">
                                            <div>
                                                <span class="text-xs font-semibold inline-block text-amber-600 dark:text-amber-400">
                                                    Uploading
                                                </span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-semibold inline-block text-amber-600 dark:text-amber-400">
                                                    {{ uploadProgress }}%
                                                </span>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-amber-200 dark:bg-zinc-900">
                                            <div
                                                :style="{ width: uploadProgress + '%' }"
                                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-zinc-900 dark:bg-amber-600 transition-all duration-300"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Note -->
                            <div class="mt-4 text-left">
                                <label for="document-note" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    Document Note
                                </label>
                                <textarea
                                    id="document-note"
                                    v-model="note"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                                    placeholder="Optional note about these files..."
                                />
                            </div>

                            <!-- Collections -->
                            <div class="mt-4 text-left">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                                    Collections (optional)
                                </label>
                                <CollectionSelector
                                    v-model="collectionIds"
                                    placeholder="Search or create collections..."
                                    :allow-create="true"
                                />
                            </div>

                            <!-- Tags -->
                            <div class="mt-4 text-left">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                                    Tags (optional)
                                </label>
                                <TagSelector
                                    v-model="tagIds"
                                    placeholder="Search or create tags..."
                                    :allow-create="true"
                                />
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-6 flex justify-center">
                                <button type="submit"
                                    :disabled="selectedFiles.length === 0 || isUploading"
                                    :class="[
                                        'rounded-md px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors duration-200',
                                        (selectedFiles.length === 0 || isUploading)
                                            ? 'bg-zinc-400 cursor-not-allowed' 
                                            : 'bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700'
                                    ]">
                                    <span v-if="isUploading">Uploading...</span>
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
import CollectionSelector from '@/Components/Domain/CollectionSelector.vue';
import TagSelector from '@/Components/Domain/TagSelector.vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { CheckCircleIcon, XMarkIcon, PhotoIcon, DocumentIcon, ReceiptRefundIcon } from '@heroicons/vue/20/solid'
import { ref, watch } from 'vue';

interface FileObject {
    file: File;
    preview: string | null;
    name: string;
    size: string;
    type: string;
}

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB for receipts
const MAX_DOCUMENT_SIZE = 50 * 1024 * 1024; // 50MB for documents

const RECEIPT_TYPES = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
const DOCUMENT_TYPES = [
    'application/pdf', 
    'image/png', 
    'image/jpeg', 
    'image/jpg',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
    'text/csv'
];

const uploadSuccess = ref(false);
const uploadError = ref<string | null>(null);
const fileUpload = ref<HTMLFormElement | null>(null);
const fileType = ref<'receipt' | 'document'>('receipt'); // Default to receipt
const isUploading = ref(false);
const uploadProgress = ref(0);
const note = ref<string>('');
const collectionIds = ref<number[]>([]);
const tagIds = ref<number[]>([]);

// Custom file upload state (not using composable's validation since we need dynamic types)
const selectedFiles = ref<FileObject[]>([]);
const isDragging = ref(false);

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function validateFile(file: File): boolean {
    const maxSize = fileType.value === 'receipt' ? MAX_FILE_SIZE : MAX_DOCUMENT_SIZE;
    const allowedTypes = fileType.value === 'receipt' ? RECEIPT_TYPES : DOCUMENT_TYPES;

    if (file.size > maxSize) {
        uploadError.value = `File ${file.name} is too large. Maximum size is ${formatFileSize(maxSize)}`;
        setTimeout(() => { uploadError.value = null; }, 5000);
        return false;
    }

    if (!allowedTypes.includes(file.type)) {
        uploadError.value = `File ${file.name} has an invalid type. Allowed types are: ${allowedTypes.join(', ')}`;
        setTimeout(() => { uploadError.value = null; }, 5000);
        return false;
    }

    return true;
}

function createFileObject(file: File): FileObject {
    return {
        file,
        preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        name: file.name,
        size: formatFileSize(file.size),
        type: file.type
    };
}

function handleFiles(files: File[], append = false) {
    const validFiles = Array.from(files)
        .filter(file => file.size > 0 && validateFile(file));

    if (validFiles.length === 0) return;

    const newFileObjects = validFiles.map(createFileObject);

    if (append) {
        selectedFiles.value = [...selectedFiles.value, ...newFileObjects];
    } else {
        selectedFiles.value = newFileObjects;
    }
}

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    handleFiles(Array.from(input.files));
}

function handleAdditionalFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    handleFiles(Array.from(input.files), true);
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    if (!event.dataTransfer?.files) return;
    handleFiles(Array.from(event.dataTransfer.files), true);
}

function removeFile(index: number) {
    const file = selectedFiles.value[index];
    if (file.preview) {
        URL.revokeObjectURL(file.preview);
    }
    selectedFiles.value.splice(index, 1);
}

function resetFiles() {
    selectedFiles.value.forEach(file => {
        if (file.preview) {
            URL.revokeObjectURL(file.preview);
        }
    });
    selectedFiles.value = [];
}

// Watch fileType changes and clear selected files to avoid validation issues
watch(fileType, () => {
    resetFiles();
});

// File type is tracked separately from the form
// The form is created dynamically in the submit function

async function submit() {
    if (!selectedFiles.value.length) return;
    
    isUploading.value = true;
    uploadProgress.value = 0;
    
    // Create a new form with proper file array structure
    const uploadForm = useForm({
        files: selectedFiles.value.map(f => f.file),
        file_type: fileType.value,
        note: note.value || null,
        collection_ids: collectionIds.value.length > 0 ? collectionIds.value : null,
        tag_ids: tagIds.value.length > 0 ? tagIds.value : null,
    });
    
    try {
        await uploadForm.post('/documents/store', {
            preserveScroll: true,
            onSuccess: () => {
                resetFiles();
                note.value = '';
                collectionIds.value = [];
                tagIds.value = [];
                uploadSuccess.value = true;
                isUploading.value = false;
                uploadProgress.value = 0;
                setTimeout(() => {
                    uploadSuccess.value = false;
                }, 5000);
            },
            onError: (errors) => {
                uploadError.value = Object.values(errors)[0] as string;
                isUploading.value = false;
                uploadProgress.value = 0;
                setTimeout(() => {
                    uploadError.value = null;
                }, 5000);
            },
            onProgress: (event) => {
                if (event.total) {
                    uploadProgress.value = Math.round((event.loaded / event.total) * 100);
                }
            },
            onFinish: () => {
                isUploading.value = false;
            },
        });
    } catch (error) {
        uploadError.value = 'An unexpected error occurred during upload';
        isUploading.value = false;
        uploadProgress.value = 0;
        setTimeout(() => {
            uploadError.value = null;
        }, 5000);
    }
}
</script>
