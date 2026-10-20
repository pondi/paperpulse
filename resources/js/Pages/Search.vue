<template>
    <AuthenticatedLayout>
        <Head title="Search library" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p v-if="activeView" class="mb-1 text-xs font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400">Saved view</p>
                    <h1 class="page-heading">{{ activeView?.name || 'Search library' }}</h1>
                    <p class="page-description">Find extracted records by merchant, text, document date or amount.</p>
                </div>
                <SaveViewButton scope="search" :filters="{ query: searchQuery, ...filters }" :active-view="activeView" />
            </div>
        </template>

        <div class="flex min-w-0 flex-col gap-4">
            <section aria-label="Search filters" class="workspace-surface">
                <form @submit.prevent="performSearch" class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                    <label class="field-label">Search text
                        <span class="relative block">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-zinc-400" aria-hidden="true" />
                            <input v-model="searchQuery" type="search" maxlength="200" aria-label="Search document contents" placeholder="Search receipts, documents, invoices, and more…" class="workspace-filter w-full pl-10" />
                        </span>
                    </label>
                    <label class="field-label">Type
                        <select v-model="filters.type" id="search-type" class="workspace-filter">
                            <option v-for="type in searchTypes" :key="type.value" :value="type.value">{{ type.label }}{{ facets.total ? ` (${facets[type.facet] || 0})` : '' }}</option>
                        </select>
                    </label>
                    <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="search-filters" class="workspace-button"><AdjustmentsHorizontalIcon class="h-4 w-4" aria-hidden="true" />Date, amount &amp; location</button>
                </form>
                <div id="search-filters" v-show="filtersOpen" class="grid min-w-0 gap-6 border-t border-zinc-200 p-4 md:grid-cols-2 xl:grid-cols-3 dark:border-zinc-800">
                    <fieldset class="flex min-w-0 flex-col gap-3">
                        <legend class="mb-3 section-heading">Document date</legend>
                        <p id="search-date-basis" class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ dateDescription }}</p>
                        <label class="field-label">From date<input id="search-date-from" v-model="filters.date_from" type="date" aria-describedby="search-date-basis" class="workspace-filter w-full" /></label>
                        <label class="field-label">To date<input id="search-date-to" v-model="filters.date_to" type="date" :min="filters.date_from || undefined" aria-describedby="search-date-basis" class="workspace-filter w-full" /></label>
                    </fieldset>
                    <fieldset class="flex min-w-0 flex-col gap-3">
                        <legend class="mb-3 section-heading">Amount · Source currency</legend>
                        <p id="search-amount-basis" class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">No currency conversion: 100 can mean 100 NOK, EUR or USD. Ranges compare receipt/invoice totals, contract or voucher values, and statement closing balances. Documents, warranties and return policies have no searchable amount.</p>
                        <label class="field-label">Minimum amount<input id="search-amount-min" v-model.number="filters.amount_min" type="number" step="0.01" aria-describedby="search-amount-basis" class="workspace-filter w-full" /></label>
                        <label class="field-label">Maximum amount<input id="search-amount-max" v-model.number="filters.amount_max" type="number" step="0.01" aria-describedby="search-amount-basis" class="workspace-filter w-full" /></label>
                    </fieldset>
                    <fieldset class="flex min-w-0 flex-col gap-3 md:col-span-2 xl:col-span-1">
                        <legend class="mb-3 section-heading">Location &amp; classification</legend>
                        <label class="field-label">Collection<select id="search-collection" v-model="filters.collection_id" class="workspace-filter"><option value="">All collections</option><option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option></select></label>
                        <p v-if="collectionsError" role="alert" class="text-xs text-red-700 dark:text-red-300">{{ collectionsError }} <button type="button" @click="loadCollections" class="rounded-sm underline">Retry collections</button></p>
                        <label class="field-label">Category<input id="search-category" v-model="filters.category" maxlength="100" placeholder="Exact category name" class="workspace-filter" /></label>
                        <label class="field-label">Item vendor (receipts)<input id="search-vendor" v-model="filters.vendor" maxlength="100" placeholder="Exact vendor name" class="workspace-filter" aria-describedby="search-vendor-help" /></label>
                        <p id="search-vendor-help" class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">Item vendors apply to receipts. To find a merchant or invoice sender, use Search text.</p>
                        <label v-if="filters.type === 'document' || filters.document_type" class="field-label">Document classification<input id="search-document-type" v-model="filters.document_type" maxlength="100" placeholder="Exact classification" class="workspace-filter" /></label>
                    </fieldset>
                </div>
                <div v-if="activeFilters.length" aria-label="Active search filters" class="flex flex-wrap items-center gap-2 border-t border-zinc-200 p-4 dark:border-zinc-800">
                    <button v-for="filter in activeFilters" :key="filter.key" type="button" :aria-label="'Remove ' + filter.label" @click="removeFilter(filter.key)" class="inline-flex max-w-full items-center gap-2 rounded-md bg-zinc-100 px-2.5 py-1.5 text-left text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"><span class="min-w-0 wrap-break-word">{{ filter.label }}</span><XMarkIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" /></button>
                    <button type="button" @click="clearFilters" class="rounded-md px-2 py-1.5 text-sm font-medium text-orange-700 dark:text-orange-400">Clear filters (keep search)</button>
                </div>
            </section>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Search covers extracted records. <Link :href="route('library.index', { query: searchQuery, type: filters.type })" class="rounded-sm underline">Find original files and unfinished uploads in Library.</Link></p>

            <div v-if="searchError" role="alert" class="flex flex-wrap items-center gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"><p>{{ searchError }}</p><button type="button" @click="performSearch" :disabled="searching" class="rounded-sm font-semibold underline disabled:opacity-50">Retry</button></div>
            <p v-if="bulkSuccess" role="status" class="text-sm text-emerald-700 dark:text-emerald-400">{{ bulkSuccess }}</p>
            <div v-if="selectedCount" class="workspace-surface flex flex-wrap items-center gap-3 p-4">
                <span class="text-sm font-medium">{{ selectedCount }} selected</span>
                <button type="button" @click="openBulkActions('add')" class="workspace-primary">Add to Collection</button>
                <button type="button" @click="openBulkActions('remove')" class="workspace-button">Remove from Collection</button>
                <button type="button" @click="clearSelection" class="ml-auto rounded-sm px-2 py-1 text-sm text-zinc-500 dark:text-zinc-400">Clear selection</button>
            </div>
            <section aria-label="Search results" :aria-busy="searching" class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p role="status" class="text-sm text-zinc-600 dark:text-zinc-400">{{ searching ? 'Searching…' : `${pagination.total} ${pagination.total === 1 ? 'result' : 'results'}` }}</p>
                    <label v-if="results.length" class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">Order this page
                        <select id="search-sort" v-model="sortBy" class="workspace-filter"><option value="relevance">Relevance</option><option value="date_desc">Date (newest)</option><option value="date_asc">Date (oldest)</option><option v-if="['receipt', 'invoice', 'contract', 'voucher', 'bank_statement'].includes(filters.type)" value="amount_desc">Amount (highest)</option><option v-if="['receipt', 'invoice', 'contract', 'voucher', 'bank_statement'].includes(filters.type)" value="amount_asc">Amount (lowest)</option></select>
                    </label>
                </div>
                <label v-if="results.length" class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"><input type="checkbox" aria-label="Select all search results on this page" :checked="allSelected" :indeterminate="someSelected" @change="toggleSelectAll" class="rounded-sm border-zinc-300 text-orange-600 focus:ring-orange-600 dark:border-zinc-600 dark:bg-zinc-800" />Select all on this page</label>
                <div v-if="results.length" class="workspace-surface divide-y divide-zinc-200 overflow-hidden dark:divide-zinc-800">
                    <SearchResultCard v-for="result in sortedResults" :key="`${result.type}-${result.id}`" :result="result" :search-query="searchQuery" :selected="isSelected(result)" @preview="openPreview" @toggle-select="toggleSelect(result)" />
                </div>
                <div v-else-if="searching" class="workspace-surface workspace-empty"><MagnifyingGlassIcon class="h-8 w-8 animate-pulse text-zinc-400" aria-hidden="true" /><p>Searching your extracted records…</p></div>
                <div v-else-if="!searchError" class="workspace-surface workspace-empty">
                    <MagnifyingGlassIcon class="h-8 w-8 text-zinc-400" aria-hidden="true" />
                    <h2 class="section-heading">{{ searchQuery || hasActiveFilters ? 'No results found' : 'Start searching' }}</h2>
                    <p>{{ searchQuery || hasActiveFilters ? 'Try another phrase, widen the date or amount range, or remove a filter.' : 'Enter a merchant or phrase, or choose a type, date, amount or collection.' }}</p>
                    <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="workspace-button">Clear filters (keep search)</button>
                    <button v-else-if="searchQuery" type="button" @click="searchQuery = ''" class="workspace-button">Clear search</button>
                </div>
                <nav v-if="pagination.last_page > 1" aria-label="Search pagination" class="flex flex-wrap items-center justify-end gap-3">
                    <button :disabled="searching || currentPage <= 1" @click="changePage(currentPage - 1)" class="workspace-button">Previous</button><span class="text-sm text-zinc-500 dark:text-zinc-400">Page {{ pagination.page }} of {{ pagination.last_page }}</span><button :disabled="searching || currentPage >= pagination.last_page" @click="changePage(currentPage + 1)" class="workspace-button">Next</button>
                </nav>
            </section>
        </div>

        <FilePreviewModal :show="showPreview" :item="previewItem" @close="closePreview" />
        <Modal :show="showBulkActions" @close="!bulkBusy && closeBulkActions()" v-slot="{ titleId }">
            <div class="flex flex-col gap-4 p-6">
                <h2 :id="titleId" class="text-lg font-semibold">{{ bulkActionType === 'add' ? 'Add to Collection' : 'Remove from Collection' }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Choose a collection for the {{ selectedCount }} selected {{ selectedCount === 1 ? 'record' : 'records' }}.</p>
                <label class="field-label">Collection<select id="bulk-collection" v-model="bulkActionCollection" :disabled="bulkBusy" class="workspace-filter"><option value="">Select a collection</option><option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option></select></label>
                <p v-if="bulkError || collectionsError" role="alert" class="text-sm text-red-700 dark:text-red-300">{{ bulkError || collectionsError }}</p>
                <div class="flex flex-wrap justify-end gap-3"><button type="button" @click="closeBulkActions" :disabled="bulkBusy" class="workspace-button">Cancel</button><button type="button" @click="executeBulkAction" :disabled="!bulkActionCollection || bulkBusy" class="workspace-primary">{{ bulkBusy ? 'Saving…' : bulkActionType === 'add' ? 'Add to Collection' : 'Remove from Collection' }}</button></div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SaveViewButton from '@/Components/Search/SaveViewButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchResultCard from '@/Components/Search/SearchResultCard.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
import Modal from '@/Components/Common/Modal.vue';
import { MagnifyingGlassIcon, AdjustmentsHorizontalIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

// Props from controller
const props = defineProps({
  initialFilters: { type: Object, default: () => ({}) },
  activeView: { type: Object, default: null },
  query: {
    type: String,
    default: ''
  },
  initialResults: {
    type: Array,
    default: () => []
  },
  initialSearchStatus: {
    type: String,
    default: 'available'
  },
  initialPagination: {
    type: Object,
    default: () => ({ page: 1, last_page: 0, total: 0 })
  },
  initialFacets: {
    type: Object,
    default: () => ({})
  }
});

// Search state
const searchQuery = ref(props.query || '');
const searching = ref(false);
const searchMessages = {
  partial: 'Some search results are unavailable. Retry to search all your documents.',
  unavailable: 'Search is unavailable. Please retry.'
};
const searchError = ref(searchMessages[props.initialSearchStatus] || '');
let requestSequence = 0;
let searchController = null;
const results = ref(props.initialResults || []);
const facets = ref(props.initialFacets || { total: 0, receipts: 0, documents: 0, invoices: 0, contracts: 0, vouchers: 0, warranties: 0, return_policies: 0, bank_statements: 0 });
const collections = ref([]);
const pagination = ref(props.initialPagination);
const currentPage = ref(props.initialPagination.page);
const filtersOpen = ref(true);
const collectionsError = ref('');
let syncingProps = false;

// Filter state
const filters = ref({
  type: 'all',
  date_from: '',
  date_to: '',
  amount_min: null,
  amount_max: null,
  category: '',
  vendor: '',
  document_type: '',
  collection_id: '',
  ...props.initialFilters
});

// Sort state
const sortBy = ref('relevance');

// Preview state
const showPreview = ref(false);
const previewItem = ref(null);

// Bulk selection state
const selectedResults = ref(new Set());
const showBulkActions = ref(false);
const bulkActionCollection = ref('');
const bulkActionType = ref('');
const bulkBusy = ref(false);
const bulkError = ref('');
const bulkSuccess = ref('');

// Computed
const hasActiveFilters = computed(() => {
  return filters.value.type !== 'all' ||
    filters.value.date_from ||
    filters.value.date_to ||
    (filters.value.amount_min !== null && filters.value.amount_min !== '') ||
    (filters.value.amount_max !== null && filters.value.amount_max !== '') ||
    filters.value.category ||
    filters.value.collection_id ||
    filters.value.tags?.length ||
    filters.value.document_type ||
    filters.value.vendor ||
    filters.value.vendors?.length;
});

const searchTypes = [
  { value: 'all', label: 'All records', facet: 'total' },
  { value: 'receipt', label: 'Receipts', facet: 'receipts' },
  { value: 'document', label: 'Documents', facet: 'documents' },
  { value: 'invoice', label: 'Invoices', facet: 'invoices' },
  { value: 'contract', label: 'Contracts', facet: 'contracts' },
  { value: 'voucher', label: 'Vouchers', facet: 'vouchers' },
  { value: 'warranty', label: 'Warranties', facet: 'warranties' },
  { value: 'return_policy', label: 'Return policies', facet: 'return_policies' },
  { value: 'bank_statement', label: 'Bank statements', facet: 'bank_statements' },
];
const dateDescription = computed(() => ({
  receipt: 'Matches the receipt date, including both endpoints.',
  document: 'Matches the date extracted from the document, including both endpoints.',
  invoice: 'Matches the invoice date, including both endpoints.',
  contract: 'Matches the contract effective date, including both endpoints.',
  voucher: 'Matches the voucher expiry date, including both endpoints.',
  warranty: 'Matches the warranty end date, including both endpoints.',
  return_policy: 'Matches the return deadline, including both endpoints.',
  bank_statement: 'Matches the statement date, including both endpoints.',
}[filters.value.type] || 'Matches receipt, document, invoice or statement dates; contract effective dates; voucher/warranty end dates; and return deadlines. Includes both endpoints.'));
const activeFilters = computed(() => Object.entries(filters.value).filter(([key, value]) =>
  value !== '' && value !== null && value !== undefined && (!Array.isArray(value) || value.length) && !(key === 'type' && value === 'all')
).map(([key, value]) => ({ key, label: key === 'type' ? searchTypes.find(type => type.value === value)?.label :
  key === 'collection_id' ? `Collection: ${collections.value.find(item => String(item.id) === String(value))?.path || value}` :
  `${({ date_from: 'From', date_to: 'To', amount_min: 'Minimum', amount_max: 'Maximum', category: 'Category', vendor: 'Item vendor', vendors: 'Item vendors', tags: 'Tags', document_type: 'Classification' })[key] || key}: ${Array.isArray(value) ? value.join(', ') : value}`
})));
const removeFilter = key => { filters.value = { ...filters.value, [key]: key === 'type' ? 'all' : key.startsWith('amount_') ? null : '' }; };

const sortedResults = computed(() => {
  const sorted = [...results.value];

  switch (sortBy.value) {
    case 'date_desc':
      return sorted.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
    case 'date_asc':
      return sorted.sort((a, b) => new Date(a.date || 0) - new Date(b.date || 0));
    case 'amount_desc':
      return sorted.sort((a, b) => {
        const aAmount = Number(a.total ?? 0);
        const bAmount = Number(b.total ?? 0);
        return bAmount - aAmount;
      });
    case 'amount_asc':
      return sorted.sort((a, b) => {
        const aAmount = Number(a.total ?? 0);
        const bAmount = Number(b.total ?? 0);
        return aAmount - bAmount;
      });
    case 'relevance':
    default:
      return sorted.sort((a, b) => (b._rankingScore || 0) - (a._rankingScore || 0));
  }
});

const selectedCount = computed(() => selectedResults.value.size);

const allSelected = computed(() => {
  return results.value.length > 0 && selectedResults.value.size === results.value.length;
});

const someSelected = computed(() => {
  return selectedResults.value.size > 0 && selectedResults.value.size < results.value.length;
});

// Methods
const emptyFacets = { total: 0, receipts: 0, documents: 0, invoices: 0, contracts: 0, vouchers: 0, warranties: 0, return_policies: 0, bank_statements: 0 };

const invalidateSearch = () => {
  requestSequence++;
  searchController?.abort();
  results.value = [];
  facets.value = emptyFacets;
  pagination.value = { page: currentPage.value, last_page: 0, total: 0 };
  searchError.value = '';
  searching.value = Boolean(searchQuery.value.trim() || hasActiveFilters.value);
  clearSelection();
};

const performSearch = async () => {
  clearTimeout(searchTimeout);
  invalidateSearch();
  const requestId = requestSequence;
  if (!searching.value) {
    router.replace({
      url: route('search', props.activeView ? { saved_search: props.activeView.id } : {}, false),
      preserveState: true,
      preserveScroll: true,
      props: current => ({ ...current, query: '', initialFilters: { ...filters.value },
        initialResults: [], initialPagination: pagination.value, initialFacets: emptyFacets,
        initialSearchStatus: 'available' }),
    });
    return;
  }
  searchController = new AbortController();

  try {
    const params = {
      query: searchQuery.value,
      page: currentPage.value,
      ...filters.value
    };
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key];
      }
    });

    const response = await axios.get(route('search'), { params, signal: searchController.signal });
    if (requestId !== requestSequence) {
      return;
    }
    results.value = response.data.results;
    pagination.value = response.data.pagination;
    facets.value = response.data.facets || emptyFacets;
    searchError.value = searchMessages[response.data.search_status] || '';
    router.replace({
      url: route('search', { ...params, ...(props.activeView ? { saved_search: props.activeView.id } : {}) }, false),
      preserveState: true,
      preserveScroll: true,
      props: current => ({ ...current, query: searchQuery.value, initialFilters: { ...filters.value },
        initialResults: results.value, initialPagination: pagination.value, initialFacets: facets.value,
        initialSearchStatus: response.data.search_status }),
    });
  } catch (error) {
    if (requestId !== requestSequence) {
      return;
    }
    searchError.value = error.response?.status === 422
      ? Object.values(error.response?.data?.errors || {}).flat().join(' ') || 'Check your search filters and try again.'
      : searchMessages.unavailable;
    if (error.response?.status === 422) filtersOpen.value = true;
  } finally {
    if (requestId === requestSequence) {
      searching.value = false;
    }
  }
};

