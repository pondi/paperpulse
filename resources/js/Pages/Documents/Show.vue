<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import DangerButton from '@/Components/Buttons/DangerButton.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import InputError from '@/Components/Forms/InputError.vue';
import Modal from '@/Components/Common/Modal.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import { 
    DocumentIcon,
    TagIcon,
    FolderIcon,
    ShareIcon,
    ArrowDownTrayIcon,
    TrashIcon,
    PencilIcon,
    XMarkIcon,
    PlusIcon,
    UserPlusIcon
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
    extension: string;
    mime_type?: string;
    size?: number;
    guid?: string;
}

interface Document {
    id: number;
    title: string;
    summary: string | null;
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

const form = useForm({
    title: props.document.title,
    summary: props.document.summary,
    category_id: props.document.category_id,
    tags: props.document.tags.map(t => t.id),
});


const selectedCategory = computed(() => {
    return props.categories.find(c => c.id === form.category_id);
});

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const saveDocument = () => {
    // Update tags to be the array of tag IDs
    form.tags = documentTags.value.map(t => t.id);
    
    form.patch(route('documents.update', props.document.id), {
        onSuccess: () => {
            isEditing.value = false;
        }
    });
};

const deleteDocument = () => {
    router.delete(route('documents.destroy', props.document.id), {
        onSuccess: () => {
            // Redirect handled by controller
        }
    });
};

const downloadDocument = () => {
    window.location.href = route('documents.download', props.document.id);
};

const imageError = ref(false);
const handleImageError = () => {
    imageError.value = true;
};

const openPdf = (url: string) => {
    window.open(url, '_blank', 'noopener');
};

const handleTagAdded = (tag: Tag) => {
    // When in edit mode, just update the form data
    if (isEditing.value) {
        form.tags.push(tag.id);
    } else {
        // When not editing, immediately save to server
        router.post(route('documents.tags.store', props.document.id), {
            name: tag.name
        }, {
            preserveScroll: true,
            onSuccess: () => {
                documentTags.value = [...documentTags.value, tag];
            }
        });
    }
};

const handleTagRemoved = (tag: Tag) => {
    // When in edit mode, just update the form data
    if (isEditing.value) {
        form.tags = form.tags.filter(id => id !== tag.id);
    } else {
        // When not editing, immediately remove from server
        router.delete(route('documents.tags.destroy', [props.document.id, tag.id]), {
            preserveScroll: true,
            onSuccess: () => {
                documentTags.value = documentTags.value.filter(t => t.id !== tag.id);
            }
        });
    }
};


const getFileIcon = (fileType: string) => {
    // This would be expanded to show different icons based on file type
    return DocumentIcon;
};

const handleSharesUpdated = (shares: any[]) => {
    // Normalize shares into simplified user list for this page
    props.document.shared_users = (shares || []).map((s: any) => ({
        id: s.shared_with_user?.id ?? s.id,
        name: s.shared_with_user?.name ?? s.name,
        email: s.shared_with_user?.email ?? s.email,
        permission: s.permission,
        shared_at: s.shared_at,
    }));
};

// Adapt shared users for SharingControls expected shape
const sharingControlShares = computed(() => {
    const users = (props.document.shared_users || []) as any[];
    return users.map((u: any) => ({
        shared_with_user: { id: u.id, name: u.name, email: u.email },
        permission: u.permission,
        shared_at: u.shared_at,
    }));
});

// Remove share from the list (fallback in addition to SharingControls)
const removeShare = (userId: number) => {
    if (!confirm('Remove access for this user?')) return;
    router.delete(route('documents.unshare', [props.document.id, userId]), {
        preserveScroll: true,
        onSuccess: () => {
            props.document.shared_users = (props.document.shared_users || []).filter(
                (u: any) => u.id !== userId
            );
        },
    });
};
</script>

<template>
    <Head :title="document.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('documents.index')"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ document.title }}
                    </h2>
                </div>
                <div class="flex items-center space-x-2">
                    <button
                        @click="downloadDocument"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4 mr-2" />
                        Download
                    </button>
                    <SharingControls
                        :file-id="document.id"
                        file-type="document"
                        :current-shares="sharingControlShares"
                        @shares-updated="handleSharesUpdated"
                    />
                    <button
                        v-if="!isEditing"
                        @click="isEditing = true"
                        class="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                    >
                        <PencilIcon class="h-4 w-4 mr-2" />
                        Edit
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Document Viewer -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="aspect-[8.5/11] bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center overflow-hidden relative">
                                    <template v-if="document.file?.url">
                                        <!-- Show image if not PDF -->
                                        <img
                                            v-if="document.file.extension !== 'pdf'"
                                            :src="document.file.url"
                                            class="w-full h-auto"
                                            :alt="document.title"
                                            @error="handleImageError"
                                            :class="{ 'hidden': imageError }"
                                        />
                                        <div v-if="imageError && document.file.extension !== 'pdf'" class="text-center text-gray-500 dark:text-gray-300">
                                            Unable to load image
                                        </div>

                                        <!-- For PDFs, open in new tab -->
                                        <div v-if="document.file.extension === 'pdf'" class="text-center">
                                            <button
                                                @click="openPdf(document.file.pdfUrl || document.file.url)"
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                                            >
                                                Open PDF
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="text-center">
                                            <component :is="getFileIcon('document')" class="h-24 w-24 text-gray-400 mx-auto mb-4" />
                                            <p class="text-gray-500 dark:text-gray-400">No file available</p>
                                        </div>
                                    </template>

                                    <!-- Quick PDF button -->
                                    <div v-if="document.file?.pdfUrl" class="absolute bottom-4 right-4">
                                        <button
                                            @click="openPdf(document.file.pdfUrl)"
                                            class="inline-flex items-center gap-x-2 px-3 py-2 bg-gray-800 rounded-md text-sm font-semibold text-white hover:bg-gray-700"
                                        >
                                            <DocumentIcon class="h-4 w-4" /> View PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Details -->
                    <div class="space-y-6">
                        <!-- Metadata -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                    Document Details
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Title -->
                                    <div>
                                        <InputLabel value="Title" />
                                        <TextInput
                                            v-if="isEditing"
                                            v-model="form.title"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                        <p v-else class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ document.title }}
                                        </p>
                                        <InputError :message="form.errors.title" class="mt-2" />
                                    </div>

                                    <!-- Summary -->
                                    <div>
                                        <InputLabel value="Summary" />
                                        <textarea
                                            v-if="isEditing"
                                            v-model="form.summary"
                                            rows="3"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        />
                                        <p v-else class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {{ document.summary || 'No summary available' }}
                                        </p>
                                        <InputError :message="form.errors.summary" class="mt-2" />
                                    </div>

                                    <!-- Category -->
                                    <div>
                                        <InputLabel value="Category" />
                                        <select
                                            v-if="isEditing"
                                            v-model="form.category_id"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        >
                                            <option :value="null">No category</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                        <div v-else class="mt-1">
                                            <span 
                                                v-if="selectedCategory"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                                :style="{
                                                    backgroundColor: selectedCategory.color + '20',
                                                    color: selectedCategory.color
                                                }"
                                            >
                                                <FolderIcon class="h-4 w-4 mr-1" />
                                                {{ selectedCategory.name }}
                                            </span>
                                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                                No category
                                            </p>
                                        </div>
                                        <InputError :message="form.errors.category_id" class="mt-2" />
                                    </div>

                                    <!-- Tags -->
                                    <div>
                                        <InputLabel value="Tags" />
                                        <div class="mt-1">
                                            <TagManager
                                                v-model="documentTags"
                                                :readonly="!isEditing"
                                                @tag-added="handleTagAdded"
                                                @tag-removed="handleTagRemoved"
                                            />
                                        </div>
                                        <InputError :message="form.errors.tags" class="mt-2" />
                                    </div>

                                    <!-- Timestamps -->
                                    <div class="pt-4 border-t dark:border-gray-700">
                                        <dl class="space-y-2">
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                                    Created
                                                </dt>
                                                <dd class="text-sm text-gray-900 dark:text-white">
                                                    {{ formatDate(document.created_at) }}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                                    Last modified
                                                </dt>
                                                <dd class="text-sm text-gray-900 dark:text-white">
                                                    {{ formatDate(document.updated_at) }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div v-if="isEditing" class="flex justify-end space-x-2 pt-4">
                                        <SecondaryButton @click="isEditing = false">
                                            Cancel
                                        </SecondaryButton>
                                        <PrimaryButton @click="saveDocument">
                                            Save Changes
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shared Users -->
                        <div v-if="document.shared_users.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                    Shared With
                                </h3>
                                <div class="space-y-3">
                                    <div 
                                        v-for="user in document.shared_users" 
                                        :key="user.id"
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ user.name }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ user.email }} • {{ user.permission }}
                                            </p>
                                        </div>
                                        <button
                                            @click="removeShare(user.id)"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <XMarkIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Button -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <button
                                    @click="showDeleteModal = true"
                                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                                >
                                    <TrashIcon class="h-4 w-4 mr-2" />
                                    Delete Document
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                    Delete Document
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete this document? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Cancel
                    </SecondaryButton>
                    <DangerButton @click="deleteDocument">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
