<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import Pagination from '@/Pages/Jobs/Components/Pagination.vue';

type FileItem = {
    id: number;
    guid: string;
    name: string;
    file_type: 'receipt' | 'document';
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
}

interface PaginationInfo {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    files: {
        data: FileItem[];
        links: any;
        meta: any;
    };
    stats: Stats;
    filters: {
        status: string;
        per_page: number;
    };
    pagination: PaginationInfo;
}

const props = defineProps<Props>();

const form = reactive({
    status: props.filters?.status ?? '',
    per_page: props.filters?.per_page ?? 50,
    page: props.pagination?.current_page ?? 1,
});

const selectedTypeById = ref<Record<number, 'receipt' | 'document'>>(
    Object.fromEntries(
        props.files.data.map(f => [
            f.id,
            f.file_type === 'receipt' ? 'document' : 'receipt',
        ])
    ) as Record<number, 'receipt' | 'document'>
);

const expandedFileId = ref<number | null>(null);

// Watch for filter changes and update URL
watch(
    () => [form.status, form.per_page, form.page],
    () => {
        router.get(
            route('files.index'),
            {
                status: form.status || undefined,
                per_page: form.per_page,
                page: form.page,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }
);

const formatDate = (iso: string | null) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

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
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 rounded-md bg-amber-100 p-3 dark:bg-orange-900/40">
                                <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">Total Files</p>
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
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">Completed</p>
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
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">In Progress</p>
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
                                <p class="text-sm font-bold text-zinc-500 dark:text-zinc-400 dark:text-zinc-400">Failed</p>
                                <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ props.stats.failed }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                    <div class="flex flex-wrap gap-4">
                        <select
                            v-model="form.status"
                            @change="form.page = 1"
                            class="bg-white text-zinc-900 dark:bg-zinc-700 dark:text-white rounded-md border border-zinc-300 dark:border-zinc-600 shadow-sm focus:border-amber-500 focus:ring focus:ring-amber-500 focus:ring-opacity-50"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
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

                <!-- Files List -->
                <div v-if="props.files.data.length === 0" class="rounded-lg bg-white p-12 text-center shadow dark:bg-zinc-800">
                    <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-zinc-900 dark:text-zinc-100">No files yet</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Upload files to get started with document processing
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="file in props.files.data"
                        :key="file.id"
                        class="overflow-hidden rounded-lg bg-white shadow transition-shadow hover:shadow-md dark:bg-zinc-800"
                        :class="{ 'ring-2 ring-red-500 dark:ring-red-400': file.status === 'failed' }"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <!-- File Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <!-- File Icon -->
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-zinc-700">
                                                <svg class="h-6 w-6 text-zinc-600 dark:text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- File Details -->
                                        <div class="flex-1 min-w-0">
                                            <a
                                                :href="file.previewUrl ?? file.viewUrl"
                                                target="_blank"
                                                class="text-lg font-semibold text-zinc-900 hover:text-amber-600 dark:text-zinc-100 dark:hover:text-amber-400 truncate block"
                                                :title="file.name"
                                            >
                                                {{ file.name }}
                                            </a>
                                            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
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
                                <div class="ml-4 flex-shrink-0">
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
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1.5 text-sm font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-200"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Completed
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex items-center gap-3">
                                <a
                                    :href="file.viewUrl"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-amber-50 dark:bg-zinc-700 dark:text-zinc-200 dark:ring-zinc-600 dark:hover:bg-amber-600"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View File
                                </a>

                                <template v-if="file.status === 'failed'">
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
                                v-if="file.status === 'failed' && expandedFileId === file.id"
                                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40"
                            >
                                <h4 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Change Processing Type</h4>
                                <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                                    If processing failed, try changing the file type. This may help if the file was incorrectly classified.
                                </p>
                                <div class="mt-3 flex items-center gap-3">
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