const changePage = (page) => {
  currentPage.value = page;
  clearSelection();
  performSearch();
};

const clearFilters = () => {
  sortBy.value = 'relevance';
  filters.value = {
    type: 'all',
    date_from: '',
    date_to: '',
    amount_min: null,
    amount_max: null,
    category: '',
    collection_id: ''
  };
};

const openPreview = (item) => {
  previewItem.value = item;
  showPreview.value = true;
};

const closePreview = () => {
  showPreview.value = false;
  previewItem.value = null;
};

// Bulk selection methods
const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedResults.value.clear();
  } else {
    results.value.forEach(result => {
      selectedResults.value.add(`${result.type}-${result.id}`);
    });
  }
};

const toggleSelect = (result) => {
  const key = `${result.type}-${result.id}`;
  if (selectedResults.value.has(key)) {
    selectedResults.value.delete(key);
  } else {
    selectedResults.value.add(key);
  }
};

const isSelected = (result) => {
  return selectedResults.value.has(`${result.type}-${result.id}`);
};

const clearSelection = () => {
  selectedResults.value.clear();
  showBulkActions.value = false;
  bulkActionCollection.value = '';
  bulkActionType.value = '';
};

const openBulkActions = (type) => {
  bulkError.value = '';
  bulkSuccess.value = '';
  bulkActionType.value = type;
  showBulkActions.value = true;
};

