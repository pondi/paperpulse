<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import Checkbox from '@/Components/Forms/Checkbox.vue';
import Modal from '@/Components/Common/Modal.vue';
import DangerButton from '@/Components/Buttons/DangerButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import DocumentDrawer from '@/Components/Domain/DocumentDrawer.vue';
import {
    DocumentIcon,
    ShareIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    EyeIcon,
    FunnelIcon,
    Squares2X2Icon,
    ListBulletIcon,
} from '@heroicons/vue/24/outline';
import { useDateFormatter } from '@/Composables/useDateFormatter';

interface Document {
    id: number;
    title: string;
    note?: string | null;
    description?: string | null;
    file_name: string;
    file_type: string;
    size: number;
    created_at: string;
    updated_at: string;
    uploaded_at?: string;
    entity_type?: string;
    entity_details?: {
        // Invoice
        date?: string;
        due_date?: string;
        total?: number;
        currency?: string;
        status?: string;
        from?: string;
        to?: string;
        payment_terms?: string;
        invoice_number?: string;
        // Contract
        effective_date?: string;
        expiration_date?: string;
        type?: string;
        parties?: string[];
        contract_value?: number;
        summary?: string;
        // Voucher
        expiry_date?: string;
        discount?: number;
        code?: string;
        voucher_type?: string;
        original_value?: number;
        current_value?: number;
        is_redeemed?: boolean;
        // Warranty
        product?: string;
        manufacturer?: string;
        warranty_type?: string;
        start_date?: string;
        coverage_type?: string;
        // BankStatement
        closing_balance?: number;
        bank_name?: string;
        opening_balance?: number;
        transaction_count?: number;
        period_start?: string;
        period_end?: string;
        // Document
        document_type?: string;
        document_date?: string;
    };
    category?: {
        id: number;
        name: string;
        color: string;
    };
    tags: Array<{
        id: number;
        name: string;
        color?: string;
    }>;
    shared_with_count: number;
    file?: {
        id: number;
        url: string;
        pdfUrl: string | null;
        previewUrl?: string | null;
        extension: string;
        size?: number;
        has_preview?: boolean;
        is_pdf?: boolean;
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

const { formatDate: formatDateLocalized, formatCurrency: formatCurrencyLocalized } = useDateFormatter();

const selectedDocuments = ref<number[]>([]);
const showDeleteModal = ref(false);
const showFilters = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
const isDrawerOpen = ref(false);
const selectedDocument = ref<Document | null>(null);

onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

const allSelected = computed(() => {
    return selectedDocuments.value.length === props.documents.data.length && props.documents.data.length > 0;
});

const someSelected = computed(() => {
    return selectedDocuments.value.length > 0 && selectedDocuments.value.length < props.documents.data.length;
});

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

const toggleAll = () => {
    if (allSelected.value) {
        selectedDocuments.value = [];
    } else {
        selectedDocuments.value = props.documents.data.map(doc => doc.id);
    }
};

const toggleDocument = (id: number) => {
    const index = selectedDocuments.value.indexOf(id);
    if (index > -1) {
        selectedDocuments.value.splice(index, 1);
    } else {
        selectedDocuments.value.push(id);
    }
};

const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);

const handleResize = () => {
    windowWidth.value = window.innerWidth;
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

const containerStyle = computed(() => ({
    paddingLeft: '1.5rem',
    paddingRight: isDrawerOpen.value ? (windowWidth.value < 640 ? '0' : '532px') : '1.5rem',
    maxWidth: '100vw',
    transition: 'padding-right 500ms ease-in-out',
}));

const viewDocument = (doc: Document) => {
    selectedDocument.value = doc;
    isDrawerOpen.value = true;
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    setTimeout(() => {
        selectedDocument.value = null;
    }, 500);
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isDrawerOpen.value) {
        closeDrawer();
    }
};

const deleteSelected = () => {
    router.delete(route('documents.destroy-bulk'), {
        data: { ids: selectedDocuments.value },
        onSuccess: () => {
            selectedDocuments.value = [];
            showDeleteModal.value = false;
        }
    });
};

const downloadSelected = () => {
    window.location.href = route('documents.download-bulk', { ids: selectedDocuments.value });
};

const applyFilter = (filters: any) => {
    router.get(route('documents.index'), filters, {
        preserveState: true,
        preserveScroll: true
    });
};

const thumbnailErrors = ref<Set<number>>(new Set());

const handleThumbnailError = (documentId: number) => {
    thumbnailErrors.value.add(documentId);
};

// Helper to get correct route name based on entity type
const getShowRoute = (document: Document) => {
    const entityType = document.entity_type || 'document';

    switch (entityType) {
        case 'contract':
            return route('contracts.show', document.id);
        case 'invoice':
            return route('invoices.show', document.id);
        case 'voucher':
            return route('vouchers.show', document.id);
        case 'bank_statement':
        case 'bankstatement':
            return route('bank-statements.show', document.id);
        case 'document':
        default:
            return route('documents.show', document.id);
    }
};

const getEntityTypeBadge = (entityType?: string) => {
    switch (entityType) {
        case 'invoice':
            return { label: 'Invoice', bg: 'bg-blue-100 dark:bg-blue-900/50', text: 'text-blue-700 dark:text-blue-300' };
        case 'contract':
            return { label: 'Contract', bg: 'bg-purple-100 dark:bg-purple-900/50', text: 'text-purple-700 dark:text-purple-300' };
        case 'voucher':
            return { label: 'Voucher', bg: 'bg-green-100 dark:bg-green-900/50', text: 'text-green-700 dark:text-green-300' };
        case 'warranty':
            return { label: 'Warranty', bg: 'bg-orange-100 dark:bg-orange-900/50', text: 'text-orange-700 dark:text-orange-300' };
        case 'bank_statement':
        case 'bankstatement':
            return { label: 'Statement', bg: 'bg-teal-100 dark:bg-teal-900/50', text: 'text-teal-700 dark:text-teal-300' };
        case 'document':
        default:
            return { label: 'Document', bg: 'bg-zinc-100 dark:bg-zinc-700', text: 'text-zinc-700 dark:text-zinc-300' };
    }
};

const formatCurrency = (amount?: number, currency?: string) => {
    if (amount == null) return null;
    return formatCurrencyLocalized(amount, currency);
};
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    Documents
                </h2>
                <div class="flex items-center space-x-4">
                    <Link
                        :href="route('documents.upload')"
                        class="inline-flex items-center px-4 py-2 bg-zinc-900 dark:bg-orange-600 border border-transparent rounded-md font-bold text-sm text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-all duration-200"
                    >
                        Upload Document
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto transition-all duration-500 ease-in-out" :style="containerStyle">
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
                        <SearchBar 
                            :initial-search="filters.search" 
                            placeholder="Search documents..."
                            @search="(search) => applyFilter({ ...filters, search })"
                        />
                        <button
                            @click="showFilters = !showFilters"
                            class="inline-flex items-center px-4 py-2 border-2 border-zinc-900 dark:border-zinc-600 rounded-md shadow-sm text-sm font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200"
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
                                    ? 'bg-amber-100 dark:bg-orange-900 text-amber-600 dark:text-amber-400' 
                                    : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200'
                            ]"
                        >
                            <Squares2X2Icon class="h-5 w-5" />
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            :class="[
                                'p-2 rounded',
                                viewMode === 'list' 
                                    ? 'bg-amber-100 dark:bg-orange-900 text-amber-600 dark:text-amber-400' 
                                    : 'text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200'
                            ]"
                        >
                            <ListBulletIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div v-if="selectedDocuments.length > 0" class="mb-6 bg-amber-50 dark:bg-zinc-800 rounded-lg p-4 shadow-lg border-l-4 border-amber-600 dark:border-amber-500">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                            {{ selectedDocuments.length }} document(s) selected
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="downloadSelected"
                                class="inline-flex items-center px-3 py-1.5 border-2 border-zinc-900 dark:border-zinc-600 rounded-md text-sm font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-700 hover:bg-amber-50 dark:hover:bg-zinc-600 transition-all duration-200"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4 mr-1" />
                                Download
                            </button>
                            <button
                                @click="showDeleteModal = true"
                                class="inline-flex items-center px-3 py-1.5 border-2 border-red-600 dark:border-red-600 rounded-md text-sm font-bold text-red-700 dark:text-red-400 bg-white dark:bg-zinc-700 hover:bg-red-50 dark:hover:bg-red-900 transition-all duration-200"
                            >
                                <TrashIcon class="h-4 w-4 mr-1" />
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Documents Grid/List -->
                <div class="bg-white dark:bg-zinc-900 overflow-visible shadow-lg sm:rounded-lg border-t-4 border-orange-600 dark:border-orange-500">
                    <!-- Empty State -->
                    <div v-if="documents.data.length === 0" class="p-12 text-center">
                        <DocumentIcon class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" />
                        <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No documents found</h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Upload your first document to get started.
                        </p>
                        <div class="mt-8">
                            <Link
                                :href="route('documents.upload')"
                                class="inline-flex items-center px-6 py-3 bg-zinc-900 dark:bg-orange-600 border border-transparent rounded-md font-bold text-sm text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-orange-700 transition-all duration-200"
                            >
                                Upload Document
                            </Link>
                        </div>
                    </div>
                    <!-- Grid View -->
                    <div v-else-if="viewMode === 'grid'" class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="document in documents.data"
                            :key="document.id"
                            class="relative group bg-white dark:bg-zinc-900 border border-amber-200 dark:border-zinc-700 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 flex flex-col"
                        >
                            <!-- Checkbox -->
                            <div class="absolute top-3 right-3 z-10">
                                <Checkbox
                                    :checked="selectedDocuments.includes(document.id)"
                                    @change="toggleDocument(document.id)"
                                />
                            </div>

                            <!-- Clickable area -->
                            <div @click="viewDocument(document)" class="cursor-pointer p-4 flex-1">
                                <!-- Entity Type Badge -->
                                <div class="flex items-center gap-2 mb-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold',
                                            getEntityTypeBadge(document.entity_type).bg,
                                            getEntityTypeBadge(document.entity_type).text,
                                        ]"
                                    >
                                        {{ getEntityTypeBadge(document.entity_type).label }}
                                    </span>
                                    <span v-if="document.category"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                        :style="{ backgroundColor: document.category.color + '20', color: document.category.color }"
                                    >
                                        {{ document.category.name }}
                                    </span>
                                </div>

                                <!-- Thumbnail Preview -->
                                <div class="aspect-[4/3] bg-amber-50 dark:bg-zinc-800 rounded-lg overflow-hidden relative mb-3">
                                    <template v-if="document.file?.has_preview && !thumbnailErrors.has(document.id)">
                                        <img
                                            :src="document.file.previewUrl || document.file.url"
                                            :alt="document.title"
                                            class="w-full h-full object-cover"
                                            @error="handleThumbnailError(document.id)"
                                        />
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center justify-center h-full">
                                            <DocumentIcon class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                                        </div>
                                    </template>
                                </div>

                                <!-- Title -->
                                <h3 class="text-base font-black text-zinc-900 dark:text-zinc-100 mb-1 truncate">
                                    {{ document.title }}
                                </h3>

                                <!-- Entity-specific details -->
                                <div v-if="document.entity_details" class="text-sm text-zinc-600 dark:text-zinc-400 space-y-0.5 mb-2">
                                    <!-- Invoice details -->
                                    <template v-if="document.entity_type === 'invoice'">
                                        <p v-if="document.entity_details.from" class="truncate">
                                            From: {{ document.entity_details.from }}
                                        </p>
                                        <div class="flex items-center justify-between">
                                            <span v-if="document.entity_details.date">{{ formatDate(document.entity_details.date) }}</span>
                                            <span v-if="document.entity_details.total" class="font-bold text-zinc-900 dark:text-zinc-100">
                                                {{ formatCurrency(document.entity_details.total, document.entity_details.currency) }}
                                            </span>
                                        </div>
                                        <span v-if="document.entity_details.status"
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                document.entity_details.status === 'paid' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'
                                            ]"
                                        >
                                            {{ document.entity_details.status }}
                                        </span>
                                    </template>
                                    <!-- Contract details -->
                                    <template v-else-if="document.entity_type === 'contract'">
                                        <p v-if="document.entity_details.type" class="truncate">{{ document.entity_details.type }}</p>
                                        <p v-if="document.entity_details.effective_date">
                                            {{ formatDate(document.entity_details.effective_date) }}
                                            <span v-if="document.entity_details.expiration_date"> - {{ formatDate(document.entity_details.expiration_date) }}</span>
                                        </p>
                                        <span v-if="document.entity_details.status"
                                            :class="[
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                document.entity_details.status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300'
                                            ]"
                                        >
                                            {{ document.entity_details.status }}
                                        </span>
                                    </template>
                                    <!-- Voucher details -->
                                    <template v-else-if="document.entity_type === 'voucher'">
                                        <p v-if="document.entity_details.code" class="font-mono truncate">{{ document.entity_details.code }}</p>
                                        <p v-if="document.entity_details.expiry_date">Expires: {{ formatDate(document.entity_details.expiry_date) }}</p>
                                    </template>
                                    <!-- Warranty details -->
                                    <template v-else-if="document.entity_type === 'warranty'">
                                        <p v-if="document.entity_details.product" class="truncate">{{ document.entity_details.product }}</p>
                                        <p v-if="document.entity_details.expiry_date">Expires: {{ formatDate(document.entity_details.expiry_date) }}</p>
                                    </template>
                                    <!-- BankStatement details -->
                                    <template v-else-if="document.entity_type === 'bankstatement' || document.entity_type === 'bank_statement'">
                                        <p v-if="document.entity_details.date">{{ formatDate(document.entity_details.date) }}</p>
                                        <p v-if="document.entity_details.closing_balance != null" class="font-bold">
                                            Balance: {{ formatCurrency(document.entity_details.closing_balance) }}
                                        </p>
                                    </template>
                                </div>

                                <!-- File info -->
                                <p class="text-xs text-zinc-500 dark:text-zinc-500 mb-2">
                                    {{ formatFileSize(document.size) }} &middot; {{ formatDate(document.created_at) }}
                                </p>

                                <!-- Note -->
                                <p v-if="document.note" class="text-sm text-zinc-600 dark:text-zinc-300 mb-2 line-clamp-2">
                                    {{ document.note }}
                                </p>

                                <!-- Tags -->
                                <div v-if="document.tags && document.tags.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="tag in document.tags.slice(0, 3)"
                                        :key="tag.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200"
                                    >
                                        {{ tag.name }}
                                    </span>
                                    <span
                                        v-if="document.tags && document.tags.length > 3"
                                        class="text-xs text-zinc-500 dark:text-zinc-400"
                                    >
                                        +{{ document.tags.length - 3 }} more
                                    </span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="px-4 py-3 border-t border-amber-100 dark:border-zinc-800 flex items-center justify-between">
                                <div class="flex items-center gap-1">
                                    <div v-if="document.shared_with_count > 0" class="relative text-green-600 dark:text-green-400 mr-1">
                                        <ShareIcon class="h-4 w-4" />
                                        <span class="absolute -top-2 -right-2 inline-flex items-center justify-center rounded-full bg-green-600 text-white text-[10px] h-3.5 min-w-3.5 px-0.5">
                                            {{ document.shared_with_count }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="getShowRoute(document)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        <EyeIcon class="h-4 w-4" />
                                        View
                                    </Link>
                                    <a
                                        :href="route('documents.download', document.id)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        <ArrowDownTrayIcon class="h-4 w-4" />
                                        Download
                                    </a>
                                    <button
                                        @click="router.delete(route('documents.destroy', document.id))"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-zinc-800 transition-colors"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left">
                                        <Checkbox
                                            :checked="allSelected"
                                            :indeterminate="someSelected"
                                            @change="toggleAll"
                                        />
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider w-24">
                                        Type
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Document
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Tags
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Shared
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Size
                                    </th>
                                    <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="relative px-6 py-3 bg-amber-50 dark:bg-zinc-800">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-amber-200 dark:divide-zinc-700">
                                <tr v-for="document in documents.data" :key="document.id" class="hover:bg-amber-50 dark:hover:bg-zinc-800 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Checkbox
                                            :checked="selectedDocuments.includes(document.id)"
                                            @change="toggleDocument(document.id)"
                                        />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold',
                                                getEntityTypeBadge(document.entity_type).bg,
                                                getEntityTypeBadge(document.entity_type).text,
                                            ]"
                                        >
                                            {{ getEntityTypeBadge(document.entity_type).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <DocumentIcon class="h-8 w-8 text-zinc-400 mr-3" />
                                            <div>
                                                <button
                                                    @click="viewDocument(document)"
                                                    class="text-sm font-bold text-zinc-900 dark:text-zinc-100 hover:text-amber-600 dark:hover:text-amber-400"
                                                >
                                                    {{ document.title }}
                                                    <span
                                                        v-if="document.shared_with_count > 0"
                                                        class="ml-2 inline-flex items-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300 px-2 py-0.5 text-[10px] font-medium"
                                                        title="Shared count"
                                                    >
                                                        <ShareIcon class="h-3 w-3 mr-1" /> {{ document.shared_with_count }}
                                                    </span>
                                                </button>
                                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ document.file_name }}
                                                </div>
                                                <div
                                                    v-if="document.note"
                                                    class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-1"
                                                >
                                                    {{ document.note }}
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
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            <span 
                                                v-for="tag in (document.tags || []).slice(0, 2)" 
                                                :key="tag.id"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200"
                                            >
                                                {{ tag.name }}
                                            </span>
                                            <span 
                                                v-if="document.tags && document.tags.length > 2"
                                                class="text-xs text-zinc-500 dark:text-zinc-400"
                                            >
                                                +{{ document.tags ? document.tags.length - 2 : 0 }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span
                                            v-if="document.shared_with_count > 0"
                                            class="inline-flex items-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300 px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ document.shared_with_count }}
                                        </span>
                                        <span v-else class="text-zinc-400">0</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ formatFileSize(document.size) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ formatDate(document.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-3">
                                            <Link
                                                :href="getShowRoute(document)"
                                                class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 transition-colors"
                                                title="View"
                                            >
                                                <EyeIcon class="h-5 w-5" />
                                            </Link>
                                            <a
                                                :href="route('documents.download', document.id)"
                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                                                title="Download"
                                            >
                                                <ArrowDownTrayIcon class="h-5 w-5" />
                                            </a>
                                            <button
                                                @click="router.delete(route('documents.destroy', document.id))"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                                title="Delete"
                                            >
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="documents.links.length > 3" class="px-6 py-4 border-t border-amber-200 dark:border-zinc-700">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-for="link in documents.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md',
                                        link.active 
                                            ? 'bg-orange-600 text-white' 
                                            : 'text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    :disabled="!link.url"
                                    v-html="link.label"
                                />
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div v-if="documents?.meta">
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        Showing
                                        <span class="font-black">{{ documents.meta.from || 0 }}</span>
                                        to
                                        <span class="font-black">{{ documents.meta.to || 0 }}</span>
                                        of
                                        <span class="font-black">{{ documents.meta.total || 0 }}</span>
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
                                                    ? 'z-10 bg-amber-50 dark:bg-orange-900 border-amber-500 text-amber-600 dark:text-amber-400' 
                                                    : 'bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 text-zinc-500 dark:text-zinc-400 hover:bg-amber-50 dark:hover:bg-zinc-700',
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

                <!-- Delete Confirmation Modal (bulk) -->
                <Modal :show="showDeleteModal" @close="showDeleteModal = false">
                    <div class="p-6">
                        <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-100">
                            Delete Documents
                        </h2>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            Are you sure you want to delete {{ selectedDocuments.length }} document(s)? This action cannot be undone.
                        </p>
                        <div class="mt-6 flex justify-end gap-3">
                            <SecondaryButton @click="showDeleteModal = false">
                                Cancel
                            </SecondaryButton>
                            <DangerButton @click="deleteSelected">
                                Delete
                            </DangerButton>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>

        <!-- Document Drawer -->
        <DocumentDrawer
            :document="selectedDocument"
            :show="isDrawerOpen"
            @close="closeDrawer"
        />
    </AuthenticatedLayout>
</template>
