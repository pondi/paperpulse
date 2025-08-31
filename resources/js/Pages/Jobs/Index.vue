<template>
  <Head title="Jobs" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">Jobs</h2>
    </template>

    <div class="py-12 bg-gray-900">
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
            <div v-else class="bg-gray-800 shadow-sm rounded-lg p-6 text-center text-gray-400 border border-gray-700">
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
import { reactive, watch } from 'vue';
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

const updateForm = (newForm: typeof form) => {
  Object.assign(form, newForm);
};

const handleJobRestart = (jobId: string) => {
  console.log('Job restart initiated for:', jobId);
  // The JobCard component handles the actual restart request
  // This is just for parent component awareness if needed
};

watch(form, (newForm) => {
  router.get('/jobs', newForm, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['jobs', 'stats', 'pulseDavStats', 'recentPulseDavFiles', 'pagination']
  });
}, { deep: true });
</script> 