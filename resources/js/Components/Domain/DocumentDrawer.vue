<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PdfViewer from '@/Components/Common/PdfViewer.vue';
import {
    DocumentIcon,
    TrashIcon,
    XMarkIcon,
    ArrowTopRightOnSquareIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';
import { EllipsisVerticalIcon } from '@heroicons/vue/20/solid';
import {
    Menu,
    MenuButton,
    MenuItem,
    MenuItems,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
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
    entity_details?: Record<string, any>;
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

const props = defineProps<{
    document: Document | null;
    show: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { formatDate: formatDateLocalized, formatCurrency: formatCurrencyLocalized } = useDateFormatter();

const showDeleteConfirm = ref(false);
const imageError = ref(false);

const handleImageError = () => {
    imageError.value = true;
};

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatCurrency = (amount?: number, currency?: string) => {
    if (amount == null) return null;
    return formatCurrencyLocalized(amount, currency);
};

const getShowRoute = (doc: Document) => {
    const entityType = doc.entity_type || 'document';
    switch (entityType) {
        case 'contract':
            return route('contracts.show', doc.id);
        case 'invoice':
            return route('invoices.show', doc.id);
        case 'voucher':
            return route('vouchers.show', doc.id);
        case 'bank_statement':
        case 'bankstatement':
            return route('bank-statements.show', doc.id);
        case 'document':
        default:
            return route('documents.show', doc.id);
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

const getDrawerHeroValue = (doc: Document) => {
    const details = doc.entity_details;
    if (!details) return null;
    switch (doc.entity_type) {
        case 'invoice':
            return details.total != null ? formatCurrencyLocalized(details.total, details.currency) : null;
        case 'contract':
            return details.contract_value != null ? formatCurrencyLocalized(details.contract_value, details.currency) : null;
        case 'voucher':
            return details.original_value != null ? formatCurrencyLocalized(details.original_value, details.currency) : null;
        case 'bank_statement':
        case 'bankstatement':
            return details.closing_balance != null ? formatCurrencyLocalized(details.closing_balance, details.currency) : null;
        default:
            return null;
    }
};

const getDrawerSubtitle = (doc: Document) => {
    const details = doc.entity_details;
    if (!details) return null;
    switch (doc.entity_type) {
        case 'invoice':
            return details.from || null;
        case 'contract':
            return details.type || null;
        case 'voucher':
            return details.code || null;
        case 'warranty':
            return details.product || null;
        case 'bank_statement':
        case 'bankstatement':
            return details.bank_name || null;
        case 'document':
            return details.document_type || null;
        default:
            return null;
    }
};

const getStatusBadge = (status?: string) => {
    if (!status) return null;
    const positiveStatuses = ['paid', 'active', 'valid'];
    const isPositive = positiveStatuses.includes(status.toLowerCase());
    return {
        label: status.charAt(0).toUpperCase() + status.slice(1),
        class: isPositive
            ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
    };
};

const openPdf = (url: string) => {
    window.open(url, '_blank', 'noopener,noreferrer');
};

const closeDrawer = () => {
    emit('close');
    setTimeout(() => {
        showDeleteConfirm.value = false;
        imageError.value = false;
    }, 500);
};

const deleteDocument = () => {
    if (!props.document) return;
    router.delete(route('documents.destroy', props.document.id), {
        onSuccess: () => {
            closeDrawer();
        },
    });
};
</script>

<template>
    <!-- Document Drawer -->
    <TransitionRoot as="template" :show="show">
        <div class="fixed right-0 flex max-w-full z-10" style="top: 65px; bottom: 0;">
            <TransitionChild
                as="template"
                enter="transform transition ease-in-out duration-500"
                enter-from="translate-x-full"
                enter-to="translate-x-0"
                leave="transform transition ease-in-out duration-500"
                leave-from="translate-x-0"
                leave-to="translate-x-full"
            >
                <div class="w-screen sm:w-[500px] bg-white dark:bg-zinc-800 shadow-xl border-l border-orange-200 dark:border-zinc-700 h-full">
                    <div class="flex h-full flex-col overflow-y-scroll">
                        <!-- Sticky Header -->
                        <div class="sticky top-0 z-10 bg-white dark:bg-zinc-800 px-4 py-6 sm:px-6 border-b border-orange-200 dark:border-zinc-700">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span
                                        v-if="document?.entity_type"
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold shrink-0',
                                            getEntityTypeBadge(document.entity_type).bg,
                                            getEntityTypeBadge(document.entity_type).text,
                                        ]"
                                    >
                                        {{ getEntityTypeBadge(document.entity_type).label }}
                                    </span>
                                    <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ document?.title }}
                                    </h2>
                                </div>
                                <div class="ml-3 flex h-7 items-center shrink-0">
                                    <button type="button" class="relative rounded-md bg-white dark:bg-zinc-800 text-zinc-400 hover:text-zinc-500 focus:ring-2 focus:ring-orange-500" @click="closeDrawer">
                                        <span class="absolute -inset-2.5" />
                                        <span class="sr-only">Close</span>
                                        <XMarkIcon class="size-6" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="relative flex-1 px-4 sm:px-6">
                            <div>
                                <div class="pb-1 sm:pb-6">
                                    <div>
                                        <!-- Image Preview -->
                                        <div class="relative h-[400px] overflow-y-auto">
                                            <div class="flex justify-center bg-white dark:bg-zinc-900">
                                                <div class="w-full">
                                                    <template v-if="document?.file?.previewUrl || document?.file?.url">
                                                        <img
                                                            :src="document.file.previewUrl || document.file.url"
                                                            class="w-full h-auto"
                                                            alt="Document preview"
                                                            @error="handleImageError"
                                                            :class="{ 'hidden': imageError }"
                                                        />
                                                        <div v-if="imageError" class="flex flex-col items-center justify-center h-[400px] bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-lg">
                                                            <ExclamationCircleIcon class="size-16 text-red-400 mb-4" />
                                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">Preview unavailable</span>
                                                        </div>
                                                    </template>
                                                    <div v-else class="flex flex-col items-center justify-center h-[400px] bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-lg">
                                                        <DocumentIcon class="size-16 text-zinc-400 mb-4" />
                                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">No preview available</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hero Value & Subtitle -->
                                        <div class="mt-6 px-4 sm:mt-8 sm:flex sm:items-end sm:px-6">
                                            <div class="sm:flex-1">
                                                <div>
                                                    <div class="flex items-center">
                                                        <h3 v-if="getDrawerHeroValue(document)" class="text-xl font-black text-zinc-900 dark:text-zinc-100 sm:text-2xl">
                                                            {{ getDrawerHeroValue(document) }}
                                                        </h3>
                                                        <span
                                                            v-if="document?.entity_details?.status"
                                                            :class="[
                                                                'ml-2.5 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                                getStatusBadge(document.entity_details.status)?.class,
                                                            ]"
                                                        >
                                                            {{ getStatusBadge(document.entity_details.status)?.label }}
                                                        </span>
                                                    </div>
                                                    <p v-if="getDrawerSubtitle(document)" class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                                        {{ getDrawerSubtitle(document) }}
                                                    </p>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div class="mt-5 flex flex-wrap space-y-3 sm:space-x-3 sm:space-y-0">
                                                    <button
                                                        v-if="document?.file?.pdfUrl || document?.file?.is_pdf"
                                                        @click="openPdf(document.file.pdfUrl || document.file.url)"
                                                        type="button"
                                                        class="inline-flex w-full flex-1 items-center justify-center gap-x-2 px-5 py-2 bg-zinc-900 dark:bg-orange-600 border border-transparent rounded-md font-bold text-sm text-white uppercase tracking-widest hover:bg-zinc-800 dark:hover:bg-orange-700 shadow-sm hover:shadow transition-all duration-200"
                                                    >
                                                        <span>View PDF</span>
                                                        <ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" />
                                                    </button>
                                                    <Link
                                                        v-if="document"
                                                        :href="getShowRoute(document)"
                                                        class="inline-flex w-full flex-1 items-center justify-center px-5 py-2 bg-white dark:bg-zinc-800 border-2 border-zinc-900 dark:border-zinc-600 rounded-md font-bold text-sm text-zinc-900 dark:text-zinc-100 uppercase tracking-widest shadow-sm hover:bg-orange-50 dark:hover:bg-zinc-700 transition-all duration-200"
                                                    >
                                                        View Details
                                                    </Link>
                                                    <Menu as="div" class="relative inline-block text-left">
                                                        <MenuButton class="relative inline-flex items-center rounded-md bg-white dark:bg-zinc-800 p-2 text-zinc-400 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-orange-50 dark:hover:bg-zinc-700 transition-colors duration-200">
                                                            <span class="absolute -inset-1" />
                                                            <span class="sr-only">Open options</span>
                                                            <EllipsisVerticalIcon class="size-5" aria-hidden="true" />
                                                        </MenuButton>
                                                        <transition
                                                            enter-active-class="transition ease-out duration-100"
                                                            enter-from-class="transform opacity-0 scale-95"
                                                            enter-to-class="transform opacity-100 scale-100"
                                                            leave-active-class="transition ease-in duration-75"
                                                            leave-from-class="transform opacity-100 scale-100"
                                                            leave-to-class="transform opacity-0 scale-95"
                                                        >
                                                            <MenuItems class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white dark:bg-zinc-800 shadow-lg ring-1 ring-black/5 focus:outline-none">
                                                                <div class="py-1">
                                                                    <MenuItem v-slot="{ active }">
                                                                        <a
                                                                            v-if="document"
                                                                            :href="route('documents.download', document.id)"
                                                                            :class="[active ? 'bg-orange-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100' : 'text-zinc-700 dark:text-zinc-300', 'block w-full text-left px-4 py-2 text-sm font-medium transition-colors duration-200']"
                                                                        >
                                                                            Download
                                                                        </a>
                                                                    </MenuItem>
                                                                    <MenuItem v-slot="{ active }">
                                                                        <button
                                                                            @click="showDeleteConfirm = true"
                                                                            :class="[active ? 'bg-orange-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100' : 'text-zinc-700 dark:text-zinc-300', 'block w-full text-left px-4 py-2 text-sm font-medium transition-colors duration-200']"
                                                                        >
                                                                            Delete
                                                                        </button>
                                                                    </MenuItem>
                                                                </div>
                                                            </MenuItems>
                                                        </transition>
                                                    </Menu>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Entity-Specific Metadata -->
                                    <div class="px-4 pb-5 pt-5 sm:px-0 sm:pt-0 mt-6">
                                        <dl class="space-y-8 px-4 sm:space-y-6 sm:px-6">
                                            <!-- Invoice Metadata -->
                                            <template v-if="document?.entity_type === 'invoice'">
                                                <div v-if="document.entity_details?.from || document.entity_details?.to">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">From / To</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.from || 'N/A' }}
                                                        <span class="text-zinc-400 mx-1">&rarr;</span>
                                                        {{ document.entity_details.to || 'N/A' }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.invoice_number">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Number</dt>
                                                    <dd class="mt-1 text-sm font-mono text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.invoice_number }}
                                                    </dd>
                                                </div>
                                                <div class="flex gap-8">
                                                    <div v-if="document.entity_details?.date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.date) }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.due_date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Due Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.due_date) }}
                                                        </dd>
                                                    </div>
                                                </div>
                                                <div v-if="document.entity_details?.payment_terms">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Payment Terms</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.payment_terms }}
                                                    </dd>
                                                </div>
                                            </template>

                                            <!-- Contract Metadata -->
                                            <template v-else-if="document?.entity_type === 'contract'">
                                                <div v-if="document.entity_details?.type">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Contract Type</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.type }}
                                                    </dd>
                                                </div>
                                                <div class="flex gap-8">
                                                    <div v-if="document.entity_details?.effective_date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Effective Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.effective_date) }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.expiration_date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Expiry Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.expiration_date) }}
                                                        </dd>
                                                    </div>
                                                </div>
                                                <div v-if="document.entity_details?.parties?.length">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Parties</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        <span v-for="(party, i) in document.entity_details.parties" :key="i">
                                                            {{ party }}<span v-if="i < document.entity_details.parties.length - 1">, </span>
                                                        </span>
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.summary">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Key Terms</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.summary }}
                                                    </dd>
                                                </div>
                                            </template>

                                            <!-- Voucher Metadata -->
                                            <template v-else-if="document?.entity_type === 'voucher'">
                                                <div v-if="document.entity_details?.code">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Voucher Code</dt>
                                                    <dd class="mt-1 text-sm font-mono text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded inline-block">
                                                        {{ document.entity_details.code }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.voucher_type">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Type</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.voucher_type }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.original_value != null" class="flex gap-8">
                                                    <div>
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Original Value</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatCurrency(document.entity_details.original_value, document.entity_details.currency) }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.current_value != null">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Current Value</dt>
                                                        <dd class="mt-1 text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                            {{ formatCurrency(document.entity_details.current_value, document.entity_details.currency) }}
                                                        </dd>
                                                    </div>
                                                </div>
                                                <div v-if="document.entity_details?.expiry_date">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Expiry Date</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ formatDateLocalized(document.entity_details.expiry_date) }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.is_redeemed != null">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Status</dt>
                                                    <dd class="mt-1">
                                                        <span :class="[
                                                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                            document.entity_details.is_redeemed
                                                                ? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300'
                                                                : 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                                                        ]">
                                                            {{ document.entity_details.is_redeemed ? 'Redeemed' : 'Available' }}
                                                        </span>
                                                    </dd>
                                                </div>
                                            </template>

                                            <!-- Warranty Metadata -->
                                            <template v-else-if="document?.entity_type === 'warranty'">
                                                <div v-if="document.entity_details?.product">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Product</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.product }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.manufacturer">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Manufacturer</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.manufacturer }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.warranty_type || document.entity_details?.coverage_type" class="flex gap-8">
                                                    <div v-if="document.entity_details?.warranty_type">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Warranty Type</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ document.entity_details.warranty_type }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.coverage_type">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Coverage</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ document.entity_details.coverage_type }}
                                                        </dd>
                                                    </div>
                                                </div>
                                                <div class="flex gap-8">
                                                    <div v-if="document.entity_details?.start_date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Start Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.start_date) }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.expiry_date">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">End Date</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatDateLocalized(document.entity_details.expiry_date) }}
                                                        </dd>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- BankStatement Metadata -->
                                            <template v-else-if="document?.entity_type === 'bankstatement' || document?.entity_type === 'bank_statement'">
                                                <div v-if="document.entity_details?.bank_name">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Bank</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.bank_name }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.period_start || document.entity_details?.period_end">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Period</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.period_start ? formatDateLocalized(document.entity_details.period_start) : 'N/A' }}
                                                        <span class="text-zinc-400 mx-1">&rarr;</span>
                                                        {{ document.entity_details.period_end ? formatDateLocalized(document.entity_details.period_end) : 'N/A' }}
                                                    </dd>
                                                </div>
                                                <div class="flex gap-8">
                                                    <div v-if="document.entity_details?.opening_balance != null">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Opening Balance</dt>
                                                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                            {{ formatCurrency(document.entity_details.opening_balance, document.entity_details.currency) }}
                                                        </dd>
                                                    </div>
                                                    <div v-if="document.entity_details?.closing_balance != null">
                                                        <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Closing Balance</dt>
                                                        <dd class="mt-1 text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                                            {{ formatCurrency(document.entity_details.closing_balance, document.entity_details.currency) }}
                                                        </dd>
                                                    </div>
                                                </div>
                                                <div v-if="document.entity_details?.transaction_count != null">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Transactions</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.transaction_count }}
                                                    </dd>
                                                </div>
                                            </template>

                                            <!-- Document Metadata -->
                                            <template v-else-if="document?.entity_type === 'document'">
                                                <div v-if="document.entity_details?.document_type">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Document Type</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.document_type }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.document_date">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Document Date</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ formatDateLocalized(document.entity_details.document_date) }}
                                                    </dd>
                                                </div>
                                                <div v-if="document.entity_details?.summary">
                                                    <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Summary</dt>
                                                    <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                        {{ document.entity_details.summary }}
                                                    </dd>
                                                </div>
                                            </template>

                                            <!-- Common Metadata (all types) -->
                                            <div v-if="document?.category" class="border-t border-zinc-200 dark:border-zinc-700 pt-6">
                                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Category</dt>
                                                <dd class="mt-1">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                        :style="{ backgroundColor: document.category.color + '20', color: document.category.color }"
                                                    >
                                                        {{ document.category.name }}
                                                    </span>
                                                </dd>
                                            </div>
                                            <div v-if="document?.tags?.length" :class="{ 'border-t border-zinc-200 dark:border-zinc-700 pt-6': !document?.category }">
                                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Tags</dt>
                                                <dd class="mt-1 flex flex-wrap gap-1">
                                                    <span
                                                        v-for="tag in document.tags"
                                                        :key="tag.id"
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                        :style="tag.color ? { backgroundColor: tag.color + '20', color: tag.color } : {}"
                                                        :class="!tag.color ? 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200' : ''"
                                                    >
                                                        {{ tag.name }}
                                                    </span>
                                                </dd>
                                            </div>
                                            <div v-if="document?.note">
                                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Note</dt>
                                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                    {{ document.note }}
                                                </dd>
                                            </div>
                                            <div v-if="document?.description && document.entity_type !== 'document'">
                                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Description</dt>
                                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                    {{ document.description }}
                                                </dd>
                                            </div>

                                            <!-- File Info -->
                                            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6">
                                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">File</dt>
                                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                                    {{ document?.file_name }}
                                                    <span class="text-zinc-400 mx-1">&middot;</span>
                                                    {{ formatFileSize(document?.size || 0) }}
                                                    <span v-if="document?.uploaded_at" class="text-zinc-400 mx-1">&middot;</span>
                                                    <span v-if="document?.uploaded_at" class="text-zinc-500 dark:text-zinc-400">
                                                        {{ formatDateLocalized(document.uploaded_at) }}
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TransitionChild>
        </div>
    </TransitionRoot>

    <!-- Delete Confirmation Dialog -->
    <TransitionRoot as="template" :show="showDeleteConfirm">
        <div class="fixed inset-0 z-50">
            <div class="fixed inset-0 bg-zinc-500/75 transition-opacity" @click="showDeleteConfirm = false" />
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <TrashIcon class="h-6 w-6 text-red-600" aria-hidden="true" />
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-lg font-black leading-6 text-zinc-900 dark:text-zinc-100">
                                    Delete Document
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-zinc-500">
                                        Are you sure you want to delete this document? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button
                                type="button"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                                @click="deleteDocument"
                            >
                                Delete
                            </button>
                            <button
                                type="button"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-bold text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-orange-50 dark:hover:bg-zinc-700 sm:mt-0 sm:w-auto"
                                @click="showDeleteConfirm = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TransitionRoot>
</template>
