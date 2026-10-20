<template>
    <section aria-label="Job totals" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <button
            v-for="item in summaries"
            :key="item.status"
            type="button"
            :aria-pressed="selectedStatus === item.status"
            @click="$emit('filter-status', item.status)"
            class="flex min-h-24 flex-col justify-between rounded-xl border bg-white p-4 text-left shadow-xs transition hover:border-amber-400 hover:shadow-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-amber-600 dark:bg-zinc-800"
            :class="selectedStatus === item.status ? 'border-amber-600 ring-1 ring-amber-600 dark:border-amber-500' : 'border-zinc-200 dark:border-zinc-700'"
        >
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ item.label }}</span>
            <span class="text-2xl font-semibold tracking-tight" :class="item.valueClass">{{ stats[item.key] || 0 }}</span>
        </button>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    stats: {
        pending: number;
        processing: number;
        completed: number;
        failed: number;
        total?: number;
    };
    selectedStatus: string;
}

const props = defineProps<Props>();
defineEmits<{
    (event: 'filter-status', status: string): void;
}>();

const summaries = computed(() => [
    { label: 'All jobs', key: 'total', status: '', valueClass: 'text-zinc-950 dark:text-white' },
    { label: 'Pending', key: 'pending', status: 'pending', valueClass: 'text-yellow-700 dark:text-yellow-300' },
    { label: 'Processing', key: 'processing', status: 'processing', valueClass: 'text-amber-700 dark:text-amber-300' },
    { label: 'Completed', key: 'completed', status: 'completed', valueClass: 'text-green-700 dark:text-green-300' },
    { label: 'Failed', key: 'failed', status: 'failed', valueClass: 'text-red-700 dark:text-red-300' },
].map(item => ({ ...item, key: item.key as keyof typeof props.stats })));
</script>
