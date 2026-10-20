<template>
    <Head title="Upload files" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">Upload files</h1>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="uploadOutcomes.length" class="mb-6 flex flex-col gap-3">
                    <section v-for="outcome in uploadOutcomes" :key="outcome.status"
                        :data-upload-outcome="outcome.status"
                        :role="outcome.status === 'failed' ? 'alert' : 'status'"
                        class="flex items-start gap-3 rounded-lg border p-4"
                        :class="outcome.classes">
                        <component :is="outcome.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <h3 class="text-sm font-semibold">{{ outcome.title }}</h3>
                            <p v-if="outcome.status === 'accepted'" class="text-sm">
                                Processing continues in the background. Open a file’s processing record below to review its status or any requested decisions.
                            </p>
                            <p v-else-if="outcome.status === 'failed'" class="text-sm">Failed files remain selected below so you can correct the issue and try again.</p>
                            <ul v-if="outcome.status !== 'accepted'" class="flex flex-col gap-2 text-sm">
                                <li v-for="result in outcome.results" :key="result.index" class="wrap-break-word">
                                    <span class="font-medium">{{ result.filename }}</span>: {{ result.message }}
                                </li>
                            </ul>
                            <ul v-if="outcome.status === 'accepted'" class="flex flex-col gap-2 text-sm">
                                <li v-for="result in outcome.results" :key="result.index" class="flex flex-wrap items-center justify-between gap-2 wrap-break-word">
                                    <span class="font-medium">{{ result.filename }}</span>
                                    <Link :href="route('files.index', { file_id: result.file_id })" class="font-semibold underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-current">Open processing record</Link>
                                </li>
                            </ul>
                        </div>
                        <button type="button" @click="dismissUploadOutcome(outcome.status)"
                            :aria-label="'Dismiss ' + outcome.title"
                            class="shrink-0 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-current">
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </section>
                </div>

                <!-- Error Alert -->
                <div class="mb-4 rounded-md bg-red-50 p-4 dark:bg-red-950/40" v-if="uploadError" role="alert" aria-live="assertive">
                    <div class="flex">
                        <XMarkIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ uploadError }}</p>
                        </div>
                        <button type="button"
                            class="ml-auto -mx-1.5 -my-1.5 rounded-md bg-red-50 p-1.5 text-red-500 hover:bg-red-100 focus:outline-hidden focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                            @click="uploadError = null">
                            <span class="sr-only">Dismiss</span>
                            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-xs dark:bg-zinc-800 sm:p-8">
                    <div class="mx-auto max-w-3xl">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Choose receipts, invoices, contracts, statements or other documents. We detect the file type automatically and extract its details.</p>

                        <form class="mt-6 flex flex-col gap-6" @submit.prevent="submit">
                            <section aria-labelledby="upload-selection-heading" class="flex flex-col gap-4">
                                <div class="flex flex-col gap-1">
                                    <h2 id="upload-selection-heading" class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">1. Choose files</h2>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Select files or drag and drop them here. You can add more before uploading.</p>
                                </div>

                                <div id="upload-selection"
                                    class="relative flex min-h-52 items-center justify-center rounded-lg border border-dashed border-zinc-400 px-4 py-8 dark:border-zinc-600"
                                    :class="[
                                        { 'border-amber-500 bg-amber-50 dark:bg-zinc-900/20': isDragging }
                                    ]"
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
                                        aria-label="Choose documents to upload"
                                        dusk="upload-file-input"
                                        @change="handleFileSelect"
                                        :accept="Object.keys(uploadConfig.capabilities[fileType]).map(extension => '.' + extension).join(',')"
                                    />

                                    <!-- Empty State -->
                                    <div v-if="selectedFiles.length === 0" class="flex max-w-xl flex-col items-center gap-3 text-center">
                                        <PhotoIcon class="mx-auto h-12 w-12" :class="isDragging ? 'text-amber-500' : 'text-zinc-300'" aria-hidden="true" />
                                        <p class="text-sm text-zinc-700 dark:text-zinc-300">Choose documents or drag and drop them here</p>
                                        <button type="button" class="workspace-primary" @click="openFilePicker">Choose files</button>
                                        <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">
                                            {{ Object.keys(uploadConfig.capabilities[fileType]).join(', ').toUpperCase() }} up to {{ uploadConfig.maxFileSizeMb[fileType] }}MB each
                                        </p>
                                        <p v-if="uploadConfig.processingLimits" class="text-xs text-zinc-600 dark:text-zinc-300">
                                            Extraction supports up to {{ uploadConfig.processingLimits.pdfPages }} pages per PDF and {{ Math.floor(uploadConfig.processingLimits.textBytes / 1000) }} KB of native text. Split longer files into supported parts before uploading. Files above these processing limits are blocked before extraction.
                                        </p>
                                    </div>

                                    <!-- File Preview -->
                                    <div v-else class="w-full">
                                        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <li v-for="(file, index) in selectedFiles" :key="file.name + index"
                                                class="relative flex min-w-0 items-center rounded-lg bg-amber-50 p-3 dark:bg-zinc-700 sm:p-4">
                                                <div class="relative h-16 w-16 shrink-0">
                                                    <img v-if="file.preview" :src="file.preview" class="h-16 w-16 rounded-lg object-cover" alt="File preview" />
                                                    <DocumentIcon v-else class="h-16 w-16 text-zinc-400" aria-hidden="true" />
                                                </div>
                                                <div class="ml-4 min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ file.name }}</p>
                                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ file.size }}</p>
                                                </div>
                                                <button @click.prevent="removeFile(index)" :aria-label="`Remove ${file.name}`" class="ml-3 shrink-0 rounded-full p-2 text-zinc-600 hover:text-zinc-900 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-zinc-300 dark:hover:text-white" type="button">
                                                    <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                                                </button>
                                            </li>
                                        </ul>

                                        <!-- Add more files button -->
                                        <div class="mt-4 flex justify-center">
                                            <input ref="additionalFileInput" type="file" multiple class="sr-only" aria-label="Add more documents" @change="handleAdditionalFiles" :accept="Object.keys(uploadConfig.capabilities[fileType]).map(extension => '.' + extension).join(',')" />
                                            <button type="button" class="workspace-button" @click="openAdditionalFilePicker">Add more files</button>
                                        </div>
                                    </div>

                                    <!-- Upload progress is shown directly below the file selection area. -->
                                </div>

                                <div v-if="isUploading" class="flex flex-col gap-2" role="status" aria-live="polite">
                                    <div class="flex justify-between text-sm font-medium text-zinc-700 dark:text-zinc-200">
                                        <span>Uploading your files</span>
                                        <span>{{ uploadProgress }}%</span>
                                    </div>
                                    <div role="progressbar" aria-label="Upload progress" :aria-valuenow="uploadProgress" aria-valuemin="0" aria-valuemax="100" class="h-2 overflow-hidden rounded-sm bg-amber-100 dark:bg-zinc-700">
                                        <div :style="{ width: uploadProgress + '%' }" class="h-full bg-amber-600 transition-all duration-300 dark:bg-amber-500"></div>
                                    </div>
                                </div>
                            </section>

                            <details class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-zinc-800 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-600 dark:text-zinc-100">2. Add notes and organization <span class="font-normal text-zinc-500 dark:text-zinc-400">(optional)</span></summary>
                                <div class="flex flex-col gap-4 border-t border-zinc-200 p-4 dark:border-zinc-700">
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">These details apply to all selected files. You can organize files later too.</p>
                                    <div class="text-left">
                                        <label for="document-note" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Note <span class="font-normal text-zinc-500">(optional)</span></label>
                                        <textarea id="document-note" v-model="note" rows="2" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white text-zinc-900 shadow-xs focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 sm:text-sm" placeholder="Add a note about these files" />
                                    </div>
                                    <div class="text-left">
                                        <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Collections <span class="font-normal text-zinc-500">(optional)</span></label>
                                        <CollectionSelector v-model="collectionIds" placeholder="Search or create collections..." :allow-create="true" />
                                    </div>
                                    <div class="text-left">
                                        <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tags <span class="font-normal text-zinc-500">(optional)</span></label>
                                        <TagSelector v-model="tagIds" placeholder="Search or create tags..." :allow-create="true" />
                                    </div>
                                </div>
                            </details>

                            <!-- Submit Button -->
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Files are uploaded securely, then processed in the background.</p>
                                <button type="submit"
                                    :disabled="selectedFiles.length === 0 || isUploading"
                                    :class="[
                                        'min-h-11 rounded-md px-5 py-3 text-sm font-semibold text-white shadow-xs focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-colors duration-200 sm:min-w-48',
                                        (selectedFiles.length === 0 || isUploading)
                                            ? 'bg-zinc-400 cursor-not-allowed' 
                                            : 'bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700'
                                    ]">
                                    <span v-if="isUploading">Uploading…</span>
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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { XMarkIcon, PhotoIcon, DocumentIcon, CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/20/solid'
import { computed, ref } from 'vue';

interface FileObject {
    file: File;
    preview: string | null;
    name: string;
    size: string;
    type: string;
}

const props = defineProps<{
    uploadConfig: {
        processingLimits?: { pdfPages: number; textBytes: number } | null;
        maxFileSizeMb: Record<'receipt' | 'document', number>;
        capabilities: Record<'receipt' | 'document', Record<string, { mimeTypes: string[]; maxBytes: number }>>;
    };
}>();

interface UploadOutcome {
    index: number;
    filename: string;
    status: 'accepted' | 'duplicate' | 'failed';
    message: string;
    file_id?: number;
}

const uploadResults = ref<UploadOutcome[]>([]);
const outcomeStyles = [
    {
        status: 'accepted' as const,
        icon: CheckCircleIcon,
        classes: 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200',
    },
    {
        status: 'duplicate' as const,
        icon: ExclamationTriangleIcon,
        classes: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200',
    },
    {
        status: 'failed' as const,
        icon: XCircleIcon,
        classes: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200',
    },
];
const uploadOutcomes = computed(() => outcomeStyles.map(outcome => {
    const results = uploadResults.value.filter(result => result.status === outcome.status);
    const fileCount = `${results.length} ${results.length === 1 ? 'file' : 'files'}`;
    const title = outcome.status === 'accepted'
        ? `${fileCount} queued for processing`
        : outcome.status === 'duplicate'
            ? `${fileCount} already uploaded`
            : `${fileCount} could not be uploaded — retry below`;

    return { ...outcome, results, title };
}).filter(outcome => outcome.results.length > 0));

function dismissUploadOutcome(status: UploadOutcome['status']): void {
    uploadResults.value = uploadResults.value.filter(result => result.status !== status);
}
const uploadError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const additionalFileInput = ref<HTMLInputElement | null>(null);
const fileType = 'document' as const;
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
    const extension = file.name.split('.').pop()?.toLowerCase() || '';
    const capability = props.uploadConfig.capabilities[fileType][extension];
    if (!capability) {
        uploadError.value = `File ${file.name} has an unsupported extension.`;
        return false;
    }
    const maxSize = capability.maxBytes;

    if (file.size > maxSize) {
        uploadError.value = `File ${file.name} is too large. Maximum size is ${formatFileSize(maxSize)}`;
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
    input.value = '';
}

function handleAdditionalFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    handleFiles(Array.from(input.files), true);
    input.value = '';
}

