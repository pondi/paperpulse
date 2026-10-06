<script setup lang="ts">
import { onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import Pagination from '@/Pages/Jobs/Components/Pagination.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const { formatDate } = useDateFormatter();

type FileItem = {
    id: number;
    guid: string;
    name: string;
    file_type: string;
    review?: { reason: string; confidence?: number; reasoning?: string };
    status: 'pending' | 'processing' | 'failed' | 'completed' | string;
    uploaded_at: string | null;
    extension: string;
    mime_type: string;
    has_preview: boolean;
    previewUrl: string | null;
    viewUrl: string;
};

interface Stats {
    total: number;
    failed: number;
    processing: number;
    pending: number;
    completed: number;
    needs_review: number;
}

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    reviewTypes?: string[];
    files: {
        data: FileItem[];
        links: any;
        meta: any;
    };
    stats?: Stats;
    filters?: {
        file_id?: number | null;
        status: string;
        query?: string;
        sort?: string;
        per_page: number;
    };
    pagination?: PaginationInfo;
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => ({
        total: 0,
        failed: 0,
        processing: 0,
        pending: 0,
        completed: 0,
        needs_review: 0,
    }),
    filters: () => ({
        status: '',
        per_page: 50,
    }),
    pagination: () => ({
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
    }),
});

const form = reactive({
    query: props.filters?.query ?? '',
    sort: props.filters?.sort ?? 'newest',
    status: props.filters?.status ?? '',
    per_page: props.filters?.per_page ?? 50,
    page: props.pagination?.current_page ?? 1,
});

const selectedTypeById = ref<Record<number, string>>(
    Object.fromEntries(
        props.files.data.map(f => [
            f.id,
            f.file_type === 'receipt' ? 'document' : 'receipt',
        ])
    ) as Record<number, string>
);

const expandedFileId = ref<number | null>(props.filters.file_id ?? null);

const selectedFileId = ref<number | null>(props.filters.file_id ?? null);
let filterTimer: ReturnType<typeof setTimeout>;
watch(
    () => [form.query, form.sort, form.status, form.per_page, form.page],
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            router.get(route('files.index'), {
                file_id: props.filters.file_id || undefined,
                query: form.query || undefined,
                sort: form.sort,
                status: form.status || undefined,
                per_page: form.per_page,
                page: form.page,
            }, { preserveState: true, preserveScroll: true });
        }, 300);
    }
);
onBeforeUnmount(() => clearTimeout(filterTimer));

const restart = (fileId: number) => {
    router.post(route('files.reprocess', fileId), {}, { preserveScroll: true });
};

const changeTypeAndRestart = (fileId: number) => {
    router.patch(
        route('files.change-type', fileId),
        { file_type: selectedTypeById.value[fileId] },
        { preserveScroll: true }
    );
    expandedFileId.value = null;
};

