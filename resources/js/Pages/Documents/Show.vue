<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Common/Modal.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import DocumentImage from '@/Components/Domain/DocumentImage.vue';
import {
    DocumentIcon,
    FolderIcon,
    ArrowDownTrayIcon,
    TrashIcon,
    PencilIcon,
    CheckIcon,
    ArrowLeftIcon
} from '@heroicons/vue/24/outline';

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface Category {
    id: number;
    name: string;
    color: string;
}

interface SharedUser {
    id: number;
    name: string;
    email: string;
    permission: 'view' | 'edit';
    shared_at: string;
}

interface FileInfo {
    id: number;
    url: string;
    pdfUrl: string | null;
    previewUrl?: string | null;
    extension: string;
    mime_type?: string;
    size?: number;
    guid?: string;
    has_preview?: boolean;
    is_pdf?: boolean;
    uploaded_at?: string | null;
    file_created_at?: string | null;
    file_modified_at?: string | null;
}

interface Document {
    id: number;
    title: string;
    summary: string | null;
    note: string | null;
    category_id: number | null;
    tags: Tag[];
    shared_users: SharedUser[];
    created_at: string | null;
    updated_at: string | null;
    file: FileInfo | null;
}

interface Props {
    document: Document;
    categories: Category[];
    available_tags: Tag[];
}

const props = defineProps<Props>();

const isEditing = ref(false);
const showDeleteModal = ref(false);
const documentTags = ref(props.document.tags);
const editedDocument = ref({
    title: props.document.title,
    summary: props.document.summary,
    note: props.document.note,
    category_id: props.document.category_id,
});

const selectedCategory = computed(() => {
    return props.categories.find(c => c.id === editedDocument.value.category_id);
});

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Auto-save when exiting edit mode
watch(isEditing, (newValue) => {
    if (!newValue) {
        // Exiting edit mode - save changes
        router.patch(route('documents.update', props.document.id), {
            ...editedDocument.value,
            tags: documentTags.value.map(t => t.id)
        }, {
            preserveScroll: true
        });
    } else {
        // Entering edit mode - reset form
        editedDocument.value = {
            title: props.document.title,
            summary: props.document.summary,
            note: props.document.note,
            category_id: props.document.category_id,
        };
    }
});

const deleteDocument = () => {
    router.delete(route('documents.destroy', props.document.id));
};

const downloadDocument = () => {
    window.location.href = route('documents.download', props.document.id);
};

const handleTagAdded = (tag: Tag) => {
    if (isEditing.value) {
        documentTags.value = [...documentTags.value, tag];
    } else {
        router.post(route('documents.tags.store', props.document.id), {
            name: tag.name
        }, {
            preserveScroll: true
        });
    }
};

const handleTagRemoved = (tag: Tag) => {
    if (isEditing.value) {
        documentTags.value = documentTags.value.filter(t => t.id !== tag.id);
    } else {
        router.delete(route('documents.tags.destroy', [props.document.id, tag.id]), {
            preserveScroll: true
        });
    }
};

const handleSharesUpdated = (shares: any[]) => {
    props.document.shared_users = (shares || []).map((s: any) => ({
        id: s.shared_with_user?.id ?? s.id,
        name: s.shared_with_user?.name ?? s.name,
        email: s.shared_with_user?.email ?? s.email,
        permission: s.permission,
        shared_at: s.shared_at,
    }));
};

const sharingControlShares = computed(() => {
    const users = (props.document.shared_users || []) as any[];
    return users.map((u: any) => ({
        shared_with_user: { id: u.id, name: u.name, email: u.email },
        permission: u.permission,
        shared_at: u.shared_at,
    }));
});

const getDocumentTypeClass = () => {
    return 'text-amber-400 bg-amber-400/10';
};
</script>

