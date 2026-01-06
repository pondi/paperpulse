<template>
    <AppLayout :title="__('duplicates')">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-black text-zinc-900 dark:text-zinc-100">{{ __('possible_duplicates') }}</h1>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('possible_duplicates_description') }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                    {{ duplicates.length }} {{ __('duplicates') }}
                </span>
            </div>

            <div v-if="errorMessage" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ errorMessage }}
            </div>

            <div v-if="duplicates.length === 0" class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900">
                {{ __('no_duplicates_found') }}
            </div>

            <div v-else class="space-y-6">
                <div
                    v-for="flag in duplicates"
                    :key="flag.id"
                    class="bg-white dark:bg-zinc-900 shadow-lg rounded-lg border-l-4 border-amber-500 dark:border-amber-400"
                >
                    <div class="p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('duplicate_reason') }}</p>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ formatReason(flag.reason) }}</p>
                                <p class="text-xs text-zinc-500">{{ __('duplicate_detected_at') }} - {{ formatDate(flag.created_at) }}</p>
                            </div>
                            <button
                                type="button"
                                class="text-xs font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100"
                                :disabled="isBusy(flag.id)"
                                @click="ignoreDuplicate(flag)"
                            >
                                {{ __('ignore_duplicate') }}
                            </button>
                        </div>

                        <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 p-4">
                                <FileCard
                                    :file="flag.file"
                                    :flag-id="flag.id"
                                    :busy="isBusy(flag.id)"
                                    @delete-file="resolveDuplicate(flag, $event)"
                                />
                            </div>
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 p-4">
                                <FileCard
                                    :file="flag.duplicate_file"
                                    :flag-id="flag.id"
                                    :busy="isBusy(flag.id)"
                                    @delete-file="resolveDuplicate(flag, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    duplicates: {
        type: Array,
        default: () => []
    }
});

const { __ } = useTranslations();
const { formatDate } = useDateFormatter();

const duplicates = ref([...props.duplicates]);
const errorMessage = ref('');
const busyFlags = ref({});

const isBusy = (flagId) => Boolean(busyFlags.value[flagId]);

const setBusy = (flagId, busy) => {
    if (busy) {
        busyFlags.value[flagId] = true;
    } else {
        delete busyFlags.value[flagId];
    }
};

const formatReason = (reason) => {
    if (!reason) {
        return __('possible_duplicate');
    }

    return reason
        .split('|')
        .map((part) => part.replace(/_/g, ' '))
        .join(', ');
};

const ignoreDuplicate = async (flag) => {
    if (!confirm(__('ignore_duplicate_confirm'))) {
        return;
    }

    setBusy(flag.id, true);
    errorMessage.value = '';

    try {
        await axios.post(`/api/v1/duplicates/${flag.id}/ignore`);
        duplicates.value = duplicates.value.filter((item) => item.id !== flag.id);
    } catch (error) {
        errorMessage.value = __('duplicate_action_failed');
    } finally {
        setBusy(flag.id, false);
    }
};

const resolveDuplicate = async (flag, fileId) => {
    if (!fileId) {
        return;
    }

    if (!confirm(__('delete_duplicate_file_confirm'))) {
        return;
    }

    setBusy(flag.id, true);
    errorMessage.value = '';

    try {
        await axios.post(`/api/v1/duplicates/${flag.id}/resolve`, {
            delete_file_id: fileId
        });

        duplicates.value = duplicates.value.filter((item) => item.id !== flag.id);
    } catch (error) {
        errorMessage.value = __('duplicate_action_failed');
    } finally {
        setBusy(flag.id, false);
    }
};

const FileCard = {
    components: { Link },
    props: {
        file: {
            type: Object,
            default: null
        },
        flagId: {
            type: Number,
            required: true
        },
        busy: {
            type: Boolean,
            default: false
        }
    },
    emits: ['delete-file'],
    setup(props, { emit }) {
        const { __ } = useTranslations();
        const { formatDate, formatCurrency } = useDateFormatter();

        const summaryLabel = computed(() => {
            if (!props.file?.summary) {
                return null;
            }

            const summary = props.file.summary;
            if (summary.type === 'receipt') {
                return {
                    title: summary.merchant_name || __('receipt_details'),
                    date: summary.date,
                    amount: summary.total_amount,
                    currency: summary.currency,
                };
            }

            if (summary.type === 'invoice') {
                return {
                    title: summary.invoice_number || summary.from_name || __('invoice'),
                    date: summary.date,
                    amount: summary.total_amount,
                    currency: summary.currency,
                };
            }

            return null;
        });

        const handleDelete = () => {
            if (!props.file?.id) {
                return;
            }

            emit('delete-file', props.file.id);
        };

        return {
            __,
            formatDate,
            formatCurrency,
            summaryLabel,
            handleDelete,
        };
    },
    template: `
        <div>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" v-if="file">
                        {{ file.name }}
                    </p>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" v-else>
                        {{ __('file_missing') }}
                    </p>
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
    `
};
</script>
