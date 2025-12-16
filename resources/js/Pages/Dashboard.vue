<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/Composables/useTranslations';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    receipts: {
        type: Array,
        default: () => []
    },
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
const { formatDate, formatCurrency } = useDateFormatter();
</script>

<template>
    <Head :title="__('dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-100 leading-tight">{{ __('dashboard') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
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
                            {{ formatCurrency(receiptCount ? totalAmount / receiptCount : 0) }}
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
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('date') }}</th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('merchant') }}</th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('amount') }}</th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 uppercase tracking-wider">{{ __('category') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-amber-200 dark:divide-zinc-700">
                                    <tr v-for="receipt in recentReceipts" :key="receipt.id" class="hover:bg-amber-50 dark:hover:bg-zinc-800 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ formatDate(receipt.receipt_date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ receipt.merchant?.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ formatCurrency(receipt.total_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-700 dark:text-zinc-300">
                                            {{ receipt.receipt_category }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