<template>
    <Head :title="document.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight flex items-center gap-x-2">
                    <DocumentIcon class="size-6" />
                    {{ document.title }}
                </h2>
                <div class="flex items-center gap-x-4">
                    <button
                        @click="downloadDocument"
                        class="inline-flex items-center gap-x-2 px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Download
                    </button>
                    <SharingControls
                        :file-id="document.id"
                        file-type="document"
                        :current-shares="sharingControlShares"
                        @shares-updated="handleSharesUpdated"
                    />
                    <Link
                        :href="route('documents.index')"
                        class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Back to Documents
                    </Link>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-9rem)] overflow-hidden">
            <!-- Left Panel - Document Details -->
            <div class="w-1/2 p-6 overflow-y-auto border-r border-amber-200 dark:border-zinc-700">
                <div class="space-y-8">
                    <!-- Document Status/Type Badge -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-3">
                                <div :class="[getDocumentTypeClass(), 'flex-none rounded-full p-1']">
                                    <div class="size-2 rounded-full bg-current" />
                                </div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">Document Details</h3>
                            </div>
                            <button
                                @click="isEditing = !isEditing"
                                class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                                :class="isEditing ? 'text-zinc-900 bg-amber-100 hover:bg-amber-200 dark:text-zinc-100 dark:bg-zinc-700 dark:hover:bg-amber-600' : 'text-zinc-100 bg-zinc-700 hover:bg-amber-600 dark:bg-zinc-600 dark:hover:bg-zinc-500'"
                            >
                                <PencilIcon v-if="!isEditing" class="size-4" />
                                <CheckIcon v-else class="size-4" />
                                {{ isEditing ? 'Save Changes' : 'Edit Document' }}
                            </button>
                        </div>

                        <dl class="mt-6 space-y-6">
                            <!-- Title -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Title</dt>
                                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ document.title }}
                                </dd>
                                <input
                                    v-else
                                    v-model="editedDocument.title"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                />
                            </div>

                            <!-- Summary -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Summary</dt>
                                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ document.summary || 'No summary available' }}
                                </dd>
                                <textarea
                                    v-else
                                    v-model="editedDocument.summary"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                />
                            </div>

                            <!-- Document Note -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Document Note</dt>
                                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ document.note || 'No note added' }}
                                </dd>
                                <textarea
                                    v-else
                                    v-model="editedDocument.note"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                />
                            </div>

                            <!-- Category -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Category</dt>
                                <dd v-if="!isEditing">
                                    <span
                                        v-if="selectedCategory"
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                        :style="{ backgroundColor: selectedCategory.color + '20', color: selectedCategory.color }"
                                    >
                                        <FolderIcon class="h-4 w-4 mr-1" />
                                        {{ selectedCategory.name }}
                                    </span>
                                    <p v-else class="mt-1 text-sm text-zinc-500">No category</p>
                                </dd>
                                <select
                                    v-else
                                    v-model="editedDocument.category_id"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                >
                                    <option :value="null">No category</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                        </dl>
                    </div>

                    <!-- Tags -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Tags</h3>
                        <TagManager
                            v-model="documentTags"
                            :readonly="!isEditing"
                            @tag-added="handleTagAdded"
                            @tag-removed="handleTagRemoved"
                        />
                    </div>

                    <!-- File Metadata -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">File Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">File Size</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatFileSize(document.file?.size || 0) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Uploaded to PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(document.file?.uploaded_at || document.created_at) }}
                                </dd>
                            </div>
                            <div v-if="document.file?.file_created_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Created</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(document.file.file_created_at) }}
                                </dd>
                            </div>
                            <div v-if="document.file?.file_modified_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Modified</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(document.file.file_modified_at) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Last Updated in PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(document.updated_at) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Delete Button -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <button
                            @click="showDeleteModal = true"
                            class="w-full inline-flex justify-center items-center gap-x-2 px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete Document
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Document Preview -->
            <div class="w-1/2 bg-amber-50 dark:bg-zinc-900 overflow-auto">
                <DocumentImage
                    :file="document.file"
                    :alt-text="document.title"
                    error-message="Failed to load document preview"
                    no-image-message="No document preview available"
                    :show-pdf-button="true"
                    pdf-button-position="fixed bottom-6 right-6"
                />
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-white">
                    Delete Document
                </h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete this document? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md font-semibold text-xs text-zinc-700 dark:text-zinc-300 uppercase tracking-widest hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteDocument"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
