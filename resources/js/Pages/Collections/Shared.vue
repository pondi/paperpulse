<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { FolderIcon, UsersIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const { formatDate } = useDateFormatter();
defineProps({ collections: { type: Array, required: true } });
</script>

<template>
    <AuthenticatedLayout framed>
        <Head title="Shared Collections" />
        <template #header>
            <div class="flex min-w-0 items-center justify-between gap-3">
                <div class="min-w-0"><h1 class="page-heading">Shared with me</h1><p class="page-description">{{ collections.length }} {{ collections.length === 1 ? 'shared collection' : 'shared collections' }}</p></div>
                <Link :href="route('collections.index')" class="workspace-button shrink-0">My Collections</Link>
            </div>
        </template>
        <section aria-label="Shared collections" class="workspace-scroll min-h-0 flex-1 overflow-y-auto" scroll-region>
            <ul v-if="collections.length" class="grid content-start gap-3 md:grid-cols-2 2xl:grid-cols-3">
                <li v-for="collection in collections" :key="collection.id" class="min-w-0">
                    <Link :href="route('collections.show', collection.id)" class="workspace-surface flex min-w-0 flex-col gap-3 p-4 hover:border-zinc-400 dark:hover:border-zinc-600">
                        <div class="flex min-w-0 items-center gap-3"><FolderIcon class="h-6 w-6 shrink-0 text-zinc-400" aria-hidden="true" /><h2 class="min-w-0 flex-1 truncate text-sm font-medium" :title="collection.name">{{ collection.name }}</h2><span class="shrink-0 rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ collection.permission === 'edit' ? 'Can edit' : 'View only' }}</span></div>
                        <p v-if="collection.description" class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ collection.description }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Shared by {{ collection.user?.name || 'Unknown' }} · {{ formatDate(collection.shared_at) }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ collection.files_count || 0 }} files in this folder</p>
                    </Link>
                </li>
            </ul>
            <div v-else class="workspace-surface workspace-empty"><UsersIcon class="h-8 w-8 text-zinc-400" aria-hidden="true" /><h2 class="section-heading">Nothing has been shared with you yet</h2><p>Collections shared with your account will appear here.</p></div>
        </section>
    </AuthenticatedLayout>
</template>
