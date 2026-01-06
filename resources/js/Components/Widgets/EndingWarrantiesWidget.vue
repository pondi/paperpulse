<template>
    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-l-4 border-emerald-500 dark:border-emerald-400">
        <div class="p-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <ShieldExclamationIcon class="h-5 w-5" />
                        </span>
                        <h3 class="text-lg font-black text-zinc-900 dark:text-zinc-100">{{ __('ending_warranties') }}</h3>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('expiring_within_days', { count: days }) }}
                        <span v-if="totalCount !== null" class="ml-2 text-xs text-zinc-500">· {{ totalCount }}</span>
                    </p>
                </div>
            </div>

            <div v-if="loading" class="py-8 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
            </div>

            <div v-else-if="error" class="py-8 text-center text-sm text-red-600 dark:text-red-400">
                {{ error }}
            </div>

            <div v-else-if="items.length === 0" class="py-8 text-center text-sm text-zinc-500">
                {{ __('no_ending_warranties') }}
            </div>

            <ul v-else class="mt-4 divide-y divide-emerald-100 dark:divide-zinc-800">
                <li v-for="warranty in items" :key="warranty.id" class="py-3">
                    <div class="flex items-center justify-between gap-4 rounded-md px-2 py-2">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ warrantyTitle(warranty) }}
                            </p>
                            <p v-if="warranty.manufacturer" class="text-xs text-zinc-500">
                                {{ warranty.manufacturer }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-emerald-700 dark:text-emerald-300">
                                {{ __('expires_in') }} {{ warranty.days_remaining ?? 0 }} {{ __('days') }}
                            </p>
                            <p class="text-xs text-zinc-500">
                                {{ __('expires_on') }} {{ formatDate(warranty.warranty_end_date) }}
                            </p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ShieldExclamationIcon } from '@heroicons/vue/24/outline';
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
const { formatDate } = useDateFormatter();

const warrantyTitle = (warranty) => {
    if (warranty.product_name) {
        return warranty.product_name;
    }

    return `${__('warranty')} #${warranty.id}`;
};
</script>
