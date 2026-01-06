<template>
    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-l-4 border-amber-500 dark:border-amber-400">
        <div class="p-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            <TicketIcon class="h-5 w-5" />
                        </span>
                        <h3 class="text-lg font-black text-zinc-900 dark:text-zinc-100">{{ __('expiring_vouchers') }}</h3>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('expiring_within_days', { count: days }) }}
                        <span v-if="totalCount !== null" class="ml-2 text-xs text-zinc-500">· {{ totalCount }}</span>
                    </p>
                </div>
                <Link
                    v-if="showViewAll"
                    :href="route('vouchers.index')"
                    class="text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                >
                    {{ __('view_all') }}
                </Link>
            </div>

            <div v-if="loading" class="py-8 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-amber-600"></div>
            </div>

            <div v-else-if="error" class="py-8 text-center text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </div>

            <div v-else-if="items.length === 0" class="py-8 text-center text-sm text-zinc-500">
                {{ __('no_expiring_vouchers') }}
            </div>

            <ul v-else class="mt-4 divide-y divide-amber-100 dark:divide-zinc-800">
                <li v-for="voucher in items" :key="voucher.id" class="py-3">
                    <Link
                        :href="route('vouchers.show', voucher.id)"
                        class="flex items-center justify-between gap-4 rounded-md px-2 py-2 hover:bg-amber-50 dark:hover:bg-zinc-800"
                    >
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ voucherTitle(voucher) }}
                            </p>
                            <p v-if="voucher.code" class="text-xs text-zinc-500">
                                {{ voucher.code }}
                            </p>
                            <p v-else-if="voucher.merchant?.name" class="text-xs text-zinc-500">
                                {{ voucher.merchant.name }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p v-if="voucher.current_value" class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ formatCurrency(voucher.current_value, voucher.currency) }}
                            </p>
                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                {{ __('expires_in') }} {{ voucher.days_remaining ?? 0 }} {{ __('days') }}
                            </p>
                            <p class="text-xs text-zinc-500">
                                {{ __('expires_on') }} {{ formatDate(voucher.expiry_date) }}
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { TicketIcon } from '@heroicons/vue/24/outline';
import { useTranslations } from '@/Composables/useTranslations';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    items: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    },
    totalCount: {
        type: Number,
        default: null
    },
    days: {
        type: Number,
        default: 30
    }
});

const { __ } = useTranslations();
const { formatDate, formatCurrency } = useDateFormatter();

const showViewAll = computed(() => !props.loading && !props.error);

const voucherTitle = (voucher) => {
    if (voucher.merchant?.name) {
        return voucher.merchant.name;
    }

    if (voucher.code) {
        return `${__('voucher')} ${voucher.code}`;
    }

    return `${__('voucher')} #${voucher.id}`;
};
</script>
