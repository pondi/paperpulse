<template>
  <Head title="Jobs" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">Jobs</h2>
    </template>

    <div class="py-12 bg-gray-900">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <JobStats :stats="stats" />
        
        <!-- PulseDav File Processing Status -->
        <PulseDavStats :stats="pulseDavStats" :recent-files="recentPulseDavFiles" />
        
        <JobFilters :form="form" :queues="queues" @update:form="updateForm" />
        
        <!-- Jobs List -->
        <div v-if="jobs?.length" class="space-y-4">
          <JobCard v-for="job in jobs" :key="job.id" :job="job" />
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
        />
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

interface Job {
  id: string;
  name: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  queue: string;
  started_at: string | null;
  finished_at: string | null;
  progress: number;
  attempt: number;
  exception: string | null;
  duration: number | null;
  order: number;
  tasks?: Job[];
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
  jobs: Job[];
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

watch(form, (newForm) => {
  router.get('/jobs', newForm, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['jobs', 'stats', 'pulseDavStats', 'recentPulseDavFiles', 'pagination']
  });
}, { deep: true });
</script> 