<template>
    <Head title="Shared Collections" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    Shared Collections
                </h2>
                <Link
                    :href="route('collections.index')"
                    class="inline-flex items-center px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    My Collections
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Info Banner -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                These collections have been shared with you by other users. You can view the files but may have limited editing permissions depending on the share settings.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Shared Collections Grid -->
                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-blue-600">
                    <div class="p-6">
                        <div v-if="collections.length > 0">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div
                                    v-for="collection in collections"
                                    :key="collection.id"
                                    class="bg-white dark:bg-zinc-800 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-200 border-l-4 p-6 cursor-pointer"
                                    :style="{ borderLeftColor: collection.color }"
                                    @click="viewCollection(collection)"
                                >
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                                :style="{ backgroundColor: collection.color }"
                                            >
                                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIconPath(collection.icon)" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                                    {{ collection.name }}
                                                </h3>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                    Shared by {{ collection.user?.name || 'Unknown' }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                :class="getPermissionClass(collection.permission)"
                                            >
                                                {{ collection.permission === 'edit' ? 'Can Edit' : 'View Only' }}
                                            </span>
                                        </div>
                                    </div>
                                    <p v-if="collection.description" class="text-sm text-zinc-600 dark:text-zinc-400 mb-4 line-clamp-2">
                                        {{ collection.description }}
                                    </p>
                                    <div class="border-t border-blue-200 dark:border-zinc-700 pt-4 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Files</span>
                                            <span class="text-sm font-black text-zinc-900 dark:text-zinc-100">{{ collection.files_count || 0 }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">Shared</span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ formatDate(collection.shared_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No shared collections</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                No one has shared any collections with you yet. When someone shares a collection, it will appear here.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    collections: {
        type: Array,
        required: true
    }
});

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

const getPermissionClass = (permission) => {
    if (permission === 'edit') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400';
    }
    return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const viewCollection = (collection) => {
    router.visit(route('collections.show', collection.id));
};
</script>
