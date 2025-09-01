<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import Dropdown from '@/Components/Navigation/Dropdown.vue';
import DropdownLink from '@/Components/Navigation/DropdownLink.vue';
import Checkbox from '@/Components/Forms/Checkbox.vue';
import Modal from '@/Components/Common/Modal.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import DangerButton from '@/Components/Buttons/DangerButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import { 
    DocumentIcon, 
    FolderIcon, 
    TagIcon, 
    ShareIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    XMarkIcon,
    FunnelIcon,
    Squares2X2Icon,
    ListBulletIcon
} from '@heroicons/vue/24/outline';

// Import our new composables
import { useSearch } from '@/Composables/useSearch.js';
import { usePagination } from '@/Composables/usePagination.js';
import { useBulkOperations } from '@/Composables/useBulkOperations.js';

interface Document {
    id: number;
    title: string;
    file_name: string;
    file_type: string;
    size: number;
    created_at: string;
    updated_at: string;
    category?: {
        id: number;
        name: string;
        color: string;
    };
    tags: Array<{
        id: number;
        name: string;
    }>;
    shared_with_count: number;
    file?: {
        id: number;
        url: string;
        pdfUrl: string | null;
        extension: string;
        size?: number;
    } | null;
}

interface Props {
    documents: {
        data: Document[];
        links: any;
        meta: any;
    };
    categories: Array<{
        id: number;
        name: string;
        color: string;
    }>;
    filters: {
        search?: string;
        category?: number;
        tag?: string;
        date_from?: string;
        date_to?: string;
    };
}

const props = defineProps<Props>();

// Use our new composables
const { search, isSearching, clearSearch } = useSearch(
    props.filters.search || '', 
    'documents.index',
    { onlyKeys: ['category', 'tag', 'date_from', 'date_to'] }
);

const { goToPage, changePerPage, goToNext, goToPrev } = usePagination(
    'documents.index',
    { onlyKeys: ['search', 'category', 'tag', 'date_from', 'date_to'] }
);

const {
    selectedItems: selectedDocuments,
    allSelected,
    someSelected,
    hasSelection,
    selectedCount,
    isProcessing: isBulkProcessing,
    toggleSelectAll,
    toggleSelect,
    isSelected,
    performBulkAction,
    performBulkDelete,
    clearSelection
} = useBulkOperations(computed(() => props.documents.data));

// Local state
const showFilters = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
const showDocumentDrawer = ref(false);
const selectedDocument = ref<Document | null>(null);

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

const viewDocument = (document: Document) => {
    selectedDocument.value = document;
    showDocumentDrawer.value = true;
};

const deleteSelected = () => {
    performBulkDelete(
        'documents.bulk-delete',
        `Are you sure you want to delete ${selectedCount.value} document(s)? This action cannot be undone.`
    );
};

const downloadSelected = () => {
    if (!hasSelection.value) return;
    
    window.location.href = route('documents.download-bulk', { 
        ids: selectedDocuments.value 
    });
};