const closeBulkActions = () => {
  showBulkActions.value = false;
  bulkActionCollection.value = '';
  bulkActionType.value = '';
};

const executeBulkAction = async () => {
  if (!bulkActionCollection.value || bulkBusy.value) {
    return;
  }

  const fileIds = [];

  // Extract file IDs from selected results
  selectedResults.value.forEach(key => {
    const [type, id] = key.split('-');
    const result = results.value.find(r => r.type === type && r.id === parseInt(id));
    if (result?.file_id) {
      fileIds.push(result.file_id);
    }
  });

  if (fileIds.length === 0) {
    bulkError.value = 'These records have no original files to organize.';
    return;
  }
  bulkBusy.value = true;
  bulkError.value = '';

  try {
    const endpoint = bulkActionType.value === 'add'
      ? route('collections.files.add', bulkActionCollection.value)
      : route('collections.files.remove', bulkActionCollection.value);

    await axios({
      method: bulkActionType.value === 'add' ? 'post' : 'delete',
      url: endpoint,
      data: { file_ids: [...new Set(fileIds)] }
    });

    bulkSuccess.value = bulkActionType.value === 'add' ? 'Files added to the collection.' : 'Files removed from the collection.';
    clearSelection();
    closeBulkActions();
  } catch (error) {
    bulkError.value = error.response?.data?.message || 'Could not update the collection. Please try again.';
  } finally {
    bulkBusy.value = false;
  }
};

