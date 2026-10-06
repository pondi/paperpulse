<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import ExpiringVouchersWidget from '@/Components/Widgets/ExpiringVouchersWidget.vue';
import EndingWarrantiesWidget from '@/Components/Widgets/EndingWarrantiesWidget.vue';
import { useTranslations } from '@/Composables/useTranslations';
import { fileStatusLabel } from '@/utils/fileStatus';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    archiveStats: { type: Object, default: () => ({ total: 0, processing: 0, failed: 0, needs_review: 0 }) },
    recentUploads: { type: Array, default: () => [] },
    expiringVouchers: { type: Object, required: true },
    endingWarranties: { type: Object, required: true },
    totalAmount: {
        type: Number,
        default: 0
    },
    receiptCount: {
        type: Number,
        default: 0
    },
    merchantCount: {
        type: Number,
        default: 0
    },
    recentReceipts: {
        type: Array,
        default: () => []
    }
});

const { __ } = useTranslations();
const { formatDate, formatDateTime, formatCurrency } = useDateFormatter();
const archiveCards = [
    { label: 'Archive files', key: 'total', view: 'all' },
    { label: 'Processing', key: 'processing', view: 'processing' },
    { label: 'Failed', key: 'failed', view: 'attention', status: 'failed' },
    { label: 'Needs review', key: 'needs_review', view: 'attention', status: 'needs_review' },
];
</script>

<template>
    <Head :title="__('dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-100 leading-tight">{{ __('dashboard') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <section aria-label="Archive overview" class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Link v-for="card in archiveCards" :key="card.key" :href="route('library.index', { view: card.view, status: card.status })" class="rounded-lg border-l-4 border-amber-600 bg-white p-4 shadow dark:border-amber-500 dark:bg-zinc-900">
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-zinc-900 dark:text-zinc-100">{{ archiveStats[card.key] }}</p>
                    </Link>
                </section>
                <section class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-zinc-900 sm:p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Recent uploads</h3>
                        <Link :href="route('library.index', { view: 'recent' })" class="text-sm font-semibold text-amber-700 dark:text-amber-400">Open Library</Link>
                    </div>
                    <ul v-if="recentUploads.length" class="flex flex-col gap-3">
                        <li v-for="file in recentUploads" :key="file.id">
                            <Link :href="route('files.show', file.id)" class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-zinc-50 p-3 hover:bg-amber-50 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                <div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ file.name }}</p><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><span class="capitalize">{{ file.file_type }}</span> · Uploaded {{ formatDateTime(file.uploaded_at) }}</p></div>
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ fileStatusLabel(file) }}</span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-zinc-600 dark:text-zinc-400">Your archive is empty. <Link :href="route('documents.upload')" class="font-medium text-amber-700 underline dark:text-amber-400">Upload your first files</Link>.</p>
                </section>
                <details v-if="receiptCount" class="mb-6">
                    <summary class="mb-4 cursor-pointer text-lg font-semibold text-zinc-900 dark:text-zinc-100">Receipt overview — statistics and recent receipts</summary>
                    <!-- Receipt statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <!-- Total Receipts -->
                        <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 sm:rounded-lg p-6 border-l-4 border-amber-600 dark:border-amber-500">
                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider mb-2">{{ __('total_receipts') }}</div>
                            <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100">{{ receiptCount }}</div>
                        </div>

                        <!-- Total Amount -->
                        <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 sm:rounded-lg p-6 border-l-4 border-orange-600 dark:border-orange-500">
                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider mb-2">{{ __('total_amount') }}</div>
                            <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100">{{ formatCurrency(totalAmount) }}</div>
                        </div>

                        <!-- Unique Merchants -->
                        <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 sm:rounded-lg p-6 border-l-4 border-red-600 dark:border-red-500">
                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider mb-2">{{ __('unique_merchants') }}</div>
                            <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100">{{ merchantCount }}</div>
                        </div>

                        <!-- Average Receipt Amount -->
                        <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 sm:rounded-lg p-6 border-l-4 border-amber-500 dark:border-amber-400">
                            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider mb-2">{{ __('average_receipt_amount') }}</div>
                            <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100">
                                {{ formatCurrency(totalAmount === null ? null : (receiptCount ? totalAmount / receiptCount : 0)) }}
                            </div>
                        </div>
                    </div>

                    <!-- Recent Receipts -->
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg mb-6 border-t-4 border-amber-600 dark:border-amber-500">
                        <div class="p-6">
                            <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-4">{{ __('recent_receipts') }}</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                                    <thead>
                                        <tr>
                                            <th class="hidden sm:table-cell px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('date') }}</th>
                                            <th class="px-3 py-3 sm:px-6 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('merchant') }}</th>
                                            <th class="px-3 py-3 sm:px-6 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('amount') }}</th>
                                            <th class="hidden sm:table-cell px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('category') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-amber-200 dark:divide-zinc-700">
                                        <tr v-for="receipt in recentReceipts" :key="receipt.id" class="hover:bg-amber-50 dark:hover:bg-zinc-800 transition-colors duration-200">
                                            <td class="hidden sm:table-cell px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ formatDate(receipt.receipt_date) }}
                                            </td>
                                            <td class="max-w-[10rem] break-words px-3 py-4 text-sm font-medium sm:max-w-none sm:px-6 text-zinc-900 dark:text-zinc-100">
                                                <Link :href="receipt.file_id ? route('files.show', receipt.file_id) : route('receipts.show', receipt.id)" class="hover:text-amber-700 dark:hover:text-amber-400">{{ receipt.merchant?.name || 'Open receipt' }}</Link>
                                                <span class="mt-1 block text-xs font-normal text-zinc-500 dark:text-zinc-400 sm:hidden">{{ formatDate(receipt.receipt_date) }}<span v-if="receipt.receipt_category"> · {{ receipt.receipt_category }}</span></span>
                                            </td>
                                            <td class="px-3 py-4 whitespace-nowrap text-sm font-bold sm:px-6 text-zinc-900 dark:text-zinc-100">
                                                {{ formatCurrency(receipt.total_amount, receipt.currency) }}
                                            </td>
                                            <td class="hidden sm:table-cell px-6 py-4 whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ receipt.receipt_category }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </details>
                <div v-if="expiringVouchers.total || endingWarranties.total" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <ExpiringVouchersWidget v-if="expiringVouchers.total" :items="expiringVouchers.items" :total-count="expiringVouchers.total" />
                    <EndingWarrantiesWidget v-if="endingWarranties.total" :items="endingWarranties.items" :total-count="endingWarranties.total" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
