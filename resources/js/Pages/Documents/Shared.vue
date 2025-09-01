<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import { 
    DocumentIcon, 
    FolderIcon, 
    TagIcon, 
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
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Shared Documents
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Flash Message -->
                <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 10-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.137-.089l4.06-5.5z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
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

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div v-if="documents.data.length === 0" class="p-6 text-center">
                        <DocumentIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No shared documents yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            When someone shares a document with you, it will appear here.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Document
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Owner
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Permission
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Shared On
                                    </th>
                                    <th class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="document in documents.data" :key="document.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <DocumentIcon class="h-8 w-8 text-gray-400 mr-3" />
                                            <div>
                                                <Link
                                                    :href="route('documents.show', document.id)"
                                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                                                >
                                                    {{ document.title }}
                                                </Link>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ formatFileSize(document.size) }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <UserIcon class="h-5 w-5 text-gray-400 mr-2" />
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ document.owner.name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
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
                                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': document.permission === 'edit',
                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': document.permission === 'view'
                                            }"
                                        >
                                            {{ document.permission === 'edit' ? 'Can edit' : 'View only' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(document.shared_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <Link
                                                :href="route('documents.show', document.id)"
                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            <a
                                                :href="route('documents.download', document.id)"
                                                class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
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
                    <div v-if="documents.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-for="link in documents.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md',
                                        link.active 
                                            ? 'bg-blue-600 text-white' 
                                            : 'text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    :disabled="!link.url"
                                    v-html="link.label"
                                />
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Showing
                                        <span class="font-medium">{{ documents.meta.from }}</span>
                                        to
                                        <span class="font-medium">{{ documents.meta.to }}</span>
                                        of
                                        <span class="font-medium">{{ documents.meta.total }}</span>
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
                                                'relative inline-flex items-center px-4 py-2 text-sm font-medium',
                                                link.active 
                                                    ? 'z-10 bg-blue-50 dark:bg-blue-900 border-blue-500 text-blue-600 dark:text-blue-400' 
                                                    : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700',
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