// Watch for filter changes
watch(filters, () => {
  if (syncingProps) return;
  currentPage.value = 1;
  performSearch();
}, { deep: true });

// Debounced search on query change
let searchTimeout = null;
watch(searchQuery, () => {
  if (syncingProps) return;
  currentPage.value = 1;
  invalidateSearch();
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    performSearch();
  }, 300);
});

onBeforeUnmount(() => {
  clearTimeout(searchTimeout);
  requestSequence++;
  searchController?.abort();
});

watch(() => [props.query, props.initialFilters, props.initialResults, props.initialPagination], () => {
  syncingProps = true;
  clearTimeout(searchTimeout);
  requestSequence++;
  searchController?.abort();
  searchQuery.value = props.query || '';
  filters.value = { type: 'all', date_from: '', date_to: '', amount_min: null, amount_max: null, category: '', collection_id: '', vendor: '', document_type: '', ...props.initialFilters };
  results.value = props.initialResults || [];
  facets.value = props.initialFacets || emptyFacets;
  pagination.value = props.initialPagination;
  currentPage.value = props.initialPagination.page;
  searchError.value = searchMessages[props.initialSearchStatus] || '';
  searching.value = false;
  clearSelection();
  nextTick(() => { syncingProps = false; });
});

const loadCollections = async () => {
  collectionsError.value = '';
  try {
    const response = await axios.get(route('collections.all'));
    collections.value = response.data.data || response.data || [];
  } catch (error) {
    collectionsError.value = 'Collections could not be loaded.';
  }
};
onMounted(loadCollections);
</script>
