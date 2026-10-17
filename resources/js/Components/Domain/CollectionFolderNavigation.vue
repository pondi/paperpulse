<script setup>
import { Link } from '@inertiajs/vue3';
import { FolderIcon } from '@heroicons/vue/24/outline';
import Pagination from '@/Components/Common/Pagination.vue';

defineProps({
    children: { type: Object, required: true },
    parentCrumb: { type: Object, default: null },
    collectionId: { type: Number, required: true },
    isOwner: Boolean,
    searching: Boolean,
});
</script>

<template>
    <section aria-label="Subfolders" class="workspace-surface flex min-h-0 flex-col gap-2 p-3">
        <h2 class="section-heading shrink-0">Subfolders <span class="font-normal text-zinc-500">{{ children.total ?? children.data.length }}</span></h2>
        <nav aria-label="Folder navigation" class="workspace-scroll flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto overscroll-contain" scroll-region>
            <Link v-if="parentCrumb" :href="parentCrumb.href" class="shrink-0 rounded px-2 py-2 text-sm text-zinc-500">← Back to {{ parentCrumb.label }}</Link>
            <Link :href="route(isOwner ? 'collections.index' : 'collections.shared')" class="shrink-0 rounded px-2 py-2 text-sm text-zinc-500">{{ isOwner ? 'All Collections' : 'Shared collections' }}</Link>
            <Link v-for="child in children.data" :key="child.id" :href="route('collections.show', child.id)" class="flex min-w-0 shrink-0 items-center gap-2 rounded px-2 py-2.5 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"><FolderIcon class="h-4 w-4 shrink-0 text-zinc-400" aria-hidden="true" /><span class="truncate" :title="child.name">{{ child.name }}</span></Link>
            <p v-if="!children.data.length" class="px-2 py-3 text-xs text-zinc-500">{{ searching ? 'No subfolder names match your search.' : 'No subfolders here yet.' }}</p>
        </nav>
        <Pagination compact v-if="children.last_page > 1" :links="children.links" :from="children.from" :to="children.to" :total="children.total" class="shrink-0" />
        <Link v-if="isOwner" :href="route('collections.index', { parent_id: collectionId })" class="shrink-0 pt-2 text-sm underline underline-offset-4">Manage subfolders</Link>
    </section>
</template>
