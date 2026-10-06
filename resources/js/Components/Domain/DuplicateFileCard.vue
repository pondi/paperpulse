<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    file: { type: Object, default: null },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['delete-file']);
const { __ } = useTranslations();
const { formatDate, formatCurrency } = useDateFormatter();

const summaryLabel = computed(() => {
    const summary = props.file?.summary;
    if (!summary) return null;

    return {
        title: summary.merchant_name || summary.vendor_name || summary.title || __(summary.type),
        date: summary.date,
        amount: summary.total_amount ?? null,
        currency: summary.currency,
    };
});

const handleDelete = () => emit('delete-file', props.file.id);
</script>

<template>
        <div>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" v-if="file">
                        {{ file.name }}
                    </p>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" v-else>
                        {{ __('file_missing') }}
                    </p>
                    <p v-if="file?.original_name && file.original_name !== file.name" class="break-words text-xs text-zinc-500">{{ file.original_name }}</p>
                    <p v-if="file" class="text-xs text-zinc-500">{{ formatDate(file.uploaded_at) }}</p>
                </div>
                <Link
                    v-if="file"
                    :href="file.detailsUrl"
                    class="text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                >
                    {{ __('view_details') }}
                </Link>
            </div>

            <div v-if="summaryLabel" class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ summaryLabel.title }}</p>
                <p v-if="summaryLabel.date">{{ __('date') }}: {{ formatDate(summaryLabel.date) }}</p>
                <p v-if="summaryLabel.amount !== null">{{ __('total_amount') }}: {{ formatCurrency(summaryLabel.amount, summaryLabel.currency) }}</p>
            </div>

            <button
                v-if="file"
                type="button"
                class="mt-4 inline-flex w-full items-center justify-center rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/30 dark:text-red-200"
                :disabled="busy"
                @click="handleDelete"
            >
                {{ __('delete_file') }}
            </button>
        </div>
</template>
