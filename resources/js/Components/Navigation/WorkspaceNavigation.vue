<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { documentTypes, documentTypeForRoute, librarySection } from '@/utils/libraryNavigation';

const page = usePage();
const currentRoute = computed(() => { page.url; return route().current() || ''; });
const isLibrary = computed(() => librarySection(currentRoute.value));
const activeType = computed(() => page.props.filters?.type || page.props.extractedEntities?.find(item => item.is_primary)?.entity_type || documentTypeForRoute(currentRoute.value));
const fileId = computed(() => {
    if (!currentRoute.value.endsWith('.show') || currentRoute.value === 'files.show') return null;
    const entity = page.props.receipt || page.props.document || page.props.invoice || page.props.contract || page.props.statement || page.props.voucher;
    return entity?.file_id || entity?.file?.id;
});
const tabs = computed(() => {
    if (isLibrary.value) {
        return documentTypes.slice(0, 6).map(type => ({
            label: type.label,
            href: route('library.index', type.value === 'all' ? {} : { type: type.value }),
            active: !['search', 'saved-searches.index'].includes(currentRoute.value) && activeType.value === type.value,
        }));
    }
    if (/^(collections\.|tags\.|categories\.|documents\.categories|merchants\.|vendors\.|duplicates\.)/.test(currentRoute.value)) {
        return [
            ['Collections', 'collections.index', 'collections.'],
            ['Tags', 'tags.index', 'tags.'],
            ['Categories', 'documents.categories', 'documents.categories'],
            ['Merchants', 'merchants.index', 'merchants.'],
            ['Vendors', 'vendors.index', 'vendors.'],
            ['Duplicates', 'duplicates.index', 'duplicates.'],
        ].map(([label, name, prefix]) => ({ label, href: route(name), active: currentRoute.value.startsWith(prefix) || (label === 'Categories' && currentRoute.value.startsWith('categories.')) }));
    }
    if (/^(analytics\.|exports\.)/.test(currentRoute.value)) {
        return [
            { label: 'Analytics', href: route('analytics.index'), active: currentRoute.value.startsWith('analytics.') },
            { label: 'Exports', href: route('exports.index'), active: currentRoute.value.startsWith('exports.') },
        ];
    }
    if (/^(files\.index|jobs\.|pulsedav\.)/.test(currentRoute.value)) {
        return [
            { label: 'Processing', href: route('files.index'), active: currentRoute.value === 'files.index' },
            { label: 'Scanner imports', href: route('pulsedav.index'), active: currentRoute.value.startsWith('pulsedav.') },
            ...(page.props.auth?.user?.is_admin ? [{ label: 'Job status', href: route('jobs.index'), active: currentRoute.value.startsWith('jobs.') }] : []),
        ];
    }
    return [];
});
</script>

<template>
    <div v-if="tabs.length" class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav aria-label="Workspace navigation" class="workspace-scroll mx-auto flex max-w-screen-2xl items-center gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
            <Link v-for="tab in tabs" :key="tab.label" :href="tab.href" :aria-current="tab.active ? 'page' : undefined"
                :class="[tab.active ? 'border-amber-600 text-amber-800 dark:border-amber-500 dark:text-amber-400' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white', 'shrink-0 border-b-2 px-3 py-3 text-sm font-medium transition-colors']">
                {{ tab.label }}
            </Link>
            <Link v-if="isLibrary" :href="route('library.index', { view: 'shared' })" class="ml-auto shrink-0 px-3 py-3 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Shared with me</Link>
            <Link v-if="fileId" :href="route('files.show', fileId)" class="shrink-0 px-3 py-3 text-sm font-medium text-amber-700 dark:text-amber-400">Document workspace</Link>
        </nav>
    </div>
</template>
