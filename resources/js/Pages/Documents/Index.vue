<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Common/Modal.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import DocumentDrawer from '@/Components/Domain/DocumentDrawer.vue';
import { DocumentIcon, Squares2X2Icon, ListBulletIcon } from '@heroicons/vue/24/outline';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ documents: { type: Object, required: true }, categories: { type: Array, default: () => [] }, filters: { type: Object, default: () => ({}) } });
const { formatDate, formatCurrency } = useDateFormatter();
const search = ref(props.filters.search || '');
const categoryId = ref(props.filters.category || '');
const tag = ref(props.filters.tag || '');
const viewMode = ref('grid');
const selectedDocuments = ref([]);
const selectedDocument = ref(null);
const showDeleteModal = ref(false);
const busy = ref(false);
const loading = ref(false);
const error = ref('');
const thumbnailErrors = ref(new Set());
const activeCategory = computed(() => props.categories.find(category => category.id === Number(props.filters.category)));
const filtered = computed(() => !!(props.filters.search || props.filters.category || props.filters.tag));
const allSelected = computed(() => props.documents.data.length > 0 && selectedDocuments.value.length === props.documents.data.length);
watch(() => props.documents.data, () => { selectedDocuments.value = []; });
watch(() => props.filters, filters => { search.value = filters.search || ''; categoryId.value = filters.category || ''; tag.value = filters.tag || ''; });
function browse() {
    error.value = '';
    router.get(route('documents.index'), { ...props.filters, search: search.value || undefined, category: categoryId.value || undefined, tag: tag.value || undefined }, {
        preserveState: true, preserveScroll: true,
        onStart: () => { loading.value = true; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { loading.value = false; },
    });
}
function toggleAll() { selectedDocuments.value = allSelected.value ? [] : props.documents.data.map(document => document.file_id); }
function removeOne(document) { selectedDocuments.value = [document.file_id]; error.value = ''; showDeleteModal.value = true; }
function deleteSelected() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    router.delete(route('documents.destroy-bulk'), {
        data: { ids: selectedDocuments.value }, preserveScroll: true,
        onSuccess: () => { selectedDocuments.value = []; showDeleteModal.value = false; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { busy.value = false; },
    });
}
function formatFileSize(bytes) {
    if (bytes == null) return 'Size unavailable';
    if (bytes === 0) return '0 Bytes';
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), 3);
    return `${parseFloat((bytes / Math.pow(1024, index)).toFixed(2))} ${['Bytes', 'KB', 'MB', 'GB'][index]}`;
}
function entityLabel(type) { return ({ document: 'Document', invoice: 'Invoice', contract: 'Contract', voucher: 'Voucher', warranty: 'Warranty', bankstatement: 'Statement', bank_statement: 'Statement', returnpolicy: 'Return policy', return_policy: 'Return policy' })[type] || 'Document'; }
function getShowRoute(document) {
    const routeName = ({ contract: 'contracts.show', invoice: 'invoices.show', voucher: 'vouchers.show', bank_statement: 'bank-statements.show', bankstatement: 'bank-statements.show', document: 'documents.show' })[document.entity_type || 'document'];
    return routeName ? route(routeName, document.id) : route('files.show', document.file_id);
}
function entitySummary(document) {
    const detail = document.entity_details || {};
    const dateRange = [detail.effective_date, detail.expiration_date].filter(Boolean).map(value => formatDate(value)).join(' – ');
    switch (document.entity_type) {
        case 'invoice': return [detail.from, detail.date && formatDate(detail.date), detail.total != null && formatCurrency(detail.total, detail.currency), detail.status].filter(Boolean);
        case 'contract': return [detail.type, dateRange, detail.status].filter(Boolean);
        case 'voucher': return [detail.code, detail.expiry_date && `Expires ${formatDate(detail.expiry_date)}`].filter(Boolean);
        case 'warranty': return [detail.product, detail.expiry_date && `Expires ${formatDate(detail.expiry_date)}`].filter(Boolean);
        case 'bank_statement': case 'bankstatement': return [detail.date && formatDate(detail.date), detail.closing_balance != null && `Balance ${formatCurrency(detail.closing_balance, detail.currency)}`].filter(Boolean);
        default: return [];
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Documents" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 basis-full sm:flex-1 sm:basis-0"><h1 class="page-heading">Documents</h1><p class="page-description">Browse processed documents, agreements and financial records.</p></div><Link :href="route('documents.upload')" class="workspace-primary">Upload Document</Link></div>
        </template>
        <div class="flex min-w-0 flex-col gap-5">
            <form role="search" aria-label="Find documents" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_minmax(10rem,auto)_minmax(8rem,auto)_auto]" @submit.prevent="browse">
                <div class="min-w-0"><label for="document-search" class="field-label mb-1">Search filenames</label><input id="document-search" v-model="search" type="search" class="workspace-filter w-full" placeholder="Original filename" /></div>
                <div class="min-w-0"><label for="document-category" class="field-label mb-1">Category</label><select id="document-category" v-model="categoryId" class="workspace-filter w-full" @change="browse"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
                <div class="min-w-0"><label for="document-tag" class="field-label mb-1">Tag</label><input id="document-tag" v-model="tag" class="workspace-filter w-full" placeholder="Exact tag name" /></div>
                <button type="submit" class="workspace-button self-end" :disabled="loading">Search</button>
            </form>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="flex flex-wrap items-center gap-3"><p v-if="activeCategory">Category: <strong class="font-medium">{{ activeCategory.name }}</strong></p><Link v-if="filtered" :href="route('documents.index')" class="underline underline-offset-4">All documents</Link><Link :href="route('documents.shared')" class="text-zinc-500 underline underline-offset-4 dark:text-zinc-400">Shared documents</Link></div>
                <div class="flex gap-1" aria-label="Document display"><button type="button" class="workspace-button" aria-label="Grid view" :aria-pressed="viewMode === 'grid'" @click="viewMode = 'grid'"><Squares2X2Icon class="h-4 w-4" aria-hidden="true" /></button><button type="button" class="workspace-button" aria-label="List view" :aria-pressed="viewMode === 'list'" @click="viewMode = 'list'"><ListBulletIcon class="h-4 w-4" aria-hidden="true" /></button></div>
            </div>
            <p v-if="loading" role="status" class="text-sm text-zinc-500">Loading documents…</p>
            <p v-if="error && !showDeleteModal" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <div v-if="selectedDocuments.length" class="workspace-surface flex flex-wrap items-center gap-2 p-3" role="region" aria-label="Selected document actions">
                <span class="mr-auto text-sm font-medium" role="status">{{ selectedDocuments.length }} document(s) selected</span>
                <a :href="route('documents.download-bulk', { ids: selectedDocuments })" class="workspace-button">Download</a>
                <button type="button" class="workspace-button !text-red-700 dark:!text-red-400" :disabled="busy" @click="error = ''; showDeleteModal = true">Delete</button>
                <button type="button" class="workspace-button" :disabled="busy" @click="selectedDocuments = []">Clear selection</button>
            </div>
            <section aria-label="Documents" :aria-busy="loading || busy" class="min-w-0">
                <div v-if="!documents.data.length" class="workspace-surface workspace-empty">
                    <DocumentIcon class="h-8 w-8 text-zinc-400" aria-hidden="true" /><h2 class="section-heading">{{ activeCategory ? `No documents in ${activeCategory.name}` : filtered ? 'No documents match your filters' : 'No documents found' }}</h2>
                    <p>{{ filtered ? 'Adjust your search or clear the filters to see more documents.' : 'Upload your first document to get started.' }}</p>
                    <Link v-if="filtered" :href="route('documents.index')" class="workspace-button">All documents</Link><Link v-else :href="route('documents.upload')" class="workspace-primary">Upload Document</Link>
                </div>
                <template v-else>
                    <label v-if="viewMode === 'grid'" class="mb-3 inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" :checked="allSelected" :indeterminate="selectedDocuments.length > 0 && !allSelected" :disabled="busy" @change="toggleAll" />Select all documents on this page</label>
                    <div v-if="viewMode === 'grid'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        <article v-for="document in documents.data" :key="document.file_id" class="workspace-surface flex flex-col gap-3 p-4" :data-document-id="document.file_id">
                            <div class="flex items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400"><span>{{ entityLabel(document.entity_type) }}</span><input v-model="selectedDocuments" type="checkbox" :value="document.file_id" :disabled="busy" :aria-label="`Select ${document.title} (file #${document.file_id})`" /></div>
                            <button type="button" class="group flex min-w-0 flex-col gap-3 rounded text-left" :dusk="`document-preview-${document.file_id}`" @click="selectedDocument = document">
                                <span class="flex aspect-[4/3] max-h-40 w-full items-center justify-center overflow-hidden rounded bg-zinc-50 dark:bg-zinc-950"><img v-if="document.file?.has_preview && !thumbnailErrors.has(document.file_id)" :src="document.file.previewUrl || document.file.url" alt="" class="h-full w-full object-contain" @error="thumbnailErrors.add(document.file_id)" /><DocumentIcon v-else class="h-10 w-10 text-zinc-300 dark:text-zinc-600" aria-hidden="true" /></span>
                                <span class="font-medium text-zinc-900 group-hover:underline [overflow-wrap:anywhere] dark:text-zinc-100">{{ document.title }}</span>
                            </button>
                            <p class="truncate text-xs text-zinc-500" :title="document.file_name">{{ document.file_name }}</p>
                            <div v-if="entitySummary(document).length" class="flex flex-col gap-1 text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-400"><p v-for="(line, index) in entitySummary(document)" :key="index">{{ line }}</p></div>
                            <p v-if="document.note" class="line-clamp-2 text-sm text-zinc-500 [overflow-wrap:anywhere]">{{ document.note }}</p>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400"><span>{{ formatFileSize(document.size) }}</span><span>{{ formatDate(document.created_at) }}</span><span v-if="document.category">{{ document.category.name }}</span><span v-if="document.shared_with_count">Shared with {{ document.shared_with_count }}</span></div>
                            <p v-if="document.tags?.length" class="text-xs text-zinc-500 [overflow-wrap:anywhere]">{{ document.tags.map(tag => tag.name).join(', ') }}</p>
                            <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800"><Link :href="getShowRoute(document)" class="inline-flex min-h-9 items-center underline underline-offset-4" :aria-label="`Open ${document.title}`">Open</Link><a v-if="document.file?.url" :href="document.file.url" :download="document.file_name" class="inline-flex min-h-9 items-center underline underline-offset-4">Download</a><button type="button" class="ml-auto min-h-9 text-red-700 dark:text-red-400" :disabled="busy" :aria-label="`Delete ${document.title}`" @click="removeOne(document)">Delete</button></div>
                        </article>
                    </div>
                    <div v-else class="workspace-surface overflow-x-auto" tabindex="0" role="region" aria-label="Document table, scroll to see all columns">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Documents with type, category, tags, sharing, file size and date.</caption>
                            <thead class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950"><tr><th scope="col" class="px-3 py-3"><input type="checkbox" :checked="allSelected" :indeterminate="selectedDocuments.length > 0 && !allSelected" :disabled="busy" aria-label="Select all documents on this page" @change="toggleAll" /></th><th scope="col" class="px-3 py-3 font-medium">Document</th><th scope="col" class="px-3 py-3 font-medium">Category / tags</th><th scope="col" class="px-3 py-3 font-medium">Shared</th><th scope="col" class="px-3 py-3 font-medium">Size</th><th scope="col" class="px-3 py-3 font-medium">Date</th><th scope="col" class="px-3 py-3 font-medium">Actions</th></tr></thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800"><tr v-for="document in documents.data" :key="document.file_id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-3 py-4"><input v-model="selectedDocuments" type="checkbox" :value="document.file_id" :disabled="busy" :aria-label="`Select ${document.title} (file #${document.file_id})`" /></td>
                                <td class="min-w-[200px] max-w-sm px-3 py-4"><button type="button" class="max-w-full truncate text-left font-medium hover:underline" :dusk="`document-preview-${document.file_id}`" @click="selectedDocument = document">{{ document.title }}</button><p class="mt-1 truncate text-xs text-zinc-500">{{ document.file_name }}</p><p class="mt-1 text-xs text-zinc-500">{{ entityLabel(document.entity_type) }}</p><p v-if="document.note" class="mt-1 truncate text-xs text-zinc-500">{{ document.note }}</p></td>
                                <td class="max-w-[200px] px-3 py-4 text-xs text-zinc-500 [overflow-wrap:anywhere]"><p>{{ document.category?.name || '—' }}</p><p v-if="document.tags?.length" class="mt-1">{{ document.tags.map(tag => tag.name).join(', ') }}</p></td>
                                <td class="px-3 py-4 text-xs text-zinc-500">{{ document.shared_with_count || '—' }}</td><td class="whitespace-nowrap px-3 py-4 text-xs text-zinc-500">{{ formatFileSize(document.size) }}</td><td class="whitespace-nowrap px-3 py-4 text-xs text-zinc-500">{{ formatDate(document.created_at) }}</td>
                                <td class="px-3 py-4"><div class="flex items-center gap-3"><Link :href="getShowRoute(document)" class="underline" :aria-label="`Open ${document.title}`">Open</Link><a v-if="document.file?.url" :href="document.file.url" :download="document.file_name" class="underline">Download</a><button type="button" class="text-red-700 dark:text-red-400" :disabled="busy" :aria-label="`Delete ${document.title}`" @click="removeOne(document)">Delete</button></div></td>
                            </tr></tbody>
                        </table>
                    </div>
                </template>
                <Pagination v-if="documents.links?.length > 3" :links="documents.links" :from="documents.from" :to="documents.to" :total="documents.total" class="mt-4" />
            </section>
        </div>
        <Modal :show="showDeleteModal" :closeable="!busy" max-width="md" @close="showDeleteModal = false" v-slot="{ titleId }"><div class="flex flex-col gap-4 p-6"><h2 :id="titleId" class="section-heading">Delete Documents</h2><p class="text-sm text-zinc-500">Delete {{ selectedDocuments.length }} document(s), their original files and extracted records? This action cannot be undone.</p><p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" class="workspace-button" :disabled="busy" @click="showDeleteModal = false">Cancel</button><button type="button" class="workspace-danger" :disabled="busy" @click="deleteSelected">{{ busy ? 'Deleting…' : 'Delete' }}</button></div></div></Modal>
        <DocumentDrawer :document="selectedDocument" :show="!!selectedDocument" @close="selectedDocument = null" />
    </AuthenticatedLayout>
</template>
