<template>
    <Head title="Tags" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    Tags
                </h2>
                <PrimaryButton @click="openCreateModal">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Tag
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search and Filters -->
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <TextInput
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Search tags..."
                                    class="w-full"
                                    @input="debounceSearch"
                                />
                            </div>
                            <div class="flex gap-2">
                                <select
                                    v-model="filters.sort"
                                    @change="applyFilters"
                                    class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600"
                                >
                                    <option value="desc">Most Used</option>
                                    <option value="asc">Least Used</option>
                                    <option value="name">Name (A-Z)</option>
                                    <option value="-name">Name (Z-A)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tags Grid -->
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-orange-600 dark:border-orange-500">
                    <div class="p-6">
                        <div v-if="tags.data.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No tags</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Get started by creating a new tag.</p>
                            <div class="mt-8">
                                <PrimaryButton @click="openCreateModal">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Create Tag
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="tag in tags.data"
                                :key="tag.id"
                                class="relative group bg-white dark:bg-zinc-900 border border-amber-200 dark:border-zinc-700 rounded-lg p-4 shadow-lg hover:shadow-xl transition-all duration-200"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span
                                                class="w-4 h-4 rounded-full"
                                                :style="{ backgroundColor: tag.color }"
                                            ></span>
                                            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ tag.name }}
                                            </h3>
                                        </div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                            <span v-if="tag.documents_count > 0">
                                                {{ tag.documents_count }} {{ tag.documents_count === 1 ? 'document' : 'documents' }}
                                            </span>
                                            <span v-if="tag.documents_count > 0 && tag.receipts_count > 0" class="mx-1">•</span>
                                            <span v-if="tag.receipts_count > 0">
                                                {{ tag.receipts_count }} {{ tag.receipts_count === 1 ? 'receipt' : 'receipts' }}
                                            </span>
                                            <span v-if="tag.documents_count === 0 && tag.receipts_count === 0">
                                                Not used yet
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions Dropdown -->
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-amber-200 dark:hover:bg-amber-600">
                                                <svg class="w-5 h-5 text-zinc-600 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink as="button" @click="openEditModal(tag)">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </DropdownLink>
                                            <DropdownLink as="button" @click="openMergeModal(tag)">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                </svg>
                                                Merge
                                            </DropdownLink>
                                            <DropdownLink as="button" @click="confirmDelete(tag)" class="text-red-600 dark:text-red-400">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="tags.last_page > 1" class="mt-6">
                            <Pagination 
                                :links="tags.links" 
                                :from="tags.from" 
                                :to="tags.to" 
                                :total="tags.total" 
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Tag Modal -->
        <Modal :show="showTagModal" @close="closeTagModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    {{ editingTag ? 'Edit Tag' : 'Create New Tag' }}
                </h2>

                <div class="mt-6">
                    <InputLabel for="tag-name" value="Tag Name" />
                    <TextInput
                        id="tag-name"
                        v-model="tagForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        :error="tagForm.errors.name"
                        @keyup.enter="saveTag"
                    />
                    <InputError :message="tagForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="tag-color" value="Tag Color" />
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            id="tag-color"
                            v-model="tagForm.color"
                            type="color"
                            class="h-10 w-20 rounded cursor-pointer"
                        />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ tagForm.color }}</span>
                    </div>
                    <InputError :message="tagForm.errors.color" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeTagModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="saveTag" :disabled="tagForm.processing">
                        {{ editingTag ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Merge Tag Modal -->
        <Modal :show="showMergeModal" @close="closeMergeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    Merge Tag
                </h2>

                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Merge <strong>{{ mergingTag?.name }}</strong> into another tag. All items will be moved to the target tag.
                </p>

                <div class="mt-6">
                    <InputLabel for="target-tag" value="Target Tag" />
                    <select
                        id="target-tag"
                        v-model="mergeForm.target_tag_id"
                        class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600"
                    >
                        <option value="">Select a tag...</option>
                        <option
                            v-for="tag in availableTargetTags"
                            :key="tag.id"
                            :value="tag.id"
                        >
                            {{ tag.name }}
                        </option>
                    </select>
                    <InputError :message="mergeForm.errors.target_tag_id" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeMergeModal">
                        Cancel
                    </SecondaryButton>
                    <DangerButton @click="mergeTag" :disabled="mergeForm.processing || !mergeForm.target_tag_id">
                        Merge Tags
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    Delete Tag
                </h2>

                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete <strong>{{ deletingTag?.name }}</strong>? This will remove the tag from all items.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        Cancel
                    </SecondaryButton>
                    <DangerButton @click="deleteTag" :disabled="deleteForm.processing">
                        Delete Tag
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import DangerButton from '@/Components/Buttons/DangerButton.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import InputError from '@/Components/Forms/InputError.vue';
import Modal from '@/Components/Common/Modal.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import DropdownLink from '@/Components/Navigation/DropdownLink.vue';
import Pagination from '@/Components/Common/Pagination.vue';

const props = defineProps({
    tags: Object,
    filters: Object,
});

const filters = ref({
    search: props.filters.search || '',
    sort: props.filters.sort || 'desc',
});

const showTagModal = ref(false);
const editingTag = ref(null);
const showMergeModal = ref(false);
const mergingTag = ref(null);
const showDeleteModal = ref(false);
const deletingTag = ref(null);

const tagForm = useForm({
    name: '',
    color: '#3B82F6',
});

const mergeForm = useForm({
    target_tag_id: '',
});

const deleteForm = useForm({});

const availableTargetTags = computed(() => {
    if (!mergingTag.value) return [];
    return props.tags.data.filter(tag => tag.id !== mergingTag.value.id);
});

let searchTimeout = null;
const debounceSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
};

const applyFilters = () => {
    router.get(route('tags.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const generateRandomColor = () => {
    const colors = [
        '#EF4444', // red
        '#F59E0B', // amber
        '#10B981', // emerald
        '#3B82F6', // blue
        '#6366F1', // indigo
        '#8B5CF6', // violet
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#84CC16', // lime
    ];
    return colors[Math.floor(Math.random() * colors.length)];
};

const openCreateModal = () => {
    editingTag.value = null;
    tagForm.reset();
    tagForm.color = generateRandomColor();
    showTagModal.value = true;
};

const openEditModal = (tag) => {
    editingTag.value = tag;
    tagForm.name = tag.name;
    tagForm.color = tag.color;
    showTagModal.value = true;
};

const closeTagModal = () => {
    showTagModal.value = false;
    editingTag.value = null;
    tagForm.reset();
};

const saveTag = () => {
    if (editingTag.value) {
        tagForm.patch(route('tags.update', editingTag.value), {
            preserveScroll: true,
            onSuccess: () => {
                closeTagModal();
            },
        });
    } else {
        tagForm.post(route('tags.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeTagModal();
            },
        });
    }
};

const openMergeModal = (tag) => {
    mergingTag.value = tag;
    mergeForm.reset();
    showMergeModal.value = true;
};

const closeMergeModal = () => {
    showMergeModal.value = false;
    mergingTag.value = null;
    mergeForm.reset();
};

const mergeTag = () => {
    mergeForm.post(route('tags.merge', mergingTag.value), {
        preserveScroll: true,
        onSuccess: () => {
            closeMergeModal();
        },
    });
};

const confirmDelete = (tag) => {
    deletingTag.value = tag;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingTag.value = null;
};

const deleteTag = () => {
    deleteForm.delete(route('tags.destroy', deletingTag.value), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
        },
    });
};
</script>