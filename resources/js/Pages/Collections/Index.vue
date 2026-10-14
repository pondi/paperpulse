<template>
    <AuthenticatedLayout>
        <Head :title="parentName ? `Subfolders in ${parentName}` : 'Collections'" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1 basis-48">
                    <h1 class="text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">{{ parentName ? `Subfolders in ${parentName}` : 'Collections' }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Organize your files into folders and subfolders.</p>
                </div>
                <button type="button" class="workspace-primary" @click="openCreateModal">
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    {{ parentName ? 'Create Subfolder' : 'Create Collection' }}
                </button>
            </div>
        </template>

        <div class="space-y-5">
            <Breadcrumbs :crumbs="folderCrumbs" />
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <Link v-if="parentName" :href="breadcrumbs[breadcrumbs.length - 1].href" class="min-w-0 text-zinc-700 underline underline-offset-4 [overflow-wrap:anywhere] dark:text-zinc-300">Back to {{ parentName }}</Link>
                <Link :href="route('collections.organization.index')" class="text-zinc-600 underline underline-offset-4 dark:text-zinc-400">Review folder recommendations</Link>
            </div>
            <p v-if="actionError" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ actionError }}</p>
            <p v-if="actionMessage" role="status" class="text-sm text-zinc-700 dark:text-zinc-300">{{ actionMessage }}</p>

            <form role="search" aria-label="Find folders" class="workspace-surface grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_auto_auto]" @submit.prevent="applyFilters">
                <div class="min-w-0 sm:col-span-2 xl:col-span-1">
                    <label for="folder-search" class="mb-1 block text-sm font-medium">{{ parentName ? 'Search these subfolders' : 'Search top-level folders' }}</label>
                    <input id="folder-search" v-model="searchQuery" @input="debounceSearch" type="search" maxlength="255" placeholder="Name or description" class="workspace-filter w-full" />
                </div>
                <div class="min-w-0">
                    <label for="folder-status" class="mb-1 block text-sm font-medium">Show</label>
                    <select id="folder-status" v-model="showArchived" @change="applyFilters" class="workspace-filter w-full">
                        <option :value="false">Active folders</option>
                        <option :value="true">All folders (including archived)</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="folder-sort" class="mb-1 block text-sm font-medium">Sort folders</label>
                    <select id="folder-sort" v-model="sort" @change="applyFilters" class="workspace-filter w-full">
                        <option value="name">Name: A–Z</option><option value="-name">Name: Z–A</option>
                        <option value="files">Most direct files</option><option value="-files">Fewest direct files</option>
                        <option value="created">Newest first</option><option value="-created">Oldest first</option>
                    </select>
                </div>
            </form>
            <div v-if="requestError" role="alert" class="flex flex-wrap items-center gap-3 text-sm text-red-700 dark:text-red-400">
                <p>{{ requestError }}</p><button class="workspace-button" @click="applyFilters">Retry</button>
            </div>
            <section aria-label="Folders" :aria-busy="loading" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <p role="status">{{ loading ? 'Loading folders…' : `${collections.total ?? 0} folder${collections.total === 1 ? '' : 's'}` }}</p>
                    <button v-if="searchQuery || showArchived" class="workspace-button" @click="resetFilters">Reset filters</button>
                </div>
                <ul v-if="collections.data.length" class="grid items-start gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    <li v-for="collection in collections.data" :key="collection.id" class="min-w-0">
                        <CollectionCard :collection="collection" :busy="actionBusy" @edit="editCollection" @archive="runAction($event, 'Archive')" @unarchive="runAction($event, 'Restore')" @delete="runAction($event, 'Delete')" />
                    </li>
                </ul>
                <div v-else-if="!loading" class="workspace-surface flex flex-col items-center gap-3 px-4 py-12 text-center">
                    <FolderIcon class="h-10 w-10 text-zinc-400" aria-hidden="true" />
                    <h2 class="max-w-full text-lg font-medium [overflow-wrap:anywhere]">{{ searchQuery ? 'No folders match your search' : parentName ? `No subfolders in ${parentName}` : 'No collections' }}</h2>
                    <p class="max-w-md text-sm text-zinc-600 dark:text-zinc-400">{{ searchQuery ? 'Try another name or description, or reset the filters.' : 'Create a folder to keep related files together. You can add files after creating it.' }}</p>
                    <button v-if="searchQuery" class="workspace-button" @click="resetFilters">Clear search</button>
                    <button v-else class="workspace-primary" @click="openCreateModal">{{ parentName ? 'Create Subfolder' : 'Create Collection' }}</button>
                </div>
                <nav v-if="collections.last_page > 1" aria-label="Folder pagination" class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-800">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ collections.from }}–{{ collections.to }} of {{ collections.total }} folders · Page {{ collections.current_page }} of {{ collections.last_page }}</span>
                    <div class="flex gap-2">
                        <Link v-if="collections.prev_page_url" :href="collections.prev_page_url" class="workspace-button" @click="cancelSearch">Previous</Link>
                        <Link v-if="collections.next_page_url" :href="collections.next_page_url" class="workspace-button" @click="cancelSearch">Next</Link>
                    </div>
                </nav>
            </section>
        </div>

        <Modal :show="showModal" :closeable="!form.processing" @close="closeModal" v-slot="{ titleId }">
            <div class="p-6">
                <h2 :id="titleId" class="break-words text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    {{ editingCollection ? 'Edit Collection' : parentName ? `Create subfolder in ${parentName}` : 'Create New Collection' }}
                </h2>

                <form @submit.prevent="saveCollection" class="mt-6 space-y-6" :aria-busy="form.processing">
                    <div v-if="form.hasErrors || saveError" role="alert" class="text-sm text-red-700 dark:text-red-400">
                        <p v-if="saveError">{{ saveError }}</p>
                        <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                    </div>
                    <div>
                        <label for="collection-name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Collection Name
                        </label>
                        <input
                            id="collection-name"
                            v-model="form.name"
                            type="text"
                            maxlength="100"
                            :aria-invalid="!!form.errors.name"
                            :aria-describedby="form.errors.name ? 'collection-name-error' : undefined"
                            required
                            class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100  focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                            :class="{ 'border-red-300': form.errors.name }"
                        />
                        <p id="collection-name-error" v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="collection-description" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Description (optional)
                        </label>
                        <textarea
                            id="collection-description"
                            v-model="form.description"
                            rows="3"
                            maxlength="500"
                            :aria-invalid="!!form.errors.description"
                            class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100  focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
                        ></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-zinc-900 dark:text-zinc-100"><input v-model="form.is_pinned" type="checkbox" />Keep this folder fixed during automatic organization</label>
                    <FolderLocationPicker v-model="form.parent_id" :exclude-id="editingCollection?.id" />
                    <p v-if="form.errors.parent_id" class="text-sm text-red-600 dark:text-red-400">{{ form.errors.parent_id }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Moving a folder keeps its existing sharing permissions. Subfolders must be shared separately.</p>
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
                            :disabled="form.processing"
                            class="inline-flex justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100  ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex justify-center rounded-md bg-zinc-900 dark:bg-orange-600 px-3 py-2 text-sm font-bold text-white  hover:bg-zinc-800 dark:hover:bg-orange-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving…' : editingCollection ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FolderIcon, PlusIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Common/Breadcrumbs.vue';
import Modal from '@/Components/Common/Modal.vue';
import CollectionCard from '@/Components/Domain/CollectionCard.vue';
import FolderLocationPicker from '@/Components/Domain/FolderLocationPicker.vue';
import IconPicker from '@/Components/Forms/IconPicker.vue';
import ColorPicker from '@/Components/Forms/ColorPicker.vue';

const props = defineProps({
    collections: { type: Object, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});
const parentName = computed(() => props.filters.parent_id ? props.breadcrumbs.at(-1)?.label : null);
const folderCrumbs = computed(() => parentName.value
    ? [{ label: 'Collections', href: route('collections.index') }, ...props.breadcrumbs, { label: 'Subfolders' }]
    : [{ label: 'Collections' }]);
const searchQuery = ref(props.filters.search || '');
const showArchived = ref(props.filters.archived || false);
const sort = ref(props.filters.sort || 'name');
const loading = ref(false);
const requestError = ref('');
const actionError = ref('');
const actionMessage = ref('');
const actionBusy = ref(false);
const saveError = ref('');
const showModal = ref(false);
const editingCollection = ref(null);
const form = useForm({ parent_id: props.filters.parent_id ?? null, is_pinned: false, name: '', description: '', icon: 'folder', color: '#64748B' });
let searchTimeout;
let cancelVisit;
let sequence = 0;

function cancelSearch() {
    clearTimeout(searchTimeout);
    cancelVisit?.cancel();
}
onBeforeUnmount(cancelSearch);
watch(() => props.filters, filters => {
    if (Object.keys(props.errors).length) return;
    clearTimeout(searchTimeout);
    searchQuery.value = filters.search || '';
    showArchived.value = filters.archived || false;
    sort.value = filters.sort || 'name';
});
function debounceSearch() {
    cancelSearch();
    searchTimeout = setTimeout(applyFilters, 300);
}
function applyFilters() {
    cancelSearch();
    const request = ++sequence;
    router.get(route('collections.index'), {
        search: searchQuery.value, archived: showArchived.value ? 1 : 0, sort: sort.value, parent_id: props.filters.parent_id ?? null,
    }, {
        preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true; requestError.value = ''; },
        onCancelToken: token => { cancelVisit = token; },
        onError: errors => { requestError.value = Object.values(errors).join(' '); },
        onNetworkError: () => { requestError.value = 'Could not load folders. Check your connection and try again.'; return false; },
        onHttpException: () => { requestError.value = 'Could not load folders. Please try again.'; return false; },
        onFinish: () => { if (request === sequence) { loading.value = false; cancelVisit = null; } },
    });
}
function resetFilters() {
    searchQuery.value = '';
    showArchived.value = false;
    applyFilters();
}
function openCreateModal() {
    cancelSearch();
    editingCollection.value = null;
    form.reset();
    form.clearErrors();
    saveError.value = '';
    form.parent_id = props.filters.parent_id ?? null;
    showModal.value = true;
}
function editCollection(collection) {
    cancelSearch();
    editingCollection.value = collection;
    form.clearErrors();
    saveError.value = '';
    Object.assign(form, { parent_id: collection.parent_id, is_pinned: collection.is_pinned, name: collection.name,
        description: collection.description || '', icon: collection.icon || 'folder', color: collection.color || '#64748B' });
    showModal.value = true;
}
function hideModal() {
    const id = editingCollection.value?.id;
    showModal.value = false;
    editingCollection.value = null;
    form.reset();
    form.clearErrors();
    if (id) nextTick(() => document.querySelector(`[data-folder-id="${id}"] button[aria-haspopup="menu"]`)?.focus());
}
function closeModal() {
    if (!form.processing) hideModal();
}
function saveCollection() {
    if (form.processing) return;
    saveError.value = '';
    const options = {
        preserveScroll: true,
        onSuccess: hideModal,
        onNetworkError: () => { saveError.value = 'Could not save the folder. Check your connection and try again.'; return false; },
        onHttpException: () => { saveError.value = 'Could not save the folder. Please try again.'; return false; },
    };
    if (editingCollection.value) form.patch(route('collections.update', editingCollection.value.id), options);
    else form.post(route('collections.store'), options);
}
async function runAction(collection, action) {
    if (actionBusy.value) return;
    cancelSearch();
    actionBusy.value = true;
    actionError.value = '';
    actionMessage.value = '';
    try {
        const { data } = await axios.get(route('collections.tree-preview', collection.id));
        if (action === 'Delete' && !data.can_delete) {
            actionError.value = 'Move or delete the subfolders before deleting this folder.';
            actionBusy.value = false;
            return;
        }
        if (!confirm(`${action} "${collection.name}"? This affects ${data.folders} folder(s) and ${data.files} unique file(s). Original files are kept. ${data.sharing_scope}`)) {
            actionBusy.value = false;
            return;
        }
    } catch {
        actionError.value = 'The folder preview could not be loaded. Please try again.';
        actionBusy.value = false;
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => { actionMessage.value = `${collection.name} ${action === 'Delete' ? 'deleted' : action === 'Restore' ? 'restored' : 'archived'}.`; },
        onError: errors => { actionError.value = Object.values(errors).join(' '); },
        onNetworkError: () => { actionError.value = 'Could not update the folder. Check your connection and try again.'; return false; },
        onHttpException: () => { actionError.value = 'Could not update the folder. Please try again.'; return false; },
        onFinish: () => { actionBusy.value = false; },
    };
    if (action === 'Delete') router.delete(route('collections.destroy', collection.id), options);
    else router.post(route(action === 'Restore' ? 'collections.unarchive' : 'collections.archive', collection.id), {}, options);
}
</script>
