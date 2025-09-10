<template>
  <div :class="{
    'shadow-sm rounded-lg p-6 border': true,
    // Default (non-failed) card colors: light + dark
    'bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700': job.status !== 'failed',
    // Failed card colors: light + dark
    'bg-red-50 border-red-300 dark:bg-red-900/20 dark:border-red-800': job.status === 'failed'
  }">
    <!-- Job Header -->
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <div class="flex items-center gap-3 mb-2">
          <!-- File Type Icon -->
          <div :class="{
            'w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold': true,
            'bg-blue-600 text-white': job.type === 'receipt',
            'bg-purple-600 text-white': job.type === 'document',
            'bg-gray-600 text-white': job.type === 'unknown'
          }">
            {{ job.type === 'receipt' ? 'R' : job.type === 'document' ? 'D' : '?' }}
          </div>
          
          <div>
            <h3 class="font-semibold text-lg text-gray-900 dark:text-white">
              {{ job.file_info?.job_name || 'Processing Job' }}
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ job.file_info?.name || 'Unknown File' }}
              <span v-if="job.file_info?.extension" class="ml-1 px-1.5 py-0.5 bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200 rounded text-xs">
                {{ job.file_info.extension.toUpperCase() }}
              </span>
            </p>
          </div>
        </div>
        
        <p class="text-xs text-gray-500">Job ID: {{ job.id }}</p>
      </div>
      
      <div class="flex items-center gap-3">
        <!-- Restart Button for Failed Jobs -->
        <button
          v-if="canRestart"
          @click="restartJob"
          :disabled="isRestarting"
          class="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:opacity-50 text-white text-sm rounded-lg font-medium transition-colors duration-200"
          :title="isRestarting ? 'Restarting...' : 'Restart failed job chain'"
        >
          <span v-if="isRestarting" class="flex items-center gap-2">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="m15.84 12.32-.1-.17a2 2 0 01.73-2.63l.06-.04a6.97 6.97 0 00-1.92-2.64 2 2 0 01-2.62.73l-.18.1A2 2 0 019 6.68v-.07A6.98 6.98 0 006.32 8.16 2 2 0 017.27 10.8l-.1.17a2 2 0 01-2.63-.73l-.04-.06a6.97 6.97 0 00-1.64 1.92 2 2 0 01.73 2.62l-.1.18A2 2 0 016.32 17v.07a6.98 6.98 0 001.84-1.48 2 2 0 012.62.73l.04.06a6.97 6.97 0 001.92-1.64 2 2 0 01-.73-2.62l.1-.18A2 2 0 0115 11.32v-.07a6.98 6.98 0 00-1.48 1.84 2 2 0 01-.73-2.62z"></path>
            </svg>
            Restarting...
          </span>
          <span v-else class="flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Restart
          </span>
        </button>
        
        <span :class="{
          'px-3 py-1 rounded-full text-sm font-semibold': true,
          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': job.status === 'pending',
          'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300': job.status === 'processing',
          'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300': job.status === 'completed',
          'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300': job.status === 'failed'
        }">
          {{ job.status }}
        </span>
      </div>
    </div>

    <!-- Overall Progress Bar -->
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mb-4">
      <div class="bg-indigo-500 h-2.5 rounded-full transition-all duration-500" 
        :style="{ width: `${job.progress}%` }">
      </div>
    </div>

    <!-- Job Details -->
    <div class="grid grid-cols-3 gap-4 text-sm text-gray-600 dark:text-gray-400 mb-6">
      <div>
        <p>Started: {{ formatDateTime(job.started_at) }}</p>
        <p v-if="job.finished_at">Finished: {{ formatDateTime(job.finished_at) }}</p>
      </div>
      <div>
        <p>Queue: {{ job.queue }}</p>
        <p>Type: {{ job.type?.toUpperCase() || 'UNKNOWN' }}</p>
      </div>
      <div>
        <p v-if="job.duration !== null && job.duration !== undefined">Duration: {{ formatDuration(job.duration) }}</p>
        <p v-if="job.file_info?.size">Size: {{ formatFileSize(job.file_info.size) }}</p>
      </div>
    </div>

    <!-- Processing Pipeline -->
    <div v-if="job.steps?.length" class="mt-6">
      <h4 class="font-semibold mb-4 text-gray-900 dark:text-white">Processing Pipeline</h4>
      
      <!-- Horizontal Step Flow -->
      <div class="flex items-center gap-2 overflow-x-auto pb-4">
        <div v-for="(step, index) in job.steps" :key="step.id" class="flex items-center gap-2 flex-shrink-0">
          <!-- Step Circle -->
          <div :class="{
            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2': true,
            'bg-green-600 border-green-600 text-white': step.status === 'completed',
            'bg-blue-600 border-blue-600 text-white animate-pulse': step.status === 'processing',
            'bg-red-600 border-red-600 text-white': step.status === 'failed',
            'bg-gray-600 border-gray-600 text-gray-300': step.status === 'pending',
          }">
            {{ index + 1 }}
          </div>
          
          <!-- Step Info -->
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ step.name }}</div>
            <div class="text-xs text-gray-600 dark:text-gray-400">
              {{ step.status }}
              <span v-if="step.duration !== null && step.duration !== undefined">
                - {{ formatDuration(step.duration) }}
              </span>
            </div>
            <div v-if="step.status === 'processing'" class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1 mt-1">
              <div class="bg-blue-500 h-1 rounded-full transition-all duration-500" 
                :style="{ width: `${step.progress}%` }">
              </div>
            </div>
          </div>
          
          <!-- Arrow -->
          <div v-if="index < job.steps.length - 1" class="text-gray-400 dark:text-gray-500 mx-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </div>
        </div>
      </div>
      
      <!-- Failed Step Details -->
      <div v-for="step in failedSteps" :key="`error-${step.id}`" class="mt-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg">
        <h5 class="text-red-700 dark:text-red-400 font-semibold mb-2">{{ step.name }} Failed</h5>
        <pre class="text-red-600 dark:text-red-300 text-sm whitespace-pre-wrap">{{ step.exception }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
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
}

