<template>
  <Head title="Jobs" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-zinc-100">Job operations</h2>
          <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Monitor queues, inspect failures and restart interrupted chains.</p>
        </div>
        <Link :href="route('files.index', { status: 'needs_review' })" class="inline-flex min-h-11 items-center rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-900 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200 dark:hover:bg-amber-950/60">
          Review uncertain files
        </Link>
      </div>
    </template>

    <div class="mx-auto flex max-w-screen-2xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
      <JobStats :stats="stats" :selected-status="form.status" @filter-status="handleFilterStatus" />

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <main class="min-w-0 space-y-5">
          <JobFilters :form="form" :queues="queues" @update:form="updateForm" />

          <section aria-labelledby="job-history-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h2 id="job-history-heading" class="text-lg font-semibold text-zinc-950 dark:text-zinc-100">Job history</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ pagination.total }} matching job chains</p>
              </div>
              <button
                v-if="jobs.some(job => job.status === 'failed')"
                type="button"
                class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-red-800 underline-offset-4 hover:bg-red-50 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/30"
                @click="selectVisibleFailures"
              >
                {{ allVisibleFailuresSelected ? 'Clear failed selections' : 'Select visible failures' }}
              </button>
            </div>

            <div v-if="actionNotice" role="status" aria-live="polite" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-900 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">
              {{ actionNotice }}
            </div>
            <div v-if="actionError" role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
              {{ actionError }}
            </div>

            <div v-if="selectedJobIds.length" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/20">
              <p class="text-sm font-medium text-red-950 dark:text-red-100">{{ selectedJobIds.length }} failed chain{{ selectedJobIds.length === 1 ? '' : 's' }} selected (maximum 10)</p>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-red-900 hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-200 dark:hover:bg-red-950/60" @click="selectedJobIds = []">Clear selection</button>
                <button type="button" :disabled="isRestartingBatch" class="inline-flex min-h-11 items-center rounded-lg bg-red-700 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-60 dark:bg-red-600 dark:hover:bg-red-500" @click="restartSelectedJobs">
                  {{ isRestartingBatch ? 'Restarting…' : `Restart ${selectedJobIds.length} selected` }}
                </button>
              </div>
            </div>

            <div v-if="jobs.length" class="space-y-3">
              <JobCard
                v-for="job in jobs"
                :key="job.id"
                :job="job"
                :selected="selectedJobIds.includes(job.id)"
                :selection-disabled="selectedJobIds.length >= 10"
                :restarting="restartingJobIds.includes(job.id)"
                @restart="restartJob"
                @toggle-selection="toggleJobSelection"
              />
            </div>

            <div v-else class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
              <h3 class="font-semibold text-zinc-950 dark:text-zinc-100">No jobs match these filters</h3>
              <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Clear a filter or choose another status or queue.</p>
            </div>

            <Pagination v-if="pagination.last_page > 1" :page="form.page" :pagination="pagination" class="mt-4" @update:page="page => form.page = page" />
          </section>
        </main>

        <aside class="min-w-0">
          <PulseDavStats :stats="pulseDavStats" :recent-files="recentPulseDavFiles" />
          <p class="mt-3 text-xs leading-5 text-zinc-500 dark:text-zinc-400">Queue totals cover all recorded jobs. Scanner file totals are scoped to your account.</p>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Link, Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JobStats from '@/Pages/Jobs/Components/JobStats.vue';
import PulseDavStats from '@/Pages/Jobs/Components/PulseDavStats.vue';
import JobFilters from '@/Pages/Jobs/Components/JobFilters.vue';
import JobCard from '@/Pages/Jobs/Components/JobCard.vue';
import Pagination from '@/Pages/Jobs/Components/Pagination.vue';

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

interface Stats {
  pending: number;
  processing: number;
  completed: number;
  failed: number;
}

interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface PulseDavFile {
  id: number;
  filename: string;
  status: string;
  uploaded_at: string | null;
  processed_at: string | null;
  error_message: string | null;
  receipt_id: number | null;
}

interface PulseDavStats {
  total: number;
  pending: number;
  processing: number;
  completed: number;
  failed: number;
}

interface Props {
  jobs: JobChain[];
  stats: Stats;
  pulseDavStats?: PulseDavStats;
  recentPulseDavFiles?: PulseDavFile[];
  queues: string[];
  filters: {
    status: string;
    queue: string;
    search: string;
    per_page: number;
  };
  pagination: Pagination;
}

const props = withDefaults(defineProps<Props>(), {
  jobs: () => [],
  stats: () => ({
    pending: 0,
    processing: 0,
    completed: 0,
    failed: 0
  }),
  pulseDavStats: () => ({
    total: 0,
    pending: 0,
    processing: 0,
    completed: 0,
    failed: 0
  }),
  recentPulseDavFiles: () => [],
  queues: () => [],
  filters: () => ({
    status: '',
    queue: '',
    search: '',
    per_page: 50
  }),
  pagination: () => ({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0
  })
});

const form = reactive({
  status: props.filters?.status ?? '',
  queue: props.filters?.queue ?? '',
  search: props.filters?.search ?? '',
  per_page: props.filters?.per_page ?? 50,
  page: props.pagination?.current_page ?? 1
});

