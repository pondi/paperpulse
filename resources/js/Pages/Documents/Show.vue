<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { 
    DocumentIcon,
    TagIcon,
    FolderIcon,
    ShareIcon,
    DownloadIcon,
    TrashIcon,
    PencilIcon,
    XMarkIcon,
    PlusIcon,
    UserPlusIcon
} from '@heroicons/vue/24/outline';

interface Tag {
    id: number;
    name: string;
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

interface Document {
    id: number;
    title: string;
    content: string;
    summary: string;
    file_name: string;
    file_type: string;
    file_url: string;
    size: number;
    category_id: number | null;
    tags: Tag[];
    shared_users: SharedUser[];
    created_at: string;
    updated_at: string;
}

interface Props {
    document: Document;
    categories: Category[];
    available_tags: Tag[];
}

const props = defineProps<Props>();

const isEditing = ref(false);
const showShareModal = ref(false);
const showDeleteModal = ref(false);
const newTag = ref('');

const form = useForm({
    title: props.document.title,
    summary: props.document.summary,
    category_id: props.document.category_id,
    tags: props.document.tags.map(t => t.id),
});

const shareForm = useForm({
    email: '',
    permission: 'view' as 'view' | 'edit',
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
    form.put(route('documents.update', props.document.id), {
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

const addTag = () => {
    if (newTag.value.trim()) {
        router.post(route('documents.tags.store', props.document.id), {
            name: newTag.value.trim()
        }, {
            preserveScroll: true,
            onSuccess: () => {
                newTag.value = '';
            }
        });
    }
};

const removeTag = (tagId: number) => {
    form.tags = form.tags.filter(id => id !== tagId);
};

const shareDocument = () => {
    shareForm.post(route('documents.share', props.document.id), {
        preserveScroll: true,
        onSuccess: () => {
            shareForm.reset();
            showShareModal.value = false;
        }
    });
};

const removeShare = (userId: number) => {
    router.delete(route('documents.unshare', [props.document.id, userId]), {
        preserveScroll: true
    });
};

const getFileIcon = (fileType: string) => {
    // This would be expanded to show different icons based on file type
    return DocumentIcon;
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
                        <DownloadIcon class="h-4 w-4 mr-2" />
                        Download
                    </button>
                    <button
                        @click="showShareModal = true"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        <ShareIcon class="h-4 w-4 mr-2" />
                        Share
                    </button>
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
                                <!-- This is where the document viewer would go -->
                                <!-- For now, showing a placeholder -->
                                <div class="aspect-[8.5/11] bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                    <div class="text-center">
                                        <component :is="getFileIcon(document.file_type)" class="h-24 w-24 text-gray-400 mx-auto mb-4" />
                                        <p class="text-gray-500 dark:text-gray-400">
                                            {{ document.file_name }}
                                        </p>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">
                                            {{ formatFileSize(document.size) }}
                                        </p>
                                        <a
                                            :href="document.file_url"
                                            target="_blank"
                                            class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                                        >
                                            Open Document
                                        </a>
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
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            <span 
                                                v-for="tag in document.tags" 
                                                :key="tag.id"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200"
                                            >
                                                <TagIcon class="h-4 w-4 mr-1" />
                                                {{ tag.name }}
                                                <button
                                                    v-if="isEditing"
                                                    @click="removeTag(tag.id)"
                                                    class="ml-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                >
                                                    <XMarkIcon class="h-4 w-4" />
                                                </button>
                                            </span>
                                            <div v-if="isEditing" class="inline-flex items-center">
                                                <TextInput
                                                    v-model="newTag"
                                                    type="text"
                                                    placeholder="Add tag..."
                                                    class="h-8 text-sm"
                                                    @keyup.enter="addTag"
                                                />
                                                <button
                                                    @click="addTag"
                                                    class="ml-1 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                >
                                                    <PlusIcon class="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>
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

        <!-- Share Modal -->
        <Modal :show="showShareModal" @close="showShareModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Share Document
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="email" value="Email address" />
                        <TextInput
                            id="email"
                            v-model="shareForm.email"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="user@example.com"
                        />
                        <InputError :message="shareForm.errors.email" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="permission" value="Permission" />
                        <select
                            id="permission"
                            v-model="shareForm.permission"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        >
                            <option value="view">View only</option>
                            <option value="edit">Can edit</option>
                        </select>
                        <InputError :message="shareForm.errors.permission" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showShareModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="shareDocument" :disabled="shareForm.processing">
                        <UserPlusIcon class="h-4 w-4 mr-2" />
                        Share
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

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