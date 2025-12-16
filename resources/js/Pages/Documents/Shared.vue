<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import { 
    DocumentIcon, 
    UserIcon,
    EyeIcon,
    ArrowDownTrayIcon
} from '@heroicons/vue/24/outline';

interface SharedDocument {
    id: number;
    title: string;
    file_name: string;
    file_type: string;
    size: number;
    created_at: string;
    category?: {
        id: number;
        name: string;
        color: string;
    };
    tags: Array<{
        id: number;
        name: string;
    }>;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    permission: 'view' | 'edit';
    shared_at: string;
}

interface Props {
    documents: {
        data: SharedDocument[];
        links: any;
        meta: any;
    };
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const applyFilter = (filters: any) => {
    router.get(route('documents.shared'), filters, {
        preserveState: true,
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Shared Documents" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                Shared Documents
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Flash Message -->
                <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 10-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.137-.089l4.06-5.5z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-300">{{ $page.props.flash.success }}</p>
                        </div>
                    </div>
                </div>
                <!-- Search -->
                <div class="mb-6">
                    <SearchBar 
                        :initial-search="filters.search" 
                        placeholder="Search shared documents..."
                        @search="(search) => applyFilter({ search })"
                    />
                </div>

                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-orange-600 dark:border-orange-500">
                    <div v-if="documents.data.length === 0" class="p-6 text-center py-12">
                        <DocumentIcon class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" />
                        <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No shared documents yet</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            When someone shares a document with you, it will appear here.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Document
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Owner
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Permission
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Shared On
                                    </th>
                                    <th class="relative px-6 py-3 bg-amber-50 dark:bg-zinc-800">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-amber-200 dark:divide-zinc-700">
                                <tr v-for="document in documents.data" :key="document.id" class="hover:bg-amber-50 dark:hover:bg-zinc-800 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <DocumentIcon class="h-8 w-8 text-zinc-400 dark:text-zinc-600 mr-3" />
                                            <div>
                                                <Link
                                                    :href="route('documents.show', document.id)"
                                                    class="text-sm font-bold text-zinc-900 dark:text-zinc-100 hover:text-amber-600 dark:hover:text-amber-400"
                                                >
                                                    {{ document.title }}
                                                </Link>
                                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ formatFileSize(document.size) }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <UserIcon class="h-5 w-5 text-zinc-400 dark:text-zinc-600 mr-2" />
                                            <div>
                                                <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                    {{ document.owner.name }}
                                                </div>
                                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ document.owner.email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span 
                                            v-if="document.category"
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                            :style="{
                                                backgroundColor: document.category.color + '20',
                                                color: document.category.color
                                            }"
                                        >
                                            {{ document.category.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300': document.permission === 'edit',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': document.permission === 'view'
                                            }"
                                        >
                                            {{ document.permission === 'edit' ? 'Can edit' : 'View only' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ formatDate(document.shared_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <Link
                                                :href="route('documents.show', document.id)"
                                                class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            <a
                                                :href="route('documents.download', document.id)"
                                                class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-300"
                                            >
                                                <ArrowDownTrayIcon class="h-5 w-5" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="documents.links.length > 3" class="px-6 py-4 bg-amber-50 dark:bg-zinc-800 border-t border-amber-200 dark:border-zinc-700">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-for="link in documents.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-bold rounded-md',
                                        link.active
                                            ? 'bg-orange-600 text-white'
                                            : 'text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-900 hover:bg-amber-50 dark:hover:bg-zinc-700',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    :disabled="!link.url"
                                    v-html="link.label"
                                />
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div v-if="documents?.meta">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                        Showing
                                        <span class="font-bold">{{ documents.meta.from || 0 }}</span>
                                        to
                                        <span class="font-bold">{{ documents.meta.to || 0 }}</span>
                                        of
                                        <span class="font-bold">{{ documents.meta.total || 0 }}</span>
                                        results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <Link
                                            v-for="link in documents.links"
                                            :key="link.label"
                                            :href="link.url"
                                            :class="[
                                                'relative inline-flex items-center px-4 py-2 text-sm font-bold border',
                                                link.active
                                                    ? 'z-10 bg-orange-600 border-orange-600 text-white'
                                                    : 'bg-white dark:bg-zinc-900 border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:bg-amber-50 dark:hover:bg-zinc-800',
                                                !link.url && 'opacity-50 cursor-not-allowed',
                                                documents.links.indexOf(link) === 0 && 'rounded-l-md',
                                                documents.links.indexOf(link) === documents.links.length - 1 && 'rounded-r-md'
                                            ]"
                                            :disabled="!link.url"
                                            v-html="link.label"
                                        />
                                    </nav>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
