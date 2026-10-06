<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { DocumentIcon, MagnifyingGlassIcon, AdjustmentsHorizontalIcon, Squares2X2Icon, Bars3Icon, ArrowUpTrayIcon, ArrowRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SaveViewButton from '@/Components/Search/SaveViewButton.vue';
import { documentTypes, smartViews, cleanViewFilters } from '@/utils/libraryNavigation';
import { fileStatusLabel, fileStatusLabels } from '@/utils/fileStatus';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    files: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ collections: [], tags: [] }) },
    activeView: { type: Object, default: null },
});
const defaults = { query: '', type: 'all', view: 'all', status: '', date_range: 'all', date_from: '', date_to: '', collection_id: '', tag_id: '', sort: 'newest', display: 'list' };
const form = reactive({ ...defaults, ...props.filters });
const expanded = ref(Boolean(form.status || form.date_range !== 'all' || form.collection_id || form.tag_id));
const loading = ref(false);
let timer;
let cancelVisit;
let sequence = 0;
const { formatDate, formatCurrency } = useDateFormatter();
const view = computed(() => smartViews.find(item => item.value === form.view));
const title = computed(() => props.activeView?.name || view.value?.label || 'Library');
const description = computed(() => view.value?.description || 'Every document, one place. Filter, find and open your files.');
const saveFilters = computed(() => cleanViewFilters(form));
const filterCount = computed(() => [form.type !== 'all', Boolean(form.status), form.date_range !== 'all', Boolean(form.date_from || form.date_to), Boolean(form.collection_id), Boolean(form.tag_id)].filter(Boolean).length);
const display = computed(() => form.display === 'grid' ? 'grid' : 'list');

