<template>
  <Head title="Jobs" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">Jobs</h2>
    </template>

    <div class="py-12 bg-amber-50 dark:bg-zinc-900">
      <div class="max-w-7xl 2xl:max-w-screen-2xl mx-auto sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
          <!-- Left Column: Statistics -->
          <div class="lg:col-span-4 xl:col-span-3 space-y-6 mb-6 lg:mb-0">
            <JobStats :stats="stats" />
            <PulseDavStats :stats="pulseDavStats" :recent-files="recentPulseDavFiles" />
          </div>
          
          <!-- Right Column: Filters and Jobs -->
          <div class="lg:col-span-8 xl:col-span-9">
            <JobFilters :form="form" :queues="queues" @update:form="updateForm" class="mb-6" />
            
            <!-- Jobs List -->
            <div v-if="jobs?.length" class="space-y-4">
              <JobCard v-for="job in jobs" :key="job.id" :job="job" @restart="handleJobRestart" />
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg p-6 text-center text-zinc-600 dark:text-zinc-400 border border-amber-200 dark:border-zinc-700">
              No jobs found
            </div>

            <Pagination 
              v-if="pagination?.last_page > 1" 
              :page="form.page"
              @update:page="page => form.page = page" 
              :pagination="pagination" 
              class="mt-6"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, watch, ref, onMounted, onUnmounted } from 'vue';
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
    search: ''
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
  page: props.pagination?.current_page ?? 1
});

const jobs = ref(props.jobs);
const stats = ref(props.stats);
const pulseDavStats = ref(props.pulseDavStats);
const recentPulseDavFiles = ref(props.recentPulseDavFiles);
const pagination = ref(props.pagination);
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
        page: form.page
      }
    });
    
    if (response.data.success) {
      jobs.value = response.data.data;
      stats.value = response.data.stats;
      pagination.value = response.data.pagination;
      
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

const handleJobRestart = (jobId: string) => {
  if (import.meta.env.DEV) console.log('Job restart initiated for:', jobId);
  // Refresh data after restart
  setTimeout(() => loadJobsData(), 1000);
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
