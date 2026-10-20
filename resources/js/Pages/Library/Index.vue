<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, AdjustmentsHorizontalIcon, ArrowRightIcon, XMarkIcon, Squares2X2Icon, ListBulletIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileWorkspace from '@/Components/Domain/FileWorkspace.vue';
import SaveViewButton from '@/Components/Search/SaveViewButton.vue';
import { documentTypes, smartViews, cleanViewFilters } from '@/utils/libraryNavigation';
import { fileStatusLabels } from '@/utils/fileStatus';

const props = defineProps({
    files: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ collections: [], tags: [] }) },
    activeView: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
});
const defaults = { query: '', type: 'all', view: 'all', status: '', review_status: '', confidence: '', date_range: 'all', date_from: '', date_to: '', collection_id: '', tag_id: '', sort: 'newest', display: 'list' };
const form = reactive({ ...defaults, ...props.filters });
const expanded = ref(false);
const reviewColumns = ref(false);
const loading = ref(false);
const requestError = ref('');
let timer;
let cancelVisit;
let sequence = 0;
const view = computed(() => smartViews.find(item => item.value === form.view));
const title = computed(() => props.activeView?.name || view.value?.label || 'Library');
const saveFilters = computed(() => cleanViewFilters(form));
const dateRanges = { last_30_days: 'Last 30 days', this_month: 'This month', this_year: 'This year', custom: 'Custom dates' };
const reviewStates = { pending: 'Pending', approved: 'Approved', flagged: 'Flagged' };
const confidenceLevels = { high: 'High · ≥90%', medium: 'Medium · 70–89%', low: 'Low · <70%', unknown: 'Unavailable' };
const activeFilters = computed(() => [
    form.query && { key: 'query', label: `Search: ${form.query}` },
    form.type !== 'all' && { key: 'type', label: documentTypes.find(type => type.value === form.type)?.label },
    view.value && { key: 'view', label: view.value.label },
    form.status && { key: 'status', label: `Processing: ${fileStatusLabels[form.status]}` },
    form.review_status && { key: 'review_status', label: `Review: ${reviewStates[form.review_status]}` },
    form.confidence && { key: 'confidence', label: `Confidence: ${confidenceLevels[form.confidence]}` },
    (form.date_range !== 'all' || form.date_from || form.date_to) && { key: 'date_range', label: `Uploaded: ${form.date_from || form.date_to ? `${form.date_from || 'Any date'} – ${form.date_to || 'Any date'}` : dateRanges[form.date_range]}` },
    form.collection_id && { key: 'collection_id', label: `Collection: ${props.options.collections.find(item => String(item.id) === String(form.collection_id))?.path || 'Selected collection'}` },
    form.tag_id && { key: 'tag_id', label: `Tag: ${props.options.tags.find(item => String(item.id) === String(form.tag_id))?.name || 'Selected tag'}` },
].filter(Boolean));
const secondaryCount = computed(() => activeFilters.value.filter(item => !['query', 'type', 'view'].includes(item.key)).length);
const specialistLists = [
    ['Receipts', 'receipts.index'], ['Documents', 'documents.index'], ['Invoices', 'invoices.index'],
    ['Contracts', 'contracts.index'], ['Bank statements', 'bank-statements.index'], ['Vouchers', 'vouchers.index'],
];

function visit(page = 1, keepSavedView = true) {
    clearTimeout(timer);
    cancelVisit?.cancel();
    const request = ++sequence;
    router.get(route('library.index'), { ...cleanViewFilters(form), page, ...(props.activeView && keepSavedView ? { saved_search: props.activeView.id } : {}) }, {
        preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true; requestError.value = ''; },
        onCancelToken: token => { cancelVisit = token; },
        onNetworkError: () => { requestError.value = 'Could not update the library. Check your connection and try again.'; return false; },
        onHttpException: () => { requestError.value = 'Could not update the library. Please try again.'; return false; },
        onFinish: () => { if (request === sequence) { loading.value = false; cancelVisit = null; } },
    });
}
function scheduleSearch() {
    clearTimeout(timer);
    cancelVisit?.cancel();
    timer = setTimeout(() => visit(), 300);
}
function apply() { visit(); }
function chooseView(value) { form.view = value; visit(1, false); }
function chooseDisplay(value) { form.display = value; visit(props.files.current_page || 1); }
function removeFilter(key) {
    form[key] = defaults[key];
    if (key === 'date_range') { form.date_from = ''; form.date_to = ''; }
    visit(1, key !== 'view');
}
function clear() { Object.assign(form, defaults, { display: form.display }); visit(1, false); }
watch(() => props.filters, value => {
    if (!Object.keys(props.errors).length) { clearTimeout(timer); Object.assign(form, defaults, value); }
});
onBeforeUnmount(() => { clearTimeout(timer); cancelVisit?.cancel(); });
</script>

