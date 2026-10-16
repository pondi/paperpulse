<template>
    <section aria-labelledby="job-filters-heading" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="job-filters-heading" class="font-semibold text-zinc-950 dark:text-zinc-100">Find a job</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Narrow the queue by status, worker queue or job name.</p>
            </div>
            <button
                v-if="hasFilters"
                type="button"
                class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-amber-800 hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-amber-300 dark:hover:bg-zinc-700"
                @click="$emit('update:form', { status: '', queue: '', search: '', per_page: form.per_page, page: 1 })"
            >
                Clear filters
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Status
                <select
                    :value="form.status"
                    aria-label="Job status"
                    class="min-h-11 rounded-lg border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white"
                    @change="$emit('update:form', { ...form, status: ($event.target as HTMLSelectElement).value, page: 1 })"
                >
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Queue
                <select
                    :value="form.queue"
                    aria-label="Worker queue"
                    class="min-h-11 rounded-lg border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white"
                    @change="$emit('update:form', { ...form, queue: ($event.target as HTMLSelectElement).value, page: 1 })"
                >
                    <option value="">All queues</option>
                    <option v-for="queue in queues" :key="queue" :value="queue">{{ queue }}</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Results per page
                <select
                    :value="form.per_page"
                    aria-label="Results per page"
                    class="min-h-11 rounded-lg border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white"
                    @change="$emit('update:form', { ...form, per_page: parseInt(($event.target as HTMLSelectElement).value), page: 1 })"
                >
                    <option :value="50">50 per page</option>
                    <option :value="100">100 per page</option>
                    <option :value="200">200 per page</option>
                    <option :value="999999">All results</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:col-span-2 xl:col-span-1">
                Search job names
                <input
                    :value="form.search"
                    type="search"
                    autocomplete="off"
                    placeholder="For example, Process Receipt"
                    class="min-h-11 rounded-lg border-zinc-300 bg-white text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white"
                    @input="$emit('update:form', { ...form, search: ($event.target as HTMLInputElement).value, page: 1 })"
                >
            </label>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Form {
    status: string;
    queue: string;
    search: string;
    page: number;
    per_page: number;
}

interface Props {
    form: Form;
    queues: string[];
}

const props = defineProps<Props>();
defineEmits<{
    (event: 'update:form', form: Form): void;
}>();

const hasFilters = computed(() => Boolean(props.form.status || props.form.queue || props.form.search));
</script>
