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
    if (currentRoute.value === 'library.index') return [];
    if (isLibrary.value) {
        return documentTypes.slice(0, 6).map(type => ({
            label: type.label,
            href: route('library.index', type.value === 'all' ? {} : { type: type.value }),
            active: !['search', 'saved-searches.index'].includes(currentRoute.value) && activeType.value === type.value,
        }));
    }
    if (currentRoute.value.startsWith('collections.')) {
        return [
            { label: 'My collections', href: route('collections.index'), active: !['collections.shared', 'collections.organization.index'].includes(currentRoute.value) },
            { label: 'Shared collections', href: route('collections.shared'), active: currentRoute.value === 'collections.shared' },
            { label: 'Folder recommendations', href: route('collections.organization.index'), active: currentRoute.value === 'collections.organization.index' },
        ];
    }
    if (/^(tags\.|categories\.|documents\.categories|merchants\.|vendors\.)/.test(currentRoute.value)) {
        return [
            ['Tags', 'tags.index', 'tags.'],
            ['Categories', 'documents.categories', 'documents.categories'],
            ['Merchants', 'merchants.index', 'merchants.'],
            ['Vendors', 'vendors.index', 'vendors.'],
        ].map(([label, name, prefix]) => ({ label, href: route(name), active: currentRoute.value.startsWith(prefix) || (label === 'Categories' && currentRoute.value.startsWith('categories.')) }));
    }
    if (/^(analytics\.|exports\.)/.test(currentRoute.value) && currentRoute.value !== 'analytics.processing') {
        return [
            { label: 'Reports', href: route('analytics.index'), active: currentRoute.value === 'analytics.index' },
            { label: 'Exports', href: route('exports.index'), active: currentRoute.value.startsWith('exports.') },
        ];
    }
    if (/^(files\.index|jobs\.|pulsedav\.|duplicates\.|analytics\.processing)/.test(currentRoute.value)) {
        return [
            { label: 'Processing', href: route('files.index'), active: currentRoute.value === 'files.index' },
            { label: 'Scanner imports', href: route('pulsedav.index'), active: currentRoute.value.startsWith('pulsedav.') },
            { label: 'Duplicates', href: route('duplicates.index'), active: currentRoute.value.startsWith('duplicates.') },
            ...(page.props.auth?.user?.is_admin ? [{ label: 'Job status', href: route('jobs.index'), active: currentRoute.value.startsWith('jobs.') }] : []),
            ...(page.props.auth?.user?.is_admin ? [{ label: 'Processing analytics', href: route('analytics.processing'), active: currentRoute.value === 'analytics.processing' }] : []),
        ];
    }
    if (/^(preferences\.|profile\.)/.test(currentRoute.value)) {
        return [
            { label: 'Preferences', href: route('preferences.index'), active: currentRoute.value.startsWith('preferences.') },
            { label: 'Profile & security', href: route('profile.edit'), active: currentRoute.value.startsWith('profile.') },
        ];
    }
    return [];
});
</script>

<template>
    <div v-if="tabs.length" class="workspace-navigation border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav aria-label="Workspace navigation" class="workspace-scroll mx-auto flex max-w-screen-2xl items-center gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
            <Link v-for="tab in tabs" :key="tab.label" :href="tab.href" :aria-current="tab.active ? 'page' : undefined"
                :class="[tab.active ? 'border-orange-600 text-zinc-900 dark:border-orange-500 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white', 'shrink-0 border-b-2 px-3 py-3 text-sm font-medium transition-colors']">
                {{ tab.label }}
            </Link>
            <Link v-if="isLibrary" :href="route('library.index', { view: 'shared' })" class="ml-auto shrink-0 px-3 py-3 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Shared with me</Link>
            <Link v-if="fileId" :href="route('files.show', fileId)" class="shrink-0 px-3 py-3 text-sm font-medium text-amber-700 dark:text-amber-400">Document workspace</Link>
        </nav>
    </div>
</template>
