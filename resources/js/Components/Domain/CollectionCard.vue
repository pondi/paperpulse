<template>
    <article :data-folder-id="collection.id" class="workspace-surface relative flex min-w-0 flex-col gap-4 p-4">
        <div class="flex items-start gap-2">
            <Link :href="route('collections.show', collection.id)" class="group flex min-w-0 flex-1 items-start gap-3 rounded-sm">
                <span class="relative shrink-0 text-zinc-500 dark:text-zinc-400" aria-hidden="true">
                    <FolderIcon class="h-10 w-10" />
                    <svg v-if="collection.icon && collection.icon !== 'folder'" class="absolute bottom-0 right-0 h-5 w-5 rounded-sm bg-white p-0.5 dark:bg-zinc-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIconPath(collection.icon)" /></svg>
                    <span class="absolute left-1 top-1 h-1.5 w-3 rounded-full" :style="{ backgroundColor: collection.color }" />
                </span>
                <div class="min-w-0 flex-1 pt-1">
                    <h2 class="font-medium text-zinc-900 group-hover:underline wrap-anywhere dark:text-zinc-100">{{ collection.name }}</h2>
                    <div v-if="collection.is_archived || collection.is_pinned" class="mt-1 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span v-if="collection.is_archived">Archived</span>
                        <span v-if="collection.is_pinned" title="Kept in place during automatic organization">Fixed location</span>
                    </div>
                </div>
            </Link>
            <Menu v-if="showEdit || showArchive || canDelete" as="div" class="relative shrink-0">
                <MenuButton :disabled="busy" :aria-label="`Actions for ${collection.name}`" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 disabled:opacity-50 dark:text-zinc-400 dark:hover:bg-zinc-800">
                    <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
                </MenuButton>
                <MenuItems class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-zinc-200 bg-white p-1 text-sm shadow-lg focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-900">
                    <MenuItem v-if="showEdit" v-slot="{ active }"><button class="min-h-11 w-full rounded-sm px-3 py-2 text-left" :class="active ? 'bg-zinc-100 dark:bg-zinc-800' : ''" @click="$emit('edit', collection)">Edit folder</button></MenuItem>
                    <MenuItem v-if="showArchive" v-slot="{ active }"><button class="min-h-11 w-full rounded-sm px-3 py-2 text-left" :class="active ? 'bg-zinc-100 dark:bg-zinc-800' : ''" @click="$emit(collection.is_archived ? 'unarchive' : 'archive', collection)">{{ collection.is_archived ? 'Restore folder' : 'Archive folder' }}</button></MenuItem>
                    <MenuItem v-if="canDelete" v-slot="{ active }"><button class="min-h-11 w-full rounded-sm px-3 py-2 text-left text-red-700 dark:text-red-400" :class="active ? 'bg-zinc-100 dark:bg-zinc-800' : ''" @click="$emit('delete', collection)">Delete folder</button></MenuItem>
                </MenuItems>
            </Menu>
        </div>
        <p v-if="collection.description" class="line-clamp-2 text-sm text-zinc-600 wrap-anywhere dark:text-zinc-400">{{ collection.description }}</p>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ collection.files_count || 0 }} direct file{{ collection.files_count === 1 ? '' : 's' }}</span>
            <Link v-if="collection.children_count" :href="childrenHref || route('collections.index', { parent_id: collection.id })" class="inline-flex min-h-11 items-center gap-1 underline underline-offset-4" :aria-label="`Browse ${collection.children_count} subfolders in ${collection.name}`">
                {{ collection.children_count }} subfolder{{ collection.children_count === 1 ? '' : 's' }}<ChevronRightIcon class="h-4 w-4" aria-hidden="true" />
            </Link>
            <span v-else>No subfolders</span>
        </div>
    </article>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { ChevronRightIcon, EllipsisHorizontalIcon, FolderIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    collection: { type: Object, required: true },
    showEdit: { type: Boolean, default: true },
    showArchive: { type: Boolean, default: true },
    showDelete: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    childrenHref: { type: String, default: null },
});
const canDelete = computed(() => props.showDelete && props.collection.files_count === 0 && props.collection.children_count === 0);
defineEmits(['edit', 'archive', 'unarchive', 'delete']);

const getIconPath = (iconName) => {
    const icons = {
        'folder': 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'folder-open': 'M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z',
        'briefcase': 'M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z',
        'document': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'home': 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'heart': 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        'star': 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'tag': 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'archive-box': 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'building-office': 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'shopping-bag': 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        'receipt-refund': 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z',
    };
    return icons[iconName] || icons['folder'];
};
</script>
