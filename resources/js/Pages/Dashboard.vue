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
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('dashboard') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total Receipts -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('total_receipts') }}</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ receiptCount }}</div>
                    </div>

                    <!-- Total Amount -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('total_amount') }}</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(totalAmount) }}</div>
                    </div>

                    <!-- Unique Merchants -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('unique_merchants') }}</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ merchantCount }}</div>
                    </div>

                    <!-- Average Receipt Amount -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ __('average_receipt_amount') }}</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ formatCurrency(receiptCount ? totalAmount / receiptCount : 0) }}
                        </div>
                    </div>
                </div>

                <!-- Recent Receipts -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('recent_receipts') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('date') }}</th>
                                        <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('merchant') }}</th>
                                        <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('amount') }}</th>
                                        <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('category') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="receipt in recentReceipts" :key="receipt.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ formatDate(receipt.receipt_date) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ receipt.merchant?.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ formatCurrency(receipt.total_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
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