const toggleExpanded = (fileId: number) => {
    expandedFileId.value = expandedFileId.value === fileId ? null : fileId;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Files" />

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">File Management</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Manage and reprocess your uploaded files
                    </p>
                </div>

                <!-- Stats -->
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 rounded-md bg-amber-100 p-3 dark:bg-orange-900/40">
                                <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Total Files</p>
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.total }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 rounded-md bg-green-100 p-3 dark:bg-green-900/40">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Completed</p>
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.completed }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 rounded-md bg-yellow-100 p-3 dark:bg-yellow-900/40">
                                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">In Progress</p>
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.processing + props.stats.pending }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 rounded-md bg-red-100 p-3 dark:bg-red-900/40">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Failed</p>
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.failed }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Needs review</p>
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.needs_review }}</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                    <div class="flex flex-wrap gap-3">
                        <input dusk="activity-query" v-model="form.query" @input="form.page = 1" type="search" maxlength="200" aria-label="Find activity by filename" placeholder="Find a filename…" class="min-w-0 flex-1 rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" />
                        <select v-model="form.sort" @change="form.page = 1" aria-label="Sort activity" class="rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-white">
                            <option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">Filename A–Z</option><option value="status">Group by status</option>
                        </select>
                        <select
                            aria-label="Processing status"
                            v-model="form.status"
                            @change="form.page = 1"
                            class="bg-white text-zinc-900 dark:bg-zinc-700 dark:text-white rounded-md border border-zinc-300 dark:border-zinc-600 shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="needs_review">Needs review</option>
                        </select>

                        <select
                            v-model.number="form.per_page"
                            @change="form.page = 1"
                            class="bg-white text-zinc-900 dark:bg-zinc-700 dark:text-white rounded-md border border-zinc-300 dark:border-zinc-600 shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50"
                        >
                            <option :value="50">50 per page</option>
                            <option :value="100">100 per page</option>
                            <option :value="200">200 per page</option>
                            <option :value="999999">All</option>
                        </select>

                        <div class="flex-1 text-right self-center">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                Showing {{ props.files.data.length }} of {{ props.pagination.total }} files
                            </span>
                        </div>
                    </div>
                </div>

                <Link v-if="filters.file_id" :href="route('files.index')" class="mb-4 inline-block text-sm text-blue-600 dark:text-blue-400 hover:underline">All processing activity</Link>
                <!-- Files List -->
                <div v-if="props.files.data.length === 0" class="rounded-lg bg-white p-12 text-center shadow dark:bg-zinc-800">
                    <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-zinc-900 dark:text-zinc-100">{{ filters.file_id ? 'Processing record unavailable' : 'No files yet' }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ filters.file_id ? 'This file is unavailable or no longer has a processing record.' : 'Upload files to get started with document processing' }}
                    </p>
                </div>

                <div v-else class="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-800">
                    <div
                        v-for="(file, index) in props.files.data"
                        :key="file.id"
                        :dusk="'activity-file-' + file.id"
                        class="bg-white dark:bg-zinc-800"
                    >
                        <h2 v-if="form.sort === 'status' && (index === 0 || props.files.data[index - 1].status !== file.status)" class="bg-zinc-50 px-4 py-2 text-xs font-semibold uppercase text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">{{ file.status.replace('_', ' ') }}</h2>
                        <div class="p-3 sm:px-4">
                            <div class="flex items-start justify-between gap-2">
                                <!-- File Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <!-- File Icon -->
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-zinc-700">
                                                <!-- Receipt icon -->
                                                <svg v-if="file.file_type === 'receipt'" class="h-6 w-6 text-zinc-600 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                                </svg>
                                                <!-- Document/folder icon -->
                                                <svg v-else class="h-6 w-6 text-zinc-600 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- File Details -->
                                        <div class="flex-1 min-w-0">
                                            <a
                                                :href="file.previewUrl ?? file.viewUrl"
                                                target="_blank"
                                                class="text-sm font-semibold text-zinc-900 hover:text-amber-600 dark:text-zinc-100 dark:hover:text-amber-400 truncate block"
                                                :title="file.name"
                                            >
                                                {{ file.name }}
                                            </a>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                    </svg>
                                                    {{ file.file_type }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    .{{ file.extension }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ formatDate(file.uploaded_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span
                                        v-if="file.status === 'failed'"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1.5 text-sm font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-200"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Failed
                                    </span>
                                    <span
                                        v-else-if="file.status === 'processing'"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-700 dark:bg-orange-900/40 dark:text-amber-200"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2" />
                                        </svg>
                                        Processing
                                    </span>
                                    <span
                                        v-else-if="file.status === 'pending'"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1.5 text-sm font-semibold text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                                        </svg>
                                        Pending
                                    </span>
                                    <span v-else-if="file.status === 'needs_review'" class="rounded-full bg-yellow-100 px-3 py-1.5 text-sm font-semibold text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200">Needs review</span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1.5 text-sm font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-200"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Completed
                                    </span>
                                    <button type="button" @click="selectedFileId = selectedFileId === file.id ? null : file.id" :aria-expanded="selectedFileId === file.id" :aria-controls="'activity-actions-' + file.id" class="text-xs font-medium text-amber-700 dark:text-amber-400">{{ selectedFileId === file.id ? 'Hide actions' : 'Show actions' }}</button>
                                </div>
                            </div>

                            <div v-if="selectedFileId === file.id && file.status === 'needs_review'" class="mt-4 rounded-lg bg-amber-50 p-4 dark:bg-zinc-900">
                                <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ file.review?.reasoning || 'This file needs review before processing can finish.' }}</p>
                                <p v-if="file.review?.confidence != null" class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Classification confidence: {{ Math.round(file.review.confidence * 100) }}%</p>
                                <div v-if="file.review?.reason === 'uncertain_classification'" class="mt-3 flex flex-wrap items-center gap-3">
                                    <label :for="`type-${file.id}`" class="text-sm dark:text-zinc-200">Document type</label>
                                    <select :id="`type-${file.id}`" v-model="selectedTypeById[file.id]" class="rounded border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white">
                                        <option v-for="type in props.reviewTypes" :key="type" :value="type">{{ type.replaceAll('_', ' ') }}</option>
                                    </select>
                                    <PrimaryButton type="button" @click="changeTypeAndRestart(file.id)">Retry extraction</PrimaryButton>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div v-if="selectedFileId === file.id" :id="'activity-actions-' + file.id" class="mt-3 flex flex-wrap items-center gap-3">
                                <Link :href="route('files.extraction-report', file.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Extraction report
                                </Link>
                                <Link :href="route('files.show', file.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Open file workspace</Link>
                                <a
                                    :href="file.viewUrl"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-amber-50 dark:bg-zinc-700 dark:text-zinc-200 dark:ring-zinc-600 dark:hover:bg-amber-600"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Open original
                                </a>

                                <template v-if="file.status === 'failed'">
                                    <Link v-if="file.extension?.toLowerCase() === 'csv'"
                                        :href="route('bank-statements.csv-mapping.edit', file.id)"
                                        class="text-blue-600 dark:text-blue-400 hover:underline">
                                        Correct CSV mapping
                                    </Link>
                                    <PrimaryButton type="button" @click="restart(file.id)">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Retry Processing
                                    </PrimaryButton>

                                    <SecondaryButton type="button" @click="toggleExpanded(file.id)">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Change Type & Retry
                                    </SecondaryButton>
                                </template>
                            </div>

                            <!-- Expanded Options -->
                            <div
                                v-if="selectedFileId === file.id && file.status === 'failed' && expandedFileId === file.id"
                                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
                            >
                                <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Change Processing Type</h4>
                                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                                    If processing failed, try changing the file type. This may help if the file was incorrectly classified.
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Process as:</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center">
                                            <input
                                                v-model="selectedTypeById[file.id]"
                                                type="radio"
                                                value="receipt"
                                                class="h-4 w-4 border-zinc-300 text-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700"
                                            />
                                            <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">Receipt</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                v-model="selectedTypeById[file.id]"
                                                type="radio"
                                                value="document"
                                                class="h-4 w-4 border-zinc-300 text-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700"
                                            />
                                            <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">Document</span>
                                        </label>
                                    </div>
                                    <PrimaryButton type="button" @click="changeTypeAndRestart(file.id)" class="ml-auto">
                                        Apply & Retry
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <Pagination
                    v-if="props.pagination.last_page > 1"
                    :page="form.page"
                    @update:page="page => form.page = page"
                    :pagination="props.pagination"
                    class="mt-6"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
