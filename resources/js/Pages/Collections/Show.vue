<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { FolderIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FolderLocationPicker from '@/Components/Domain/FolderLocationPicker.vue';
import CollectionCard from '@/Components/Domain/CollectionCard.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import Breadcrumbs from '@/Components/Common/Breadcrumbs.vue';
import Modal from '@/Components/Common/Modal.vue';
import IconPicker from '@/Components/Forms/IconPicker.vue';
import ColorPicker from '@/Components/Forms/ColorPicker.vue';
import FileWorkspace from '@/Components/Domain/FileWorkspace.vue';
import PublicLinkManager from '@/Components/Domain/PublicLinkManager.vue';

const props = defineProps({
    collection: { type: Object, required: true },
    stats: { type: Object, required: true },
    workspaceFiles: { type: Array, default: () => [] },
    workspaceTags: { type: Array, default: () => [] },
    children: { type: Object, default: () => ({ data: [] }) },
    filePagination: { type: Array, default: () => [] },
    fileMeta: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    treePreview: { type: Object, default: null },
    isOwner: { type: Boolean, default: false },
    breadcrumbs: { type: Array, default: () => [] },
    publicLinks: { type: Array, default: () => [] },
});
const search = ref(props.filters.search || '');
const sort = ref(props.filters.sort || 'name');
watch(() => props.filters, filters => { search.value = filters.search || ''; sort.value = filters.sort || 'name'; });
const loading = ref(false);
const error = ref('');
const removing = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const editErrors = ref({});
const editedCollection = ref({});
const searchInput = ref(null);
const parentCrumb = computed(() => props.collection.parent_id && props.breadcrumbs.length > 3 ? props.breadcrumbs.at(-2) : null);
function browse(clear = false) {
    if (clear) search.value = '';
    error.value = '';
    router.get(route('collections.show', props.collection.id), { search: search.value || undefined, sort: sort.value }, {
        preserveState: true, preserveScroll: true,
        onStart: () => { loading.value = true; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { loading.value = false; },
    });
}
function edit() {
    editedCollection.value = {
        name: props.collection.name, parent_id: props.collection.parent_id,
        description: props.collection.description || '', icon: props.collection.icon, color: props.collection.color,
    };
    editErrors.value = {};
    isEditing.value = true;
}
function save() {
    if (saving.value) return;
    saving.value = true;
    editErrors.value = {};
    router.patch(route('collections.update', props.collection.id), editedCollection.value, {
        preserveScroll: true,
        onSuccess: () => { isEditing.value = false; },
        onError: errors => { editErrors.value = errors; },
        onFinish: () => { saving.value = false; },
    });
}
function removeFile(file) {
    if (removing.value || !confirm(`Remove “${file.original_name || file.name}” from “${props.collection.name}”? The original file and its extracted records will stay in your library.`)) return;
    removing.value = true;
    error.value = '';
    router.delete(route('collections.files.remove', props.collection.id), {
        data: { file_ids: [file.id] }, preserveScroll: true,
        onSuccess: () => nextTick(() => searchInput.value?.focus()),
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { removing.value = false; },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="collection.name" />
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <Breadcrumbs v-if="breadcrumbs.length" :crumbs="breadcrumbs" class="mb-3" />
                    <div class="flex items-start gap-3">
                        <FolderIcon class="mt-1 h-7 w-7 shrink-0 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
                        <div class="min-w-0">
                            <h1 class="page-heading [overflow-wrap:anywhere]">{{ collection.name }}</h1>
                            <p v-if="collection.description" class="page-description [overflow-wrap:anywhere]">{{ collection.description }}</p>
                            <p v-if="collection.is_archived || !isOwner" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ collection.is_archived ? 'Archived folder' : 'Shared with you' }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <button v-if="isOwner" type="button" class="workspace-button" @click="edit">Edit Collection</button>
                    <Link v-if="isOwner" :href="route('documents.upload', { collection_ids: [collection.id] })" class="workspace-primary">Upload files</Link>
                </div>
            </div>
        </template>

        <div class="flex min-w-0 flex-col gap-6">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <Link v-if="parentCrumb" :href="parentCrumb.href" class="inline-flex min-h-11 items-center underline underline-offset-4 [overflow-wrap:anywhere]">Back to {{ parentCrumb.label }}</Link>
                <Link :href="route(isOwner ? 'collections.index' : 'collections.shared')" class="inline-flex min-h-11 items-center underline underline-offset-4">{{ isOwner ? 'All Collections' : 'Shared collections' }}</Link>
                <Link v-if="isOwner" :href="route('collections.organization.index')" class="inline-flex min-h-11 items-center text-zinc-500 underline underline-offset-4 dark:text-zinc-400">Review recommendations</Link>
            </div>

            <form role="search" aria-label="Find folder contents" class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="browse()">
                <div class="min-w-0 flex-1">
                    <label for="collection-search" class="mb-1 block text-sm font-medium">Search this folder</label>
                    <input id="collection-search" ref="searchInput" v-model="search" type="search" class="workspace-filter w-full" placeholder="Filename or subfolder name" aria-describedby="collection-scope" />
                </div>
                <div>
                    <label for="collection-sort" class="mb-1 block text-sm font-medium">Sort contents</label>
                    <select id="collection-sort" v-model="sort" class="workspace-filter w-full" @change="browse()">
                        <option value="name">Name A–Z</option><option value="-name">Name Z–A</option>
                        <option value="created">Newest first</option><option value="-created">Oldest first</option>
                    </select>
                </div>
                <button type="submit" class="workspace-button" :disabled="loading">Search</button>
                <button v-if="filters.search" type="button" class="workspace-button" :disabled="loading" @click="browse(true)">Clear search</button>
            </form>
            <p id="collection-scope" class="-mt-4 text-sm text-zinc-500 dark:text-zinc-400">Searches original filenames and subfolder names directly here, across all pages. Open a subfolder to search inside it.</p>
            <p v-if="loading" role="status" class="text-sm text-zinc-500">Loading folder contents…</p>
            <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>

            <section aria-label="Subfolders" :aria-busy="loading" class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="section-heading">Subfolders <span class="font-normal text-zinc-500">{{ children.total ?? children.data.length }}</span></h2>
                    <Link v-if="isOwner" :href="route('collections.index', { parent_id: collection.id })" class="inline-flex min-h-11 items-center text-sm underline underline-offset-4">Manage subfolders</Link>
                </div>
                <div v-if="children.data.length" class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                    <CollectionCard v-for="child in children.data" :key="child.id" :collection="child" :show-edit="false" :show-archive="false" :show-delete="false" :children-href="route('collections.show', child.id)" />
                </div>
                <p v-else class="rounded-lg border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ filters.search ? 'No subfolder names match your search.' : 'No subfolders here yet.' }}</p>
                <Pagination v-if="children.last_page > 1" :links="children.links" :from="children.from" :to="children.to" :total="children.total" class="mt-4" />
            </section>

            <section aria-label="Files in this folder" class="min-w-0">
                <h2 class="section-heading mb-3">Files <span class="font-normal text-zinc-500">{{ fileMeta.total ?? stats.total_files }}</span></h2>
                <FileWorkspace :files="workspaceFiles" :tags="workspaceTags" :loading="loading || removing" :empty-title="fileMeta.total > 0 ? 'No files on this page' : filters.search ? 'No filenames match your search' : 'No files directly in this collection'" :empty-description="fileMeta.total > 0 ? 'Use the page controls below to return to your files.' : filters.search ? 'Try another filename or clear the search.' : 'Upload files here or open a subfolder above.'">
                    <template v-if="isOwner" #file-context="{ file, dirty }">
                        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <button type="button" class="workspace-button" :disabled="removing || dirty" @click="removeFile(file)">Remove from this folder</button>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ dirty ? 'Save or cancel your edits first.' : 'The original stays in your library.' }}</p>
                        </div>
                    </template>
                </FileWorkspace>
                <Pagination v-if="filePagination?.length > 3" :links="filePagination" :from="fileMeta.from" :to="fileMeta.to" :total="fileMeta.total" class="mt-4" />
            </section>

            <details class="workspace-surface p-4" data-folder-details>
                <summary class="cursor-pointer text-sm font-medium">Folder details</summary>
                <dl class="mt-4 grid gap-3 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-400">
                    <div class="flex gap-2"><dt>Files directly here</dt><dd class="font-semibold">{{ stats.total_files }}</dd></div>
                    <div v-if="treePreview" class="flex gap-2"><dt>Files including subfolders</dt><dd class="font-semibold">{{ treePreview.files }}</dd></div>
                    <div class="flex gap-2"><dt>Documents directly here</dt><dd>{{ stats.documents_count }}</dd></div>
                    <div class="flex gap-2"><dt>Receipts directly here</dt><dd>{{ stats.receipts_count }}</dd></div>
                    <div class="flex gap-2"><dt>Invoices directly here</dt><dd>{{ stats.invoices_count }}</dd></div>
                </dl>
            </details>
            <details v-if="isOwner" class="workspace-surface p-4" data-public-sharing>
                <summary class="cursor-pointer text-sm font-medium">Public sharing</summary>
                <PublicLinkManager :collection-id="collection.id" :public-links="publicLinks" :flash-public-link="$page.props.flash?.publicLink" class="mt-4" />
            </details>
        </div>

        <Modal :show="isEditing" :closeable="!saving" max-width="lg" @close="isEditing = false" v-slot="{ titleId }">
            <form class="flex flex-col gap-5 p-5 sm:p-6" @submit.prevent="save">
                <h2 :id="titleId" class="section-heading">Edit collection</h2>
                <div>
                    <label for="collection-name" class="mb-1 block text-sm font-medium">Name</label>
                    <input id="collection-name" v-model="editedCollection.name" class="workspace-filter w-full" required maxlength="100" :aria-invalid="!!editErrors.name" />
                </div>
                <FolderLocationPicker v-model="editedCollection.parent_id" :exclude-id="collection.id" />
                <p v-if="treePreview" class="text-sm text-zinc-500 dark:text-zinc-400">Moving this folder also moves its {{ treePreview.folders - 1 }} subfolders. {{ treePreview.sharing_scope }}</p>
                <div>
                    <label for="collection-description" class="mb-1 block text-sm font-medium">Description</label>
                    <textarea id="collection-description" v-model="editedCollection.description" rows="2" maxlength="500" class="workspace-filter w-full" />
                </div>
                <IconPicker v-model="editedCollection.icon" label="Collection icon" />
                <ColorPicker v-model="editedCollection.color" label="Collection color" />
                <div v-if="Object.keys(editErrors).length" role="alert" class="text-sm text-red-700 dark:text-red-400"><p v-for="(message, field) in editErrors" :key="field">{{ message }}</p></div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="workspace-button" :disabled="saving" @click="isEditing = false">Cancel</button>
                    <button type="submit" class="workspace-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save changes' }}</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
