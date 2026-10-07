<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, AdjustmentsHorizontalIcon, ArrowRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
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
});
const defaults = { query: '', type: 'all', view: 'all', status: '', review_status: '', confidence: '', date_range: 'all', date_from: '', date_to: '', collection_id: '', tag_id: '', sort: 'newest', display: 'list' };
const form = reactive({ ...defaults, ...props.filters });
const expanded = ref(Boolean(form.status || form.collection_id || form.tag_id || form.date_range === 'custom'));
const loading = ref(false);
let timer;
let cancelVisit;
let sequence = 0;
const view = computed(() => smartViews.find(item => item.value === form.view));
const title = computed(() => props.activeView?.name || view.value?.label || 'Library');
const description = computed(() => view.value?.description || 'Review, correct and organize your documents in one workspace.');
const saveFilters = computed(() => cleanViewFilters(form));
const filterCount = computed(() => [form.type !== 'all', Boolean(form.status), Boolean(form.review_status), Boolean(form.confidence), form.date_range !== 'all', Boolean(form.date_from || form.date_to), Boolean(form.collection_id), Boolean(form.tag_id)].filter(Boolean).length);

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
watch(() => props.filters, value => { Object.assign(form, defaults, value); });
onBeforeUnmount(() => { clearTimeout(timer); cancelVisit?.cancel(); });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="title" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p v-if="activeView" class="mb-1 text-xs font-semibold uppercase tracking-wider text-orange-700 dark:text-orange-400">Saved view</p>
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ title }}</h1>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ description }}</p>
                </div>
                <SaveViewButton :filters="saveFilters" :active-view="activeView" />
            </div>
        </template>

        <div class="flex flex-col gap-3">
            <section aria-label="Library filters" class="rounded border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <form @submit.prevent="apply" class="grid grid-cols-2 items-end gap-2 p-3 sm:flex sm:flex-wrap sm:items-center sm:gap-3 sm:p-4">
                    <div class="relative col-span-2 min-w-0 sm:min-w-[180px] sm:flex-1">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-zinc-400" aria-hidden="true" />
                        <input dusk="library-query" v-model="form.query" type="search" maxlength="200" aria-label="Find documents" placeholder="Find by name, merchant or content…" class="w-full rounded border-zinc-200 bg-zinc-50 py-2 pl-10 text-sm focus:border-orange-500 focus:ring-orange-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white" />
                    </div>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">Type
                        <select v-model="form.type" @change="apply" aria-label="Document type" class="rounded border-zinc-200 py-2 text-sm focus:border-orange-500 focus:ring-orange-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">View
                        <select :value="form.view" @change="chooseView($event.target.value)" aria-label="Library view" class="rounded border-zinc-200 py-2 text-sm focus:border-orange-500 focus:ring-orange-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            <option value="all">All files</option>
                            <option v-for="item in smartViews" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">Status
                        <select v-model="form.review_status" @change="apply" class="workspace-filter"><option value="">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="flagged">Flagged</option></select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">AI confidence
                        <select v-model="form.confidence" @change="apply" class="workspace-filter"><option value="">Any confidence</option><option value="high">High · ≥90%</option><option value="medium">Medium · 70–89%</option><option value="low">Low · &lt;70%</option><option value="unknown">Unavailable</option></select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">Upload date
                        <select v-model="form.date_range" @change="form.date_from = ''; form.date_to = ''; expanded = form.date_range === 'custom'; apply()" class="workspace-filter"><option value="all">Any time</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="this_year">This year</option><option value="custom">Choose dates</option></select>
                    </label>
                    <button type="button" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="library-more-filters" class="inline-flex items-center gap-2 rounded border border-zinc-200 px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <AdjustmentsHorizontalIcon class="h-4 w-4" aria-hidden="true" />Filters<span v-if="filterCount" class="rounded bg-orange-100 px-1.5 text-xs text-orange-800 dark:bg-orange-500/20 dark:text-orange-300">{{ filterCount }}</span>
                    </button>
                    <button v-if="filterCount || form.query || form.view !== 'all'" type="button" @click="clear" class="inline-flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />Reset</button>
                </form>
                <div v-if="expanded" id="library-more-filters" class="grid gap-4 border-t border-zinc-100 p-4 sm:grid-cols-2 xl:grid-cols-3 dark:border-zinc-800">
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Processing status<select v-model="form.status" @change="apply" class="rounded border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any status</option><option v-for="(name, value) in fileStatusLabels" :key="value" :value="value">{{ name }}</option></select></label>
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Collection<select v-model="form.collection_id" @change="apply" class="rounded border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any collection</option><option v-for="collection in options.collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option></select></label>
                    <label class="flex flex-col gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">Tag<select v-model="form.tag_id" @change="apply" class="rounded border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Any tag</option><option v-for="tag in options.tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select></label>
                    <template v-if="form.date_range === 'custom'">
                        <label class="flex flex-col gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">From<input v-model="form.date_from" type="date" @change="apply" class="rounded border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" /></label>
                        <label class="flex flex-col gap-1.5 text-xs text-zinc-600 dark:text-zinc-400">To<input v-model="form.date_to" type="date" :min="form.date_from || undefined" @change="apply" class="rounded border-zinc-200 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" /></label>
                    </template>
                </div>
            </section>

            <p v-if="Object.keys($page.props.errors || {}).length" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ Object.values($page.props.errors)[0] }}</p>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-500 dark:text-zinc-400" role="status">{{ loading ? 'Finding documents…' : files.total + (files.total === 1 ? ' file' : ' files') }}</p>
                <div class="flex items-center gap-3">
                    <Link :href="route('search', { query: form.query, type: form.type })" class="hidden text-xs text-zinc-500 hover:text-orange-700 sm:inline dark:hover:text-orange-400">Search with advanced text filters<ArrowRightIcon class="ml-1 inline h-3 w-3" /></Link>
                    <select v-model="form.sort" @change="apply" aria-label="Sort files" class="rounded border-zinc-200 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">Name A–Z</option></select>

                </div>
            </div>

            <FileWorkspace :files="files.data" :tags="options.tags" :loading="loading" />

            <nav v-if="files.last_page > 1" aria-label="Library pagination" class="flex items-center justify-between gap-3">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Showing {{ files.from }}–{{ files.to }} of {{ files.total }}</p>
                <div class="flex items-center gap-3"><button type="button" :disabled="files.current_page === 1 || loading" @click="visit(files.current_page - 1)" class="rounded border border-zinc-200 px-3 py-2 text-sm text-zinc-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300">Previous</button><span class="text-xs text-zinc-500">{{ files.current_page }} / {{ files.last_page }}</span><button type="button" :disabled="files.current_page === files.last_page || loading" @click="visit(files.current_page + 1)" class="rounded border border-zinc-200 px-3 py-2 text-sm text-zinc-600 disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-300">Next</button></div>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