function visit(page = 1, keepSavedView = true) {
    clearTimeout(timer);
    cancelVisit?.cancel();
    const request = ++sequence;
    loading.value = true;
    router.get(route('library.index'), { ...cleanViewFilters(form), page, ...(props.activeView && keepSavedView ? { saved_search: props.activeView.id } : {}) }, {
        preserveState: true, preserveScroll: true, replace: true,
        onCancelToken: token => { cancelVisit = token; },
        onFinish: () => { if (request === sequence) { loading.value = false; cancelVisit = null; } },
    });
}
watch(() => form.query, () => { clearTimeout(timer); cancelVisit?.cancel(); timer = setTimeout(() => visit(), 300); });
function apply() { visit(); }
function chooseView(value) { form.view = value; visit(1, false); }
function clear() { Object.assign(form, defaults, { display: form.display }); visit(1, false); }
function label(type) { return documentTypes.find(item => item.value === type)?.label || type.replaceAll('_', ' '); }
watch(() => props.filters, value => { Object.assign(form, defaults, value); });
onBeforeUnmount(() => { clearTimeout(timer); cancelVisit?.cancel(); });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="title" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p v-if="activeView" class="mb-1 text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Saved view</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ title }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
                </div>
                <SaveViewButton :filters="saveFilters" :active-view="activeView" />
            </div>
        </template>

        <div class="flex flex-col gap-5">
            <p v-if="form.type === 'receipt'" class="text-xs text-zinc-500 dark:text-zinc-400">Library uses the view and sort controls below and 24 files per page. Receipt table defaults in Settings apply to the separate receipt overview.</p>
            <section aria-label="Library filters" class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <form @submit.prevent="apply" class="grid grid-cols-2 items-end gap-2 p-3 sm:flex sm:flex-wrap sm:items-center sm:gap-3 sm:p-4">
                    <div class="relative col-span-2 min-w-0 sm:min-w-[180px] sm:flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-zinc-400" aria-hidden="true" />
                        <input dusk="library-query" v-model="form.query" type="search" maxlength="200" aria-label="Find documents" placeholder="Find by name, merchant or content…" class="w-full rounded-lg border-zinc-200 bg-zinc-50 py-2 pl-10 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white" />
                    </div>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">Type
                        <select v-model="form.type" @change="apply" aria-label="Document type" class="rounded-lg border-zinc-200 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">View
                        <select :value="form.view" @change="chooseView($event.target.value)" aria-label="Library view" class="rounded-lg border-zinc-200 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            <option value="all">All files</option>
                            <option v-for="item in smartViews" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </label>
                    <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="library-more-filters" class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <AdjustmentsHorizontalIcon class="h-4 w-4" aria-hidden="true" />Filters<span v-if="filterCount" class="rounded bg-amber-100 px-1.5 text-xs text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">{{ filterCount }}</span>
                    </button>
                    <button v-if="filterCount || form.query || form.view !== 'all'" type="button" @click="clear" class="inline-flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />Reset</button>
                </form>
                <div v-if="expanded" id="library-more-filters" class="grid gap-4 border-t border-zinc-100 p-4 sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-800">
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Processing status<select v-model="form.status" @change="apply" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any status</option><option v-for="(name, value) in fileStatusLabels" :key="value" :value="value">{{ name }}</option></select></label>
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Uploaded<select v-model="form.date_range" @change="form.date_from = ''; form.date_to = ''; apply()" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="all">Any time</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="this_year">This year</option><option value="custom">Choose dates</option></select></label>
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Collection<select v-model="form.collection_id" @change="apply" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any collection</option><option v-for="collection in options.collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option></select></label>
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Tag<select v-model="form.tag_id" @change="apply" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any tag</option><option v-for="tag in options.tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select></label>
                    <template v-if="form.date_range === 'custom'">
                        <label class="flex flex-col gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">From<input v-model="form.date_from" type="date" @change="apply" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" /></label>
                        <label class="flex flex-col gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">To<input v-model="form.date_to" type="date" :min="form.date_from || undefined" @change="apply" class="rounded-lg border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" /></label>
                    </template>
                </div>
            </section>

            <p v-if="Object.keys($page.props.errors || {}).length" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ Object.values($page.props.errors)[0] }}</p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-500 dark:text-zinc-400" role="status">{{ loading ? 'Finding documents…' : files.total + (files.total === 1 ? ' file' : ' files') }}</p>
                <div class="flex items-center gap-3">
                    <Link :href="route('search', { query: form.query, type: form.type })" class="hidden text-xs text-zinc-500 hover:text-amber-700 sm:inline dark:hover:text-amber-400">Search with advanced text filters<ArrowRightIcon class="ml-1 inline h-3 w-3" /></Link>
                    <select v-model="form.sort" @change="apply" aria-label="Sort files" class="rounded-lg border-zinc-200 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">Name A–Z</option></select>
                    <div class="flex rounded-lg border border-zinc-200 bg-white p-0.5 dark:border-zinc-700 dark:bg-zinc-900">
                        <button type="button" aria-label="List view" :aria-pressed="display === 'list'" @click="form.display = 'list'; apply()" :class="[display === 'list' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-400', 'rounded p-1.5']"><Bars3Icon class="h-4 w-4" /></button>
                        <button type="button" aria-label="Grid view" :aria-pressed="display === 'grid'" @click="form.display = 'grid'; apply()" :class="[display === 'grid' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-400', 'rounded p-1.5']"><Squares2X2Icon class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>

            <div v-if="files.data.length" :aria-busy="loading" :class="[display === 'grid' ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3' : 'flex flex-col divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900', loading ? 'opacity-60' : '']">
                <Link v-for="file in files.data" :key="file.id" :href="file.detailsUrl" :dusk="'library-file-' + file.id" :class="[display === 'grid' ? 'flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-4 hover:border-amber-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-amber-700' : 'flex items-center gap-4 px-4 py-3 hover:bg-zinc-50 sm:px-5 dark:hover:bg-zinc-800/60', 'group transition-colors']">
                    <div :class="[display === 'grid' ? 'h-40 w-full' : 'h-12 w-10 shrink-0', 'flex items-center justify-center overflow-hidden rounded-md border border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950']">
                        <img v-if="file.previewUrl" :src="file.previewUrl" :alt="file.name" loading="lazy" class="h-full w-full object-contain" />
                        <DocumentIcon v-else :class="[display === 'grid' ? 'h-10 w-10' : 'h-5 w-5', 'text-zinc-300 dark:text-zinc-600']" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="break-words text-sm font-semibold text-zinc-800 group-hover:text-amber-700 dark:text-zinc-100 dark:group-hover:text-amber-400">{{ file.identity?.title || file.name || 'Untitled document' }}</h2>
                        <p v-if="file.identity?.title" class="mt-0.5 break-words text-xs text-zinc-500 dark:text-zinc-400">{{ file.name }}</p>
                        <div v-if="file.identity?.date || file.identity?.amount != null" class="mt-1 flex flex-wrap gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                            <span v-if="file.identity.date">{{ formatDate(file.identity.date) }}</span>
                            <span v-if="file.identity.amount != null">{{ formatCurrency(file.identity.amount, file.identity.currency) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-400 dark:text-zinc-500">
                            <span class="uppercase">{{ file.extension }}</span><span>Uploaded {{ formatDate(file.uploaded_at) }}</span>
                            <span v-if="file.folder" class="truncate">Folder: {{ file.folder.name }}</span><span v-if="file.is_shared">Shared by {{ file.owner }}</span>
                        </div>
                        <div v-if="display === 'grid'" class="mt-3 flex flex-wrap gap-1.5"><span v-for="type in file.entity_types.length ? file.entity_types : [file.file_type]" :key="type" class="rounded bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ label(type) }}</span></div>
                    </div>
                    <span :class="[file.status === 'failed' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400' : file.status === 'needs_review' ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400' : file.status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400', 'self-start whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium', display === 'list' ? 'sm:self-center' : '']">{{ fileStatusLabel(file) }}</span>
                    <ArrowRightIcon v-if="display === 'list'" class="hidden h-4 w-4 shrink-0 text-zinc-300 group-hover:text-amber-600 sm:block" aria-hidden="true" />
                </Link>
            </div>
            <div v-else class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <DocumentIcon class="h-10 w-10 text-zinc-300 dark:text-zinc-600" aria-hidden="true" />
                <div><h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ filterCount || form.query || form.view !== 'all' ? 'No matching documents' : 'Your library starts here' }}</h2><p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ filterCount || form.query || form.view !== 'all' ? 'Try another view or adjust your filters.' : 'Upload your first document. PaperPulse will extract and organize its information.' }}</p></div>
                <button v-if="filterCount || form.query || form.view !== 'all'" type="button" @click="clear" class="text-sm font-medium text-amber-700 dark:text-amber-400">Clear filters</button>
                <Link v-else :href="route('documents.upload')" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"><ArrowUpTrayIcon class="h-4 w-4" />Upload files</Link>
            </div>

            <nav v-if="files.last_page > 1" aria-label="Library pagination" class="flex items-center justify-between gap-3">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Showing {{ files.from }}–{{ files.to }} of {{ files.total }}</p>
                <div class="flex items-center gap-3"><button type="button" :disabled="files.current_page === 1 || loading" @click="visit(files.current_page - 1)" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300">Previous</button><span class="text-xs text-zinc-500">{{ files.current_page }} / {{ files.last_page }}</span><button type="button" :disabled="files.current_page === files.last_page || loading" @click="visit(files.current_page + 1)" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300">Next</button></div>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
