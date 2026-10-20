<template>
    <article
        :data-job-card="job.id"
        :class="[
            'rounded-xl border p-4 shadow-xs sm:p-5',
            job.status === 'failed'
                ? 'border-red-200 bg-red-50/70 dark:border-red-900 dark:bg-red-950/20'
                : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800',
        ]"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                <div
                    aria-hidden="true"
                    :class="[
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white',
                        job.type === 'receipt' ? 'bg-orange-700' : job.type === 'document' ? 'bg-indigo-700' : 'bg-zinc-700',
                    ]"
                >
                    {{ job.type === 'receipt' ? 'R' : job.type === 'document' ? 'D' : '?' }}
                </div>
                <div class="min-w-0">
                    <h3 class="wrap-break-word text-base font-semibold text-zinc-950 dark:text-zinc-100 sm:text-lg">
                        {{ job.file_info?.job_name || 'Processing Job' }}
                    </h3>
                    <p class="mt-1 break-all text-sm text-zinc-700 dark:text-zinc-300">
                        {{ job.file_info?.name || 'Unknown file' }}
                        <span v-if="job.file_info?.extension" class="ml-1 rounded-sm bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                            {{ job.file_info.extension.toUpperCase() }}
                        </span>
                    </p>
                    <p class="mt-1 break-all font-mono text-xs text-zinc-500 dark:text-zinc-400">Job ID: {{ job.id }}</p>
                </div>
            </div>

            <span
                :aria-label="`Job status: ${job.status}`"
                class="rounded-full px-3 py-1 text-sm font-semibold capitalize"
                :class="statusClasses[job.status]"
            >
                {{ job.status }}
            </span>
        </header>

        <div v-if="job.status === 'failed'" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-red-200 pt-4 dark:border-red-900">
            <label class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-red-950 dark:text-red-100">
                <input
                    type="checkbox"
                    :checked="selected"
                    :disabled="selectionDisabled && !selected"
                    :aria-label="`Select failed job ${job.file_info?.name || job.id}`"
                    data-job-selection
                    class="rounded-sm border-red-400 text-red-700 focus:ring-red-600 disabled:opacity-50 dark:border-red-700"
                    @change="$emit('toggle-selection', job.id)"
                >
                Select for batch restart
            </label>
            <button
                type="button"
                :disabled="restarting"
                data-job-restart
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white shadow-xs transition hover:bg-red-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:bg-red-600 dark:hover:bg-red-500"
                @click="$emit('restart', job.id)"
            >
                {{ restarting ? 'Restarting…' : 'Restart failed chain' }}
            </button>
        </div>

        <div
            class="mt-4 h-2.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
            role="progressbar"
            :aria-label="`Processing progress for ${job.file_info?.name || 'job'}`"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="job.progress"
        >
            <div class="h-full rounded-full bg-amber-600 transition-all dark:bg-amber-500" :style="{ width: `${job.progress}%` }"></div>
        </div>

        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Started</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ formatDateTime(job.started_at) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Queue and type</dt>
                <dd class="mt-1 wrap-break-word text-zinc-800 dark:text-zinc-200">{{ job.queue }} · {{ job.type?.toUpperCase() || 'UNKNOWN' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Duration</dt>
                <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ job.duration !== null && job.duration !== undefined ? formatDuration(job.duration) : 'In progress' }}</dd>
            </div>
        </dl>

        <section v-if="job.steps?.length" aria-label="Processing steps" class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <h4 class="font-semibold text-zinc-950 dark:text-zinc-100">Processing pipeline</h4>
            <ol class="mt-3 flex gap-3 overflow-x-auto pb-2">
                <li v-for="(step, index) in job.steps" :key="step.id" class="flex min-w-44 flex-1 items-start gap-2 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span
                        aria-hidden="true"
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                        :class="step.status === 'completed' ? 'bg-green-700' : step.status === 'failed' ? 'bg-red-700' : step.status === 'processing' ? 'bg-amber-700' : 'bg-zinc-500'"
                    >
                        {{ index + 1 }}
                    </span>
                    <div class="min-w-0">
                        <p class="wrap-break-word text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ step.name }}</p>
                        <p class="mt-1 text-xs capitalize text-zinc-600 dark:text-zinc-400">{{ step.status }}<span v-if="step.duration !== null && step.duration !== undefined"> · {{ formatDuration(step.duration) }}</span></p>
                    </div>
                </li>
            </ol>

            <details v-for="step in failedSteps" :key="`error-${step.id}`" class="mt-3 rounded-lg border border-red-200 bg-white dark:border-red-900 dark:bg-zinc-900">
                <summary class="min-h-11 cursor-pointer px-4 py-3 text-sm font-semibold text-red-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-300">
                    {{ step.name }} failed · show diagnostics
                </summary>
                <pre class="max-h-72 overflow-auto whitespace-pre-wrap wrap-break-word border-t border-red-100 p-4 text-xs text-red-800 dark:border-red-900 dark:text-red-200">{{ step.exception }}</pre>
            </details>
        </section>
    </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime, formatDuration } from '@/utils/datetime';

interface JobStep {
    id: string;
    name: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    progress: number;
    started_at: string | null;
    finished_at: string | null;
    duration: number | null;
    attempt: number;
    exception: string | null;
    order: number;
}

interface FileInfo {
    name: string;
    extension?: string;
    size?: number;
    job_name: string;
}

interface JobChain {
    id: string;
    type: 'receipt' | 'document' | 'unknown';
    file_info: FileInfo | null;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    progress: number;
    queue: string;
    started_at: string | null;
    finished_at: string | null;
    duration: number | null;
    steps: JobStep[];
}

interface Props {
    job: JobChain;
    selected: boolean;
    selectionDisabled: boolean;
    restarting: boolean;
}

const props = defineProps<Props>();
defineEmits<{
    (event: 'restart', jobId: string): void;
    (event: 'toggle-selection', jobId: string): void;
}>();

const failedSteps = computed(() => props.job.steps?.filter(step => step.status === 'failed' && step.exception) || []);
const statusClasses: Record<JobChain['status'], string> = {
    pending: 'bg-yellow-100 text-yellow-900 dark:bg-yellow-950/60 dark:text-yellow-200',
    processing: 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200',
    completed: 'bg-green-100 text-green-900 dark:bg-green-950/60 dark:text-green-200',
    failed: 'bg-red-100 text-red-900 dark:bg-red-950/60 dark:text-red-200',
};
</script>
