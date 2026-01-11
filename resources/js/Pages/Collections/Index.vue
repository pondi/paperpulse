<template>
    <Head title="Collections" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    Collections
                </h2>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-bold rounded-md shadow-sm hover:shadow text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Collection
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search and Filters -->
                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <input
                                    v-model="searchQuery"
                                    @input="debounceSearch"
                                    type="search"
                                    placeholder="Search collections..."
                                    class="w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600"
                                />
                            </div>
                            <div class="flex gap-2">
                                <select
                                    v-model="showArchived"
                                    @change="applyFilters"
                                    class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600"
                                >
                                    <option :value="false">Active Collections</option>
                                    <option :value="true">Archived Collections</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collections Grid -->
                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-blue-600">
                    <div class="p-6">
                        <div v-if="collections.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <CollectionCard
                                v-for="collection in collections.data"
                                :key="collection.id"
                                :collection="collection"
                                :show-edit="true"
                                :show-archive="true"
                                :show-delete="true"
                                @click="viewCollection(collection)"
                                @edit="editCollection(collection)"
                                @archive="archiveCollection(collection)"
                                @unarchive="unarchiveCollection(collection)"
                                @delete="deleteCollection(collection)"
                            />
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No collections</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Get started by creating a new collection to organize your files.</p>
                            <div class="mt-8">
                                <button
                                    @click="openCreateModal"
                                    type="button"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-bold rounded-md shadow-sm hover:shadow text-white bg-blue-600 hover:bg-blue-700 transition-all duration-200"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Create Collection
                                </button>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="collections.last_page > 1" class="mt-6">
                            <Pagination
                                :links="collections.links"
                                :from="collections.from"
                                :to="collections.to"
                                :total="collections.total"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    {{ editingCollection ? 'Edit Collection' : 'Create New Collection' }}
                </h2>

                <form @submit.prevent="saveCollection" class="mt-6 space-y-6">
                    <div>
                        <label for="collection-name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Collection Name
                        </label>
                        <input
                            id="collection-name"
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="collection-description" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Description (optional)
                        </label>
                        <textarea
                            id="collection-description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        ></textarea>
                    </div>

                    <IconPicker
                        v-model="form.icon"
                        label="Icon"
                    />

                    <ColorPicker
                        v-model="form.color"
                        label="Color"
                    />

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="inline-flex justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50"
                        >
                            {{ editingCollection ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CollectionCard from '@/Components/Domain/CollectionCard.vue';
import Modal from '@/Components/Common/Modal.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import IconPicker from '@/Components/Forms/IconPicker.vue';
import ColorPicker from '@/Components/Forms/ColorPicker.vue';

const props = defineProps({
    collections: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

const searchQuery = ref(props.filters.search || '');
const showArchived = ref(props.filters.archived || false);
const showModal = ref(false);
const editingCollection = ref(null);

const form = useForm({
    name: '',
    description: '',
    icon: 'folder',
    color: '#3B82F6'
});

let searchTimeout = null;
const debounceSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
};

const applyFilters = () => {
    router.get(route('collections.index'), {
        search: searchQuery.value,
        archived: showArchived.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

const openCreateModal = () => {
    editingCollection.value = null;
    form.reset();
    form.icon = 'folder';
    form.color = '#3B82F6';
    showModal.value = true;
};

const editCollection = (collection) => {
    editingCollection.value = collection;
    form.name = collection.name;
    form.description = collection.description || '';
    form.icon = collection.icon;
    form.color = collection.color;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingCollection.value = null;
    form.reset();
};

const saveCollection = () => {
    if (editingCollection.value) {
        form.patch(route('collections.update', editingCollection.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('collections.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    }
};

const viewCollection = (collection) => {
    router.visit(route('collections.show', collection.id));
};

const archiveCollection = (collection) => {
    if (confirm(`Archive "${collection.name}"? It will be hidden but can be restored later.`)) {
        router.post(route('collections.archive', collection.id), {}, {
            preserveScroll: true
        });
    }
};

const unarchiveCollection = (collection) => {
    router.post(route('collections.unarchive', collection.id), {}, {
        preserveScroll: true
    });
};

const deleteCollection = (collection) => {
    if (confirm(`Delete "${collection.name}"? This action cannot be undone.`)) {
        router.delete(route('collections.destroy', collection.id), {
            preserveScroll: true
        });
    }
};
</script>
