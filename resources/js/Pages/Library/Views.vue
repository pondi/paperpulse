<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookmarkIcon, TrashIcon, PencilIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Common/Modal.vue';
import { documentTypes, smartViews } from '@/utils/libraryNavigation';

defineProps({ views: { type: Array, default: () => [] } });
const editing = ref(null);
const removing = ref(null);
const name = ref('');
const busy = ref(false);
const error = ref('');
function request(method, view, data, done) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    const options = {
        preserveScroll: true,
        onSuccess: done,
        onError: errors => { error.value = Object.values(errors)[0] || 'Please try again.'; },
        onFinish: () => { busy.value = false; },
    };
    if (method === 'delete') {
        router.delete(route('saved-searches.destroy', view.id), options);
    } else {
        router.patch(route('saved-searches.update', view.id), data, options);
    }
}
function rename(view) { editing.value = view; name.value = view.name; error.value = ''; }
function summary(view) {
    const filters = view.filters;
    const parts = [];
    if (filters.query) parts.push('“' + filters.query + '”');
    const type = documentTypes.find(item => item.value === filters.type);
    if (type && type.value !== 'all') parts.push(type.label);
    const smart = smartViews.find(item => item.value === filters.view);
    if (smart) parts.push(smart.label);
    const dates = { last_30_days: 'Last 30 days', this_month: 'This month', this_year: 'This year' };
    if (dates[filters.date_range]) parts.push(dates[filters.date_range]);
    if (filters.status) parts.push(filters.status.replaceAll('_', ' '));
    if (filters.date_from || filters.date_to) parts.push([filters.date_from || 'Any date', filters.date_to || 'Today onward'].join(' to '));
    if (filters.tags?.length) parts.push(filters.tags.join(', '));
    return parts.join(' · ') || 'All matching documents';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Saved views" />
        <template #header><div><h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Saved views</h1><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Your shortcuts to the documents you use often. Views update as your library changes.</p></div></template>
        <div class="flex flex-col gap-8">
            <section aria-label="Smart views">
                <h2 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">Ready to use</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="view in smartViews" :key="view.value" :href="route('library.index', { view: view.value })" class="group flex items-start justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 hover:border-amber-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-amber-700">
                        <div><h3 class="text-sm font-medium text-zinc-900 dark:text-white">{{ view.label }}</h3><p class="mt-1 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ view.description }}</p></div><ArrowRightIcon class="mt-1 h-4 w-4 shrink-0 text-zinc-300 group-hover:text-amber-600" aria-hidden="true" />
                    </Link>
                </div>
            </section>
            <section aria-label="Your saved views">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Your views <span class="ml-1 text-zinc-400">{{ views.length }}</span></h2><Link :href="route('library.index')" class="text-sm font-medium text-amber-700 dark:text-amber-400">Create from Library</Link></div>
                <p v-if="error && !editing && !removing" role="alert" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                <div v-if="views.length" class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                    <div v-for="view in views" :key="view.id" class="flex flex-wrap items-center gap-3 p-4 sm:px-5">
                        <button type="button" :aria-label="(view.is_pinned ? 'Unpin ' : 'Pin ') + view.name" :aria-pressed="view.is_pinned" :disabled="busy" @click="request('patch', view, { is_pinned: !view.is_pinned })" :class="[view.is_pinned ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-300 dark:text-zinc-600', 'rounded p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800']"><BookmarkIcon class="h-5 w-5" :class="view.is_pinned ? 'fill-current' : ''" /></button>
                        <Link :href="route('saved-searches.show', view.id)" class="min-w-0 flex-1"><h3 class="truncate text-sm font-semibold text-zinc-800 hover:text-amber-700 dark:text-white dark:hover:text-amber-400">{{ view.name }}</h3><p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ summary(view) }}</p></Link>
                        <span class="hidden rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-500 sm:inline dark:bg-zinc-800 dark:text-zinc-400">{{ view.scope === 'search' ? 'Content search' : 'Library' }}</span>
                        <button type="button" :aria-label="'Rename ' + view.name" :disabled="busy" @click="rename(view)" class="rounded p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"><PencilIcon class="h-4 w-4" /></button>
                        <button type="button" :aria-label="'Delete ' + view.name" :disabled="busy" @click="removing = view; error = ''" class="rounded p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
                    <BookmarkIcon class="h-8 w-8 text-zinc-300 dark:text-zinc-600" aria-hidden="true" /><h3 class="text-base font-semibold text-zinc-900 dark:text-white">Make your library work for you</h3><p class="max-w-sm text-sm text-zinc-500 dark:text-zinc-400">Choose your filters in Library or Search, then select “Save view”. Pin your favorites for quick access.</p><Link :href="route('library.index')" class="mt-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">Open Library</Link>
                </div>
            </section>
        </div>
        <Modal :show="Boolean(editing)" max-width="md" @close="!busy && (editing = null)">
            <form @submit.prevent="request('patch', editing, { name }, () => { editing = null; })" class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Rename view</h2><label for="rename-view" class="text-sm text-zinc-600 dark:text-zinc-400">View name</label><input id="rename-view" v-model="name" required maxlength="80" class="rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                <p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="busy" @click="editing = null" class="px-3 py-2 text-sm text-zinc-500">Cancel</button><button type="submit" :disabled="busy" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Save name</button></div>
            </form>
        </Modal>
        <Modal :show="Boolean(removing)" max-width="md" @close="!busy && (removing = null)">
            <div class="flex flex-col gap-4 p-6"><h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Remove “{{ removing?.name }}”?</h2><p class="text-sm text-zinc-500 dark:text-zinc-400">This removes the saved view. Your documents remain in the library.</p><p v-if="error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><div class="flex justify-end gap-3"><button type="button" :disabled="busy" @click="removing = null" class="px-3 py-2 text-sm text-zinc-500">Cancel</button><button type="button" :disabled="busy" @click="request('delete', removing, {}, () => { removing = null; })" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">Remove view</button></div></div>
        </Modal>
    </AuthenticatedLayout>
</template>