function openFilePicker(): void {
    fileInput.value?.click();
}

function openAdditionalFilePicker(): void {
    additionalFileInput.value?.click();
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

function submit() {
    if (!selectedFiles.value.length) return;
    
    uploadError.value = null;
    isUploading.value = true;
    uploadProgress.value = 0;
    
    const submittedFiles = [...selectedFiles.value];
    const uploadForm = useForm({
        files: submittedFiles.map(f => f.file),
        file_type: fileType,
        note: note.value || null,
        collection_ids: collectionIds.value.length > 0 ? collectionIds.value : null,
        tag_ids: tagIds.value.length > 0 ? tagIds.value : null,
    });
    
    try {
        uploadForm.post(route('documents.store'), {
            preserveScroll: false,
            onSuccess: (page) => {
                uploadResults.value = page.props.flash.upload_results;
                const failedIndexes = new Set(uploadResults.value
                    .filter(result => result.status === 'failed')
                    .map(result => result.index));
                selectedFiles.value = submittedFiles.filter((file, index) => {
                    if (failedIndexes.has(index)) return true;
                    if (file.preview) URL.revokeObjectURL(file.preview);
                    return false;
                });
                if (selectedFiles.value.length === 0) {
                    note.value = '';
                    collectionIds.value = [];
                    tagIds.value = [];
                }
                uploadProgress.value = 0;
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