<template>
    <AuthenticatedLayout framed>
        <Head :title="title" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p v-if="activeView" class="mb-1 text-xs font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400">Saved view</p>
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ title }}</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400" role="status">{{ loading ? 'Finding files…' : files.total + (files.total === 1 ? ' file' : ' files') }}</p>
                </div>
                <SaveViewButton :filters="saveFilters" :active-view="activeView" />
            </div>
        </template>

        <div class="workspace-stack">
            <section aria-label="Library filters" class="workspace-surface max-h-[45dvh] overflow-y-auto">
                <form @submit.prevent="apply" class="grid grid-cols-2 items-end gap-2 p-3 lg:flex lg:flex-wrap">
                    <label class="col-span-2 flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 lg:flex-1 dark:text-zinc-400">
                        <span class="sr-only">Find a file</span>
                        <span class="relative block">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-zinc-400" aria-hidden="true" />
                            <input dusk="library-query" v-model="form.query" @input="scheduleSearch" type="search" maxlength="200" placeholder="Name, merchant or content…" class="workspace-filter w-full pl-10" />
                        </span>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400"><span class="sr-only">Type</span>
                        <select v-model="form.type" @change="apply" aria-label="Document type" class="workspace-filter">
                            <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400"><span class="sr-only">View</span>
                        <select :value="form.view" @change="chooseView($event.target.value)" aria-label="Library view" class="workspace-filter">
                            <option value="all">All files</option>
                            <option v-for="item in smartViews" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </label>
                    <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="library-more-filters" class="workspace-button justify-center">
                        <AdjustmentsHorizontalIcon class="h-4 w-4" aria-hidden="true" />Filters<span v-if="secondaryCount" class="rounded-sm bg-orange-100 px-1.5 text-xs text-orange-800 dark:bg-orange-500/20 dark:text-orange-300">{{ secondaryCount }}</span>
                    </button>
                    <select v-model="form.sort" @change="apply" aria-label="Sort files" class="workspace-filter"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">Name A–Z</option></select>
                    <div role="group" aria-label="File presentation" class="flex gap-1">
                        <button v-for="[value, label, icon] in [['list', 'List view', ListBulletIcon], ['grid', 'Grid view', Squares2X2Icon]]" :key="value" type="button" @click="chooseDisplay(value)" :aria-label="label" :aria-pressed="form.display === value" :class="form.display === value ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'" class="rounded-md border border-zinc-200 p-2.5 dark:border-zinc-700"><component :is="icon" class="h-5 w-5" aria-hidden="true" /></button>
                    </div>
                </form>
                <div v-show="expanded" id="library-more-filters" class="grid gap-4 border-t border-zinc-200 p-4 sm:grid-cols-2 xl:grid-cols-3 dark:border-zinc-800">
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Review status<select name="review_status" v-model="form.review_status" @change="apply" class="workspace-filter"><option value="">Any review status</option><option v-for="(label, value) in reviewStates" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">AI confidence<select name="confidence" v-model="form.confidence" @change="apply" class="workspace-filter"><option value="">Any confidence</option><option v-for="(label, value) in confidenceLevels" :key="value" :value="value">{{ label }}</option></select></label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Processing status<select name="status" v-model="form.status" @change="apply" class="workspace-filter"><option value="">Any processing status</option><option v-for="(name, value) in fileStatusLabels" :key="value" :value="value">{{ name }}</option></select></label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Collection<select name="collection_id" v-model="form.collection_id" @change="apply" class="workspace-filter"><option value="">Any collection</option><option v-for="collection in options.collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option></select></label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Tag<select name="tag_id" v-model="form.tag_id" @change="apply" class="workspace-filter"><option value="">Any tag</option><option v-for="tag in options.tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select></label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Upload date
                        <select name="date_range" v-model="form.date_range" @change="form.date_from = ''; form.date_to = ''; apply()" class="workspace-filter"><option value="all">Any time</option><option v-for="(label, value) in dateRanges" :key="value" :value="value">{{ label }}</option></select>
                    </label>
                    <template v-if="form.date_range === 'custom' || form.date_from || form.date_to">
                        <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Uploaded from<input name="date_from" v-model="form.date_from" type="date" @change="apply" class="workspace-filter min-w-0" /></label>
                        <label class="flex min-w-0 flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Uploaded to<input name="date_to" v-model="form.date_to" type="date" :min="form.date_from || undefined" @change="apply" class="workspace-filter min-w-0" /></label>
                    </template>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm sm:col-span-2 xl:col-span-3">
                        <Link :href="route('search', cleanViewFilters({ query: form.query, type: form.type, collection_id: form.collection_id }))" class="inline-flex items-center gap-2 rounded-md text-orange-700 dark:text-orange-400">Search by amount or document date<ArrowRightIcon class="h-4 w-4 shrink-0" aria-hidden="true" /></Link>
                        <details class="min-w-0">
                            <summary class="cursor-pointer rounded-md text-zinc-600 dark:text-zinc-300">Browse specialist lists</summary>
                            <nav aria-label="Specialist lists" class="mt-3 flex flex-wrap gap-x-4 gap-y-3">
                                <Link v-for="[label, name] in specialistLists" :key="name" :href="route(name)" class="rounded-md text-orange-700 dark:text-orange-400">{{ label }}</Link>
                            </nav>
                        </details>
                    </div>
                    <button type="button" class="workspace-button justify-self-start" :aria-pressed="reviewColumns" @click="reviewColumns = !reviewColumns">{{ reviewColumns ? 'Hide review columns' : 'Show review columns' }}</button>
                    <p class="text-xs text-zinc-500 sm:col-span-2 xl:col-span-3 dark:text-zinc-400">Upload dates describe when you added a file. Use advanced search for dates found inside documents.</p>
                </div>
                <div v-if="activeFilters.length" aria-label="Active filters" class="flex flex-wrap items-center gap-2 border-t border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <button v-for="filter in activeFilters" :key="filter.key" type="button" @click="removeFilter(filter.key)" :aria-label="'Remove ' + filter.label" class="inline-flex max-w-full items-center gap-2 rounded-md bg-zinc-100 px-2.5 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                        <span class="min-w-0 wrap-break-word">{{ filter.label }}</span><XMarkIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    </button>
                    <button type="button" @click="clear" class="rounded-md px-2 py-1.5 text-sm font-medium text-orange-700 dark:text-orange-400">Clear all filters</button>
                </div>
            </section>

            <div v-if="requestError || Object.keys(errors).length" role="alert" class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <p>{{ requestError || Object.values(errors)[0] }}</p>
                <button v-if="requestError" type="button" @click="apply" class="mt-2 rounded-sm font-semibold underline">Try again</button>
            </div>
            <FileWorkspace :review-mode="reviewColumns" class="workspace-fill" :files="files.data" :tags="options.tags" :loading="loading" :display="form.display"
                :empty-title="loading ? 'Finding files…' : activeFilters.length ? 'No files match these filters' : 'Your library starts here'"
                :empty-description="loading ? 'Updating your results.' : activeFilters.length ? 'Remove a filter or try a different name, merchant or phrase.' : 'Add your first file using Upload, scan a document or connect a scanner from the navigation.'" />

            <nav v-if="files.last_page > 1" aria-label="Library pagination" class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Showing {{ files.from }}–{{ files.to }} of {{ files.total }}</p>
                <div class="flex items-center gap-3"><button type="button" :disabled="files.current_page === 1 || loading" @click="visit(files.current_page - 1)" class="workspace-button">Previous</button><span class="text-xs text-zinc-500">{{ files.current_page }} / {{ files.last_page }}</span><button type="button" :disabled="files.current_page === files.last_page || loading" @click="visit(files.current_page + 1)" class="workspace-button">Next</button></div>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
