<template>
  <Head title="PaperPulse - Job Monitor" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Job Monitor</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Queue Status -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Queue Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div v-for="(count, queue) in queued" :key="queue" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ queue }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ count }} jobs</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Job Chains -->
        <div v-if="Object.keys(chains).length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Job Chains</h3>
            <div class="space-y-6">
              <div v-for="(chain, chainId) in chains" :key="chainId" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Chain {{ chainId }}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ chain.completed }} of {{ chain.total }} completed
                      <span v-if="chain.failed > 0" class="text-red-500">({{ chain.failed }} failed)</span>
                    </p>
                  </div>
                  <div class="flex items-center gap-x-2">
                    <div class="w-32 bg-gray-200 rounded-full h-2 dark:bg-gray-600">
                      <div class="bg-blue-600 h-2 rounded-full" :style="{ width: chain.progress + '%' }"></div>
                    </div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ Math.round(chain.progress) }}%</span>
                  </div>
                </div>
                <div class="space-y-2">
                  <div v-for="job in chain.jobs" :key="job.id" 
                       class="text-sm flex items-center justify-between py-2 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex items-center gap-x-3">
                      <div :class="[
                        job.status === 'completed' ? 'bg-green-400' : 
                        job.status === 'failed' ? 'bg-red-400' :
                        job.status === 'processing' ? 'bg-yellow-400' :
                        'bg-gray-400',
                        'h-2 w-2 rounded-full'
                      ]"></div>
                      <span>{{ formatJobName(job.name) }}</span>
                      <span v-if="job.attempts > 1" class="text-xs text-gray-500">(Attempt {{ job.attempts }})</span>
                    </div>
                    <div class="flex items-center gap-x-4">
                      <span>{{ getTimeText(job) }}</span>
                      <button v-if="job.status === 'failed'"
                              @click="rerunJob(job.id)"
                              class="text-indigo-600 hover:text-indigo-500">
                        Rerun
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Individual Jobs -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="mb-4">
              <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                <div class="flex items-center">
                  <div class="w-2 h-2 rounded-full bg-gray-500 mr-2"></div>
                  <span>Queued ({{ counts.pending }})</span>
                </div>
                <div class="flex items-center">
                  <div class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></div>
                  <span>Processing ({{ counts.processing }})</span>
                </div>
                <div class="flex items-center">
                  <div class="w-2 h-2 rounded-full bg-red-500 mr-2"></div>
                  <span>Failed ({{ counts.failed }})</span>
                </div>
              </div>
            </div>

            <template v-if="jobs.length > 0">
              <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-700">
                <li v-for="job in jobs" :key="job.id" class="flex items-center justify-between gap-x-6 py-5">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-x-3">
                      <p class="text-sm font-semibold leading-6 text-gray-900 dark:text-gray-100">{{ formatJobName(job.name) }}</p>
                      <div :class="[
                        job.status === 'completed' ? 'text-green-700 bg-green-50 ring-green-600/20 dark:bg-green-900/50' :
                        job.status === 'failed' ? 'text-red-700 bg-red-50 ring-red-600/20 dark:bg-red-900/50' :
                        job.status === 'processing' ? 'text-blue-700 bg-blue-50 ring-blue-600/20 dark:bg-blue-900/50' :
                        'text-yellow-700 bg-yellow-50 ring-yellow-600/20 dark:bg-yellow-900/50',
                        'rounded-md py-1 px-2 text-xs font-medium ring-1 ring-inset'
                      ]">
                        {{ getStatusText(job.status) }}
                      </div>
                    </div>
                    <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                      <p class="whitespace-nowrap">Queue: {{ job.queue }}</p>
                      <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                        <circle cx="1" cy="1" r="1" />
                      </svg>
                      <p class="truncate">{{ getTimeText(job) }}</p>
                      <template v-if="job.status === 'failed' && job.exception">
                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                          <circle cx="1" cy="1" r="1" />
                        </svg>
                        <p class="truncate text-red-500">Error: {{ job.exception }}</p>
                      </template>
                    </div>
                  </div>
                  <div class="flex items-center gap-x-4">
                    <div class="w-24 bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                      <div 
                        :class="[
                          job.status === 'completed' ? 'bg-green-600' :
                          job.status === 'failed' ? 'bg-red-600' :
                          'bg-yellow-600'
                        ]"
                        class="h-2.5 rounded-full" 
                        :style="{ width: (job.progress || 0) + '%' }"
                      ></div>
                    </div>
                    <span class="text-sm">{{ job.progress || 0 }}%</span>
                    <button 
                      v-if="job.status === 'failed'"
                      @click="rerunJob(job.id)"
                      class="text-sm text-indigo-600 hover:text-indigo-500"
                    >
                      Rerun
                    </button>
                  </div>
                </li>
              </ul>
            </template>
            <template v-else>
              <div class="text-center">
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">No active jobs</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no jobs running at the moment.</p>
                <p v-if="counts.pending === 0" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  No queue worker running. To start processing jobs, run <code class="bg-gray-100 dark:bg-gray-900 p-1 rounded">php artisan queue:work</code>
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import { useToast } from "vue-toastification";

const jobs = ref([]);
const counts = ref({
  pending: 0,
  processing: 0,
  failed: 0
});
const chains = ref({});
const queued = ref({});
let intervalId = null;

const toast = useToast();

const formatJobName = (name) => {
  return name.split('\\').pop();
};

const getStatusText = (status) => {
  switch (status) {
    case 'processing': return 'Running';
    case 'completed': return 'Completed';
    case 'failed': return 'Failed';
    case 'queued': return 'Queued';
    default: return status;
  }
};

const getTimeText = (job) => {
  const date = new Date(job.started_at);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);

  if (diffInSeconds < 60) {
    return 'Just now';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
  } else {
    return formatDate(job.started_at);
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleString('en-US', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const fetchJobs = async () => {
  try {
    const response = await axios.get('/jobs/status');
    if (response.data.success) {
      const newJobs = response.data.data;
      
      newJobs.forEach(job => {
        const existingJob = jobs.value.find(j => j.id === job.id);
        
        if (!existingJob) {
          if (job.status === 'completed') {
            toast.success(`Job ${job.name} completed`);
          } else if (job.status === 'failed') {
            toast.error(`Job ${job.name} failed`);
          }
        }
      });

      jobs.value = newJobs;
      counts.value = response.data.counts;
      chains.value = response.data.chains;
      queued.value = response.data.queued;
    } else {
      console.error('Could not fetch job status:', response.data.message);
    }

  } catch (error) {
    console.error('Could not fetch job status:', error.response?.data?.message || error.message);
  }
};

const rerunJob = async (jobId) => {
  try {
    await axios.post(`/jobs/${jobId}/rerun`);
    toast.success('Job requeued successfully');
    await fetchJobs();
  } catch (error) {
    toast.error('Failed to rerun job: ' + (error.response?.data?.message || error.message));
  }
};

onMounted(() => {
  fetchJobs();
  intervalId = setInterval(fetchJobs, 5000);
});

onBeforeUnmount(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
});
</script> 