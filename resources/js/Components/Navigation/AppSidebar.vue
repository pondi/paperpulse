<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { HomeIcon, FolderIcon, RectangleStackIcon, ChartBarIcon, ClockIcon, ArrowDownTrayIcon, BookmarkIcon, Cog6ToothIcon, ExclamationCircleIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import ApplicationLogo from '@/Components/Common/ApplicationLogo.vue';
import { librarySection } from '@/utils/libraryNavigation';

const emit = defineEmits(['navigate']);
const page = usePage();
const currentRoute = computed(() => { page.url; return route().current() || ''; });
const currentView = computed(() => new URL(page.url, 'http://localhost').searchParams.get('view'));
const savedId = computed(() => new URL(page.url, 'http://localhost').searchParams.get('saved_search'));
const main = computed(() => [
    { label: 'Home', href: route('dashboard'), icon: HomeIcon, active: currentRoute.value === 'dashboard' },
    { label: 'Library', href: route('library.index'), icon: FolderIcon, active: librarySection(currentRoute.value) && !['search', 'documents.categories', 'saved-searches.index'].includes(currentRoute.value) && !savedId.value && !['recent', 'needs-review'].includes(currentView.value) },
    { label: 'Collections', href: route('collections.index'), icon: RectangleStackIcon, active: /^(collections\.|tags\.|categories\.|merchants\.|vendors\.|duplicates\.|documents\.categories)/.test(currentRoute.value) },
    { label: 'Reports', href: route('analytics.index'), icon: ChartBarIcon, active: /^(analytics\.|exports\.)/.test(currentRoute.value) },
]);
const shortcuts = computed(() => [
    { label: 'Recent uploads', href: route('library.index', { view: 'recent' }), icon: ClockIcon, active: !savedId.value && currentRoute.value === 'library.index' && currentView.value === 'recent' },
    { label: 'Needs attention', href: route('library.index', { view: 'needs-review' }), icon: ExclamationCircleIcon, active: !savedId.value && currentRoute.value === 'library.index' && currentView.value === 'needs-review', count: page.props.navigation?.attention_count || 0 },
]);
const utility = computed(() => [
    { label: 'Search contents', href: route('search'), icon: MagnifyingGlassIcon, active: !savedId.value && currentRoute.value === 'search' },
    { label: 'Activity', href: route('files.index'), icon: ClockIcon, active: /^(files\.index|jobs\.)/.test(currentRoute.value) },
    { label: 'Imports', href: route('pulsedav.index'), icon: ArrowDownTrayIcon, active: currentRoute.value.startsWith('pulsedav.') },
]);
const pinned = computed(() => page.props.navigation?.saved_views || []);
const linkClass = active => [
    active ? 'bg-orange-50 text-orange-900 dark:bg-orange-500/10 dark:text-orange-400' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white',
    'flex items-center gap-3 rounded px-3 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-500',
];
</script>

<template>
    <div class="flex h-full flex-col border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <Link :href="route('dashboard')" class="flex h-14 shrink-0 items-center gap-2.5 px-6" @click="emit('navigate')">
            <ApplicationLogo class="h-8 w-8" />
            <span class="text-lg font-bold tracking-tight text-zinc-900 dark:text-white">PaperPulse</span>
        </Link>
        <nav aria-label="Main navigation" class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-4">
            <div class="flex flex-col gap-1">
                <Link v-for="item in main" :key="item.label" :href="item.href" :class="linkClass(item.active)" :aria-current="item.active ? 'page' : undefined" @click="emit('navigate')">
                    <component :is="item.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />{{ item.label }}
                </Link>
            </div>
            <div class="flex flex-col gap-1">
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400">Smart views</p>
                <Link v-for="item in shortcuts" :key="item.label" :href="item.href" :class="linkClass(item.active)" :aria-current="item.active ? 'page' : undefined" @click="emit('navigate')">
                    <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.count" class="rounded-md bg-orange-100 px-1.5 py-0.5 text-xs text-orange-800 dark:bg-orange-500/20 dark:text-orange-300">{{ item.count }}</span>
                </Link>
            </div>
            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between px-3 pb-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Saved views</p>
                    <Link :href="route('saved-searches.index')" class="text-xs font-medium text-zinc-500 hover:text-orange-700 dark:hover:text-orange-400" @click="emit('navigate')">Manage</Link>
                </div>
                <Link v-for="view in pinned" :key="view.id" :href="route('saved-searches.show', view.id)"
                    :class="linkClass(savedId === String(view.id))" :aria-current="savedId === String(view.id) ? 'page' : undefined" @click="emit('navigate')">
                    <BookmarkIcon class="h-4 w-4 shrink-0" aria-hidden="true" /><span class="truncate">{{ view.name }}</span>
                </Link>
                <Link v-if="!pinned.length" :href="route('saved-searches.index')" class="px-3 py-2 text-xs leading-5 text-zinc-500 dark:text-zinc-400" @click="emit('navigate')">Save library filters to find them here.</Link>
            </div>
            <div class="mt-auto flex flex-col gap-1">
                <Link v-for="item in utility" :key="item.label" :href="item.href" :class="linkClass(item.active)" :aria-current="item.active ? 'page' : undefined" @click="emit('navigate')">
                    <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />{{ item.label }}
                </Link>
            </div>
        </nav>
        <Link :href="route('preferences.index')" class="flex shrink-0 items-center gap-3 border-t border-zinc-200 px-6 py-4 text-sm text-zinc-500 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:text-white" @click="emit('navigate')">
            <Cog6ToothIcon class="h-5 w-5" aria-hidden="true" />Settings
        </Link>
    </div>
</template>