const props = defineProps<Props>();
const emit = defineEmits<{
  restart: [jobId: string];
}>();

const isRestarting = ref(false);

// Check if the job can be restarted (has failed steps or is failed itself)
const canRestart = computed(() => {
  if (props.job.status === 'failed') {
    return true;
  }
  
  // Check if any steps failed
  if (props.job.steps?.some(step => step.status === 'failed')) {
    return true;
  }
  
  return false;
});

// Get failed steps for error display
const failedSteps = computed(() => {
  return props.job.steps?.filter(step => step.status === 'failed' && step.exception) || [];
});

// Format file size helper
const formatFileSize = (bytes: number): string => {
  if (!bytes) return '';
  
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  
  return Math.round(bytes / Math.pow(1024, i)) + ' ' + sizes[i];
};

const restartJob = () => {
  if (isRestarting.value) return;
  
  isRestarting.value = true;
  
  // Use Inertia's router.post for proper CSRF handling
  router.post(`/jobs/${props.job.id}/restart`, {}, {
    preserveScroll: true,
    preserveState: false,
    onStart: () => {
      console.log('Restarting job:', props.job.id);
    },
    onSuccess: () => {
      // Emit restart event to parent component
      emit('restart', props.job.id);
      console.log('Job restart initiated successfully');
      isRestarting.value = false;
      
      // The page will reload automatically with updated data
    },
    onError: (errors) => {
      console.error('Failed to restart job:', errors);
      alert('Failed to restart job. Please try again.');
      isRestarting.value = false;
    },
    onFinish: () => {
      // Reset state if needed
      if (isRestarting.value) {
        isRestarting.value = false;
      }
    }
  });
};
</script> 