const applyFilter = (newFilters: any) => {
    router.get(route('documents.index'), newFilters, {
        preserveState: true,
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Documents
                </h2>
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('documents.upload')"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Upload Document
                    </Link>
                </div>
            </div>
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

                <!-- Search and Filters -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search documents..."
                                class="block w-64 rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                            <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                            </div>
                        </div>
                        <button
                            @click="showFilters = !showFilters"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <FunnelIcon class="h-5 w-5 mr-2" />
                            Filters
                        </button>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="viewMode = 'grid'"
                            :class="[
                                'p-2 rounded',
                                viewMode === 'grid' 
                                    ? 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400' 
                                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                            ]"
                        >
                            <Squares2X2Icon class="h-5 w-5" />
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            :class="[
                                'p-2 rounded',
                                viewMode === 'list' 
                                    ? 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400' 
                                    : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200'
                            ]"
                        >
                            <ListBulletIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div v-if="hasSelection" class="mb-4 bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ selectedCount }} document(s) selected
                        </span>
                        <div class="flex items-center space-x-2">
                            <button
                                @click="downloadSelected"
                                :disabled="isBulkProcessing"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4 mr-1" />
                                Download
                            </button>
                            <button
                                @click="deleteSelected"
                                :disabled="isBulkProcessing"
                                class="inline-flex items-center px-3 py-1.5 border border-red-300 dark:border-red-600 rounded-md text-sm font-medium text-red-700 dark:text-red-400 bg-white dark:bg-gray-700 hover:bg-red-50 dark:hover:bg-red-900 disabled:opacity-50"
                            >
                                <TrashIcon class="h-4 w-4 mr-1" />
                                <span v-if="isBulkProcessing">Deleting...</span>
                                <span v-else>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Documents Grid/List -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <!-- Empty State -->
                    <div v-if="documents.data.length === 0" class="p-12 text-center">
                        <DocumentIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No documents found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Upload your first document to get started.
                        </p>
                        <div class="mt-6">
                            <Link
                                :href="route('documents.upload')"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                            >
                                Upload Document
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Grid View -->
                    <div v-else-if="viewMode === 'grid'" class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="document in documents.data"
                            :key="document.id"
                            class="relative group border dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition-shadow"
                        >
                            <div class="absolute top-4 right-4">
                                <Checkbox
                                    :checked="isSelected(document.id)"
                                    @change="toggleSelect(document.id)"
                                />
                            </div>
                            
                            <div @click="viewDocument(document)" class="cursor-pointer">
                                <DocumentIcon class="h-12 w-12 text-gray-400 mb-3" />
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1 truncate">
                                    {{ document.title }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                    {{ formatFileSize(document.size) }} • {{ formatDate(document.created_at) }}
                                </p>
                                
                                <div v-if="document.category" class="mb-2">
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :style="{
                                            backgroundColor: document.category.color + '20',
                                            color: document.category.color
                                        }"
                                    >
                                        <FolderIcon class="h-3 w-3 mr-1" />
                                        {{ document.category.name }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <Link
                                        :href="route('documents.show', document.id)"
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <EyeIcon class="h-5 w-5" />
                                    </Link>
                                    <div v-if="document.shared_with_count > 0" class="relative text-green-600 dark:text-green-400">
                                        <ShareIcon class="h-5 w-5" />
                                        <span class="absolute -top-2 -right-2 inline-flex items-center justify-center rounded-full bg-green-600 text-white text-[10px] h-4 min-w-4 px-1">
                                            {{ document.shared_with_count }}
                                        </span>
                                    </div>
                                </div>
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('documents.download', document.id)">
                                            Download
                                        </DropdownLink>
                                        <DropdownLink :href="route('documents.show', document.id)">
                                            Edit
                                        </DropdownLink>
                                        <DropdownLink 
                                            as="button" 
                                            @click="router.delete(route('documents.destroy', document.id))"
                                            class="text-red-600 dark:text-red-400"
                                        >
                                            Delete
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <Checkbox
                                            :checked="allSelected"
                                            :indeterminate="someSelected"
                                            @change="toggleSelectAll"
                                        />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Document
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Size
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="document in documents.data" :key="document.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Checkbox
                                            :checked="isSelected(document.id)"
                                            @change="toggleSelect(document.id)"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <DocumentIcon class="h-8 w-8 text-gray-400 mr-3" />
                                            <div>
                                                <button
                                                    @click="viewDocument(document)"
                                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                                                >
                                                    {{ document.title }}
                                                </button>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ document.file_name }}
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatFileSize(document.size) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(document.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                                    </svg>
                                                </button>
                                            </template>
                                            <template #content>
                                                <DropdownLink :href="route('documents.show', document.id)">
                                                    View
                                                </DropdownLink>
                                                <DropdownLink :href="route('documents.download', document.id)">
                                                    Download
                                                </DropdownLink>
                                                <DropdownLink 
                                                    as="button" 
                                                    @click="router.delete(route('documents.destroy', document.id))"
                                                    class="text-red-600 dark:text-red-400"
                                                >
                                                    Delete
                                                </DropdownLink>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Enhanced Pagination using composables -->
                    <div v-if="documents.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <button
                                    @click="goToPrev(documents.meta.current_page)"
                                    :disabled="documents.meta.current_page <= 1"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Previous
                                </button>
                                <button
                                    @click="goToNext(documents.meta.current_page)"
                                    :disabled="documents.meta.current_page >= documents.meta.last_page"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Next
                                </button>
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
                                        <button
                                            v-for="link in documents.links"
                                            :key="link.label"
                                            @click="link.url ? goToPage(new URL(link.url).searchParams.get('page') || 1) : null"
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

                <!-- Document Drawer -->
                <Teleport to="body">
                    <div v-if="showDocumentDrawer" class="fixed inset-0 overflow-hidden z-50">
                        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showDocumentDrawer = false"></div>
                        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white dark:bg-gray-800 shadow-xl">
                            <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ selectedDocument?.title }}
                                </h3>
                                <button
                                    @click="showDocumentDrawer = false"
                                    class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                                >
                                    <XMarkIcon class="h-6 w-6" />
                                </button>
                            </div>
                            <div class="p-4">
                                <div class="aspect-[8.5/11] bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center overflow-hidden relative">
                                    <template v-if="selectedDocument?.file?.url">
                                        <img
                                            v-if="selectedDocument.file.extension !== 'pdf'"
                                            :src="selectedDocument.file.url"
                                            class="w-full h-auto"
                                            :alt="selectedDocument.title"
                                        />
                                        <div v-else class="text-center">
                                            <button
                                                @click="window.open(selectedDocument.file.pdfUrl || selectedDocument.file.url, '_blank', 'noopener')"
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700"
                                            >
                                                Open PDF
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="text-center text-gray-500 dark:text-gray-300">No preview available</div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </Teleport>
            </div>
        </div>
    </AuthenticatedLayout>
</template>