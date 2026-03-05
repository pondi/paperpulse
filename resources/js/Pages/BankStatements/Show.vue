<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Common/Breadcrumbs.vue';
import Modal from '@/Components/Common/Modal.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import DocumentImage from '@/Components/Domain/DocumentImage.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';
import {
    BanknotesIcon,
    ArrowDownTrayIcon,
    TrashIcon,
    ArrowLeftIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
} from '@heroicons/vue/24/outline';

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface FileInfo {
    id: number;
    url: string;
    pdfUrl: string | null;
    previewUrl?: string | null;
    extension: string;
    mime_type?: string;
    size?: number;
    guid?: string;
    has_preview?: boolean;
    is_pdf?: boolean;
    uploaded_at?: string | null;
    file_created_at?: string | null;
    file_modified_at?: string | null;
}

interface Transaction {
    id: number;
    transaction_date: string | null;
    posting_date: string | null;
    description: string;
    reference: string | null;
    transaction_type: string;
    category: string | null;
    category_group: string | null;
    category_group_label: string | null;
    subcategory: string | null;
    amount: number;
    balance_after: number | null;
    currency: string;
    counterparty_name: string | null;
    counterparty_account: string | null;
}

interface CategoryGroup {
    value: string;
    label: string;
}

interface Statement {
    id: number;
    bank_name: string;
    account_holder_name: string | null;
    account_number: string;
    account_number_full: string | null;
    iban: string | null;
    swift_code: string | null;
    statement_date: string | null;
    statement_period_start: string | null;
    statement_period_end: string | null;
    opening_balance: number | null;
    closing_balance: number | null;
    currency: string;
    total_credits: number | null;
    total_debits: number | null;
    transaction_count: number | null;
    transactions: Transaction[];
    tags: Tag[];
    file: FileInfo | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Crumb {
    label: string;
    href?: string;
}

interface Props {
    statement: Statement;
    available_tags: Tag[];
    category_groups: CategoryGroup[];
    breadcrumbs?: Crumb[];
}

const props = defineProps<Props>();
const { formatDate, formatCurrency } = useDateFormatter();

const showDeleteModal = ref(false);
const statementTags = ref(props.statement.tags);
const txSearch = ref('');
const txTypeFilter = ref('');
const txCategoryFilter = ref('');
const txSort = ref('transaction_date');
const txSortDir = ref<'asc' | 'desc'>('desc');
const showFilters = ref(false);

const statementTitle = computed(() => {
    if (props.statement.bank_name) return props.statement.bank_name;
    if (props.statement.statement_period_start && props.statement.statement_period_end) {
        return formatDate(props.statement.statement_period_start) + ' – ' + formatDate(props.statement.statement_period_end);
    }
    if (props.statement.statement_date) return formatDate(props.statement.statement_date);
    return 'Bank Statement';
});

const statementSubtitle = computed(() => {
    const parts: string[] = [];
    if (props.statement.account_holder_name) parts.push(props.statement.account_holder_name);
    if (props.statement.account_number) parts.push(props.statement.account_number);
    if (props.statement.bank_name && props.statement.statement_period_start && props.statement.statement_period_end) {
        parts.push(formatDate(props.statement.statement_period_start) + ' – ' + formatDate(props.statement.statement_period_end));
    }
    return parts.join(' · ');
});

const netFlow = computed(() => {
    return (props.statement.total_credits || 0) - (props.statement.total_debits || 0);
});

const filteredTransactions = computed(() => {
    let result = [...(props.statement.transactions || [])];

    if (txSearch.value) {
        const search = txSearch.value.toLowerCase();
        result = result.filter(tx =>
            (tx.description && tx.description.toLowerCase().includes(search)) ||
            (tx.counterparty_name && tx.counterparty_name.toLowerCase().includes(search)) ||
            (tx.reference && tx.reference.toLowerCase().includes(search))
        );
    }

    if (txTypeFilter.value) {
        result = result.filter(tx => tx.transaction_type === txTypeFilter.value);
    }

    if (txCategoryFilter.value) {
        result = result.filter(tx => tx.category_group === txCategoryFilter.value);
    }

    result.sort((a, b) => {
        let comparison = 0;
        switch (txSort.value) {
            case 'amount':
                comparison = (a.amount || 0) - (b.amount || 0);
                break;
            case 'balance_after':
                comparison = (a.balance_after || 0) - (b.balance_after || 0);
                break;
            case 'description':
                comparison = (a.description || '').localeCompare(b.description || '');
                break;
            case 'transaction_date':
            default:
                comparison = (a.transaction_date || '').localeCompare(b.transaction_date || '');
                break;
        }
        return txSortDir.value === 'desc' ? -comparison : comparison;
    });

    return result;
});

const sortBy = (field: string) => {
    if (txSort.value === field) {
        txSortDir.value = txSortDir.value === 'desc' ? 'asc' : 'desc';
    } else {
        txSort.value = field;
        txSortDir.value = 'desc';
    }
};

const formatShortDate = (date: string | null | undefined) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric',
    });
};

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const amountClass = (tx: Transaction) => {
    if (tx.transaction_type === 'credit' || (tx.amount && tx.amount > 0)) {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-red-600 dark:text-red-400';
};

const deleteStatement = () => {
    router.delete(route('bank-statements.destroy', props.statement.id));
};

const downloadStatement = () => {
    window.location.href = route('bank-statements.download', props.statement.id);
};

const handleTagAdded = (tag: Tag) => {
    router.post(route('bank-statements.tags.store', props.statement.id), {
        name: tag.name
    }, { preserveScroll: true });
};

const handleTagRemoved = (tag: Tag) => {
    router.delete(route('bank-statements.tags.destroy', [props.statement.id, tag.id]), {
        preserveScroll: true
    });
};
</script>

<template>
    <Head :title="`Bank Statement - ${statementTitle}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight flex items-center gap-x-2">
                        <BanknotesIcon class="size-6" />
                        {{ statementTitle }}
                    </h2>
                    <p v-if="statementSubtitle" class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ statementSubtitle }}
                    </p>
                </div>
                <div class="flex items-center gap-x-4">
                    <button
                        v-if="statement.file"
                        @click="downloadStatement"
                        class="inline-flex items-center gap-x-2 px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Download
                    </button>
                    <Link
                        :href="route('bank-statements.index')"
                        class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <Breadcrumbs v-if="breadcrumbs?.length" :crumbs="breadcrumbs" class="px-6 pt-4" />

        <div class="flex h-[calc(100vh-9rem)] overflow-hidden">
            <!-- Left Panel - Statement Details + Transactions -->
            <div class="w-1/2 p-6 overflow-y-auto border-r border-amber-200 dark:border-zinc-700">
                <div class="space-y-6">
                    <!-- Statement Details Card -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Statement Details</h3>
                        <dl class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Bank</dt>
                                    <dd class="text-sm text-zinc-900 dark:text-white">{{ statement.bank_name || 'N/A' }}</dd>
                                </div>
                                <div v-if="statement.account_holder_name">
                                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Account Holder</dt>
                                    <dd class="text-sm text-zinc-900 dark:text-white">{{ statement.account_holder_name }}</dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div v-if="statement.account_number">
                                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Account Number</dt>
                                    <dd class="text-sm text-zinc-900 dark:text-white font-mono">{{ statement.account_number }}</dd>
                                </div>
                                <div v-if="statement.iban">
                                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">IBAN</dt>
                                    <dd class="text-sm text-zinc-900 dark:text-white font-mono">{{ statement.iban }}</dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Period</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(statement.statement_period_start) }} &ndash; {{ formatDate(statement.statement_period_end) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Balance Summary -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Balance Summary</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>Opening Balance</span>
                                <span class="font-medium">{{ formatCurrency(statement.opening_balance, statement.currency) }}</span>
                            </div>
                            <div class="flex justify-between text-green-600 dark:text-green-400">
                                <span>Total Credits</span>
                                <span class="font-medium">+{{ formatCurrency(statement.total_credits, statement.currency) }}</span>
                            </div>
                            <div class="flex justify-between text-red-600 dark:text-red-400">
                                <span>Total Debits</span>
                                <span class="font-medium">-{{ formatCurrency(statement.total_debits, statement.currency) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-amber-200 dark:border-zinc-600">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">Net Flow</span>
                                <span class="font-bold" :class="netFlow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatCurrency(netFlow, statement.currency) }}
                                </span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-zinc-900 dark:text-zinc-100 pt-2 border-t border-amber-200 dark:border-zinc-600">
                                <span>Closing Balance</span>
                                <span>{{ formatCurrency(statement.closing_balance, statement.currency) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Table -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-amber-200 dark:border-zinc-700">
                        <div class="p-4 border-b border-amber-200 dark:border-zinc-700">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">
                                    Transactions
                                    <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">({{ filteredTransactions.length }})</span>
                                </h3>
                                <button
                                    @click="showFilters = !showFilters"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-amber-50 dark:hover:bg-zinc-700"
                                >
                                    <FunnelIcon class="h-3.5 w-3.5" />
                                    Filters
                                </button>
                            </div>

                            <!-- Search -->
                            <div class="relative">
                                <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                                <input
                                    v-model="txSearch"
                                    type="text"
                                    placeholder="Search transactions..."
                                    class="w-full pl-9 pr-3 py-2 text-sm border border-zinc-300 dark:border-zinc-600 rounded bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400"
                                />
                            </div>

                            <!-- Expanded Filters -->
                            <div v-if="showFilters" class="mt-3 grid grid-cols-2 gap-3">
                                <select
                                    v-model="txTypeFilter"
                                    class="text-sm border border-zinc-300 dark:border-zinc-600 rounded px-3 py-2 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">All Types</option>
                                    <option value="credit">Credits</option>
                                    <option value="debit">Debits</option>
                                    <option value="fee">Fees</option>
                                </select>
                                <select
                                    v-model="txCategoryFilter"
                                    class="text-sm border border-zinc-300 dark:border-zinc-600 rounded px-3 py-2 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">All Categories</option>
                                    <option v-for="cat in category_groups" :key="cat.value" :value="cat.value">
                                        {{ cat.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                                <thead class="bg-amber-50 dark:bg-zinc-700">
                                    <tr>
                                        <th @click="sortBy('transaction_date')" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider cursor-pointer hover:text-zinc-800 dark:hover:text-zinc-100">
                                            <span class="inline-flex items-center gap-1">
                                                Date
                                                <ArrowUpIcon v-if="txSort === 'transaction_date' && txSortDir === 'asc'" class="h-3 w-3" />
                                                <ArrowDownIcon v-if="txSort === 'transaction_date' && txSortDir === 'desc'" class="h-3 w-3" />
                                            </span>
                                        </th>
                                        <th @click="sortBy('description')" class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider cursor-pointer hover:text-zinc-800 dark:hover:text-zinc-100">
                                            Description
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                                            Category
                                        </th>
                                        <th @click="sortBy('amount')" class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider cursor-pointer hover:text-zinc-800 dark:hover:text-zinc-100">
                                            <span class="inline-flex items-center gap-1">
                                                Amount
                                                <ArrowUpIcon v-if="txSort === 'amount' && txSortDir === 'asc'" class="h-3 w-3" />
                                                <ArrowDownIcon v-if="txSort === 'amount' && txSortDir === 'desc'" class="h-3 w-3" />
                                            </span>
                                        </th>
                                        <th @click="sortBy('balance_after')" class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider cursor-pointer hover:text-zinc-800 dark:hover:text-zinc-100">
                                            Balance
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-amber-100 dark:divide-zinc-700/50">
                                    <tr v-for="tx in filteredTransactions" :key="tx.id" class="hover:bg-amber-50 dark:hover:bg-zinc-700/30">
                                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                            {{ formatShortDate(tx.transaction_date) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100 max-w-xs truncate">
                                            <div>{{ tx.description }}</div>
                                            <div v-if="tx.counterparty_name" class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                                {{ tx.counterparty_name }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs whitespace-nowrap">
                                            <span v-if="tx.category_group_label" class="inline-flex items-center px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                {{ tx.category_group_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right font-medium whitespace-nowrap" :class="amountClass(tx)">
                                            {{ formatCurrency(tx.amount, statement.currency) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                            {{ tx.balance_after !== null ? formatCurrency(tx.balance_after, statement.currency) : '' }}
                                        </td>
                                    </tr>
                                    <tr v-if="filteredTransactions.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                            No transactions match your filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Tags</h3>
                        <TagManager
                            v-model="statementTags"
                            :readonly="false"
                            @tag-added="handleTagAdded"
                            @tag-removed="handleTagRemoved"
                        />
                    </div>

                    <!-- File Metadata -->
                    <div v-if="statement.file" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">File Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">File Size</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ formatFileSize(statement.file?.size || 0) }}</dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Uploaded</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ formatDate(statement.file?.uploaded_at || statement.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Delete Button -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <button
                            @click="showDeleteModal = true"
                            class="w-full inline-flex justify-center items-center gap-x-2 px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete Statement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Preview / Quick Stats -->
            <div class="w-1/2 bg-amber-50 dark:bg-zinc-900 overflow-auto">
                <div v-if="statement.file && (statement.file.is_pdf || statement.file.has_preview)">
                    <DocumentImage
                        :file="statement.file"
                        :alt-text="`Bank Statement - ${statement.bank_name}`"
                        error-message="Failed to load statement preview"
                        no-image-message="No preview available"
                        :show-pdf-button="true"
                        pdf-button-position="fixed bottom-6 right-6"
                    />
                </div>
                <div v-else class="p-6 space-y-6">
                    <!-- Quick Stats for CSV imports (no preview) -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Quick Stats</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(statement.total_credits, statement.currency) }}</div>
                                <div class="text-xs text-green-700 dark:text-green-300 mt-1">Total Credits</div>
                            </div>
                            <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(statement.total_debits, statement.currency) }}</div>
                                <div class="text-xs text-red-700 dark:text-red-300 mt-1">Total Debits</div>
                            </div>
                            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400" :class="netFlow >= 0 ? '' : 'text-red-600 dark:text-red-400'">{{ formatCurrency(netFlow, statement.currency) }}</div>
                                <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">Net Cash Flow</div>
                            </div>
                            <div class="text-center p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg">
                                <div class="text-2xl font-bold text-zinc-800 dark:text-zinc-200">{{ statement.transaction_count || 0 }}</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">Transactions</div>
                            </div>
                        </div>
                    </div>

                    <!-- Average Transaction Size -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Averages</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Avg Credit</dt>
                                <dd class="text-sm font-medium text-green-600 dark:text-green-400">
                                    {{ statement.transactions.filter(t => t.transaction_type === 'credit').length > 0
                                        ? formatCurrency((statement.total_credits || 0) / statement.transactions.filter(t => t.transaction_type === 'credit').length, statement.currency)
                                        : 'N/A' }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Avg Debit</dt>
                                <dd class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ statement.transactions.filter(t => t.transaction_type === 'debit').length > 0
                                        ? formatCurrency((statement.total_debits || 0) / statement.transactions.filter(t => t.transaction_type === 'debit').length, statement.currency)
                                        : 'N/A' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-white">Delete Bank Statement</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete this bank statement and all its transactions? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md font-semibold text-xs text-zinc-700 dark:text-zinc-300 uppercase tracking-widest hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteStatement"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