const jobs = ref(props.jobs);
const stats = ref(props.stats);
const pulseDavStats = ref(props.pulseDavStats);
const recentPulseDavFiles = ref(props.recentPulseDavFiles);
const pagination = ref(props.pagination);
const selectedJobIds = ref<string[]>([]);
const restartingJobIds = ref<string[]>([]);
const isRestartingBatch = ref(false);
const actionNotice = ref('');
const actionError = ref('');
const visibleFailedJobIds = computed(() => jobs.value.filter(job => job.status === 'failed').map(job => job.id));
const allVisibleFailuresSelected = computed(() => visibleFailedJobIds.value.length > 0 && visibleFailedJobIds.value.every(id => selectedJobIds.value.includes(id)));
let pollInterval = null;
let fastPollInterval = null;

const updateForm = (newForm: typeof form) => {
  Object.assign(form, newForm);
};

const loadJobsData = async () => {
  try {
    const response = await axios.get('/jobs/status', {
      params: {
        status: form.status || undefined,
        queue: form.queue || undefined,
        search: form.search || undefined,
        per_page: form.per_page,
        page: form.page
      }
    });
    
    if (response.data.success) {
      jobs.value = response.data.data;
      stats.value = response.data.stats;
      pagination.value = response.data.pagination;
      selectedJobIds.value = selectedJobIds.value.filter(id => jobs.value.some(job => job.id === id && job.status === 'failed'));
      
      // Implement adaptive polling - faster when there are processing jobs
      const hasProcessingJobs = response.data.data.some(job => 
        job.status === 'processing' || job.status === 'pending' ||
        job.steps?.some(step => step.status === 'processing' || step.status === 'pending')
      );
      
      // Clear existing fast polling
      if (fastPollInterval) {
        clearInterval(fastPollInterval);
        fastPollInterval = null;
      }
      
      // If we have processing jobs and no filter is applied, poll every 1 second
      if (hasProcessingJobs && !form.status && !form.queue && !form.search) {
        fastPollInterval = setInterval(loadJobsData, 1000);
        // Clear the normal interval to avoid double polling
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
      }
    }
  } catch (error) {
    console.error('Failed to load jobs data:', error);
  }
};



const handleFilterStatus = (status: string) => {
  form.status = status;
  form.page = 1;
};

const toggleJobSelection = (jobId: string) => {
  actionError.value = '';
  selectedJobIds.value = selectedJobIds.value.includes(jobId)
    ? selectedJobIds.value.filter(id => id !== jobId)
    : selectedJobIds.value.length < 10 ? [...selectedJobIds.value, jobId] : selectedJobIds.value;
};

const selectVisibleFailures = () => {
  if (allVisibleFailuresSelected.value) {
    selectedJobIds.value = selectedJobIds.value.filter(id => !visibleFailedJobIds.value.includes(id));

    return;
  }

  const remainingSlots = 10 - selectedJobIds.value.length;
  const unselectedVisible = visibleFailedJobIds.value.filter(id => !selectedJobIds.value.includes(id));
  selectedJobIds.value = [...selectedJobIds.value, ...unselectedVisible.slice(0, remainingSlots)];
  actionNotice.value = unselectedVisible.length > remainingSlots
    ? 'The batch restart limit is 10. Select additional failed chains after this batch.'
    : '';
};

const restartJob = async (jobId: string) => {
  actionNotice.value = '';
  actionError.value = '';
  restartingJobIds.value = [...restartingJobIds.value, jobId];

  try {
    const response = await axios.post(route('jobs.restart', { jobId }), {});
    actionNotice.value = response.data.message || 'Job chain restart initiated.';
    selectedJobIds.value = selectedJobIds.value.filter(id => id !== jobId);
    await loadJobsData();
  } catch (error: any) {
    actionError.value = error.response?.data?.message || 'Could not restart this job chain. Review its diagnostics and try again.';
  } finally {
    restartingJobIds.value = restartingJobIds.value.filter(id => id !== jobId);
  }
};

const restartSelectedJobs = async () => {
  if (!selectedJobIds.value.length || isRestartingBatch.value) {
    return;
  }

  actionNotice.value = '';
  actionError.value = '';
  isRestartingBatch.value = true;

  try {
    const response = await axios.post(route('jobs.restart-multiple'), { job_ids: selectedJobIds.value });
    actionNotice.value = response.data.message || 'Selected job chains were processed.';
    actionError.value = response.data.summary?.failure_count ? 'Some selected chains could not be restarted. Review their diagnostics before retrying.' : '';
    selectedJobIds.value = [];
    await loadJobsData();
  } catch (error: any) {
    actionError.value = error.response?.data?.message || 'Could not restart the selected job chains. Try again.';
  } finally {
    isRestartingBatch.value = false;
  }
};

watch(form, (newForm) => {
  // Clear both polling intervals when filters change
  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }
  if (fastPollInterval) {
    clearInterval(fastPollInterval);
    fastPollInterval = null;
  }
  
  router.get('/jobs', newForm, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['jobs', 'stats', 'pulseDavStats', 'recentPulseDavFiles', 'pagination'],
    onSuccess: () => {
      // Re-establish polling after filter change
      pollInterval = setInterval(loadJobsData, 2000);
    }
  });
}, { deep: true });

onMounted(() => {
  // Poll for job updates every 2 seconds for real-time updates
  pollInterval = setInterval(loadJobsData, 2000);
  
  // Listen for Echo events if available
  if (window.Echo) {
    window.Echo.private(`App.Models.User.${window.page.props.auth.user.id}`)
      .listen('JobStatusChanged', () => {
        loadJobsData();
      })
      .listen('JobCompleted', () => {
        loadJobsData();
      })
      .listen('JobFailed', () => {
        loadJobsData();
      });
  }
});

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval);
  }
  if (fastPollInterval) {
    clearInterval(fastPollInterval);
  }
});
</script> 
