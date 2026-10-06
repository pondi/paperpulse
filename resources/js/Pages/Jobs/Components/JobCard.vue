<template>
  <div :class="{
    'shadow-sm rounded-lg p-6 border': true,
    // Default (non-failed) card colors: light + dark
    'bg-white border-amber-200 dark:bg-zinc-800 dark:border-zinc-700': job.status !== 'failed',
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
            'bg-orange-600 text-white': job.type === 'receipt',
            'bg-purple-600 text-white': job.type === 'document',
            'bg-zinc-600 text-white': job.type === 'unknown'
          }">
            {{ job.type === 'receipt' ? 'R' : job.type === 'document' ? 'D' : '?' }}
          </div>
          
          <div>
            <h3 class="font-semibold text-lg text-zinc-900 dark:text-white">
              {{ job.file_info?.job_name || 'Processing Job' }}
            </h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
              {{ job.file_info?.name || 'Unknown File' }}
              <span v-if="job.file_info?.extension" class="ml-1 px-1.5 py-0.5 bg-amber-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200 rounded text-xs">
                {{ job.file_info.extension.toUpperCase() }}
              </span>
            </p>
          </div>
        </div>
        
        <p class="text-xs text-zinc-500">Job ID: {{ job.id }}</p>
      </div>
      
      <div class="flex items-center gap-3">
        <span :class="{
          'px-3 py-1 rounded-full text-sm font-semibold': true,
          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300': job.status === 'pending',
          'bg-amber-100 text-amber-800 dark:bg-orange-900/50 dark:text-amber-300': job.status === 'processing',
          'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300': job.status === 'completed',
          'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300': job.status === 'failed'
        }">
          {{ job.status }}
        </span>
      </div>
    </div>

    <!-- Overall Progress Bar -->
    <div class="w-full bg-amber-200 dark:bg-zinc-700 rounded-full h-2.5 mb-4">
      <div class="bg-amber-500 h-2.5 rounded-full transition-all duration-500" 
        :style="{ width: `${job.progress}%` }">
      </div>
    </div>

    <!-- Job Details -->
    <div class="grid grid-cols-3 gap-4 text-sm text-zinc-600 dark:text-zinc-400 mb-6">
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
      <h4 class="font-semibold mb-4 text-zinc-900 dark:text-white">Processing Pipeline</h4>
      
      <!-- Horizontal Step Flow -->
      <div class="flex items-center gap-2 overflow-x-auto pb-4">
        <div v-for="(step, index) in job.steps" :key="step.id" class="flex items-center gap-2 flex-shrink-0">
          <!-- Step Circle -->
          <div :class="{
            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2': true,
            'bg-green-600 border-green-600 text-white': step.status === 'completed',
            'bg-orange-600 border-amber-600 text-white animate-pulse': step.status === 'processing',
            'bg-red-600 border-red-600 text-white': step.status === 'failed',
            'bg-zinc-600 border-zinc-600 text-zinc-300': step.status === 'pending',
          }">
            {{ index + 1 }}
          </div>
          
          <!-- Step Info -->
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ step.name }}</div>
            <div class="text-xs text-zinc-600 dark:text-zinc-400">
              {{ step.status }}
              <span v-if="step.duration !== null && step.duration !== undefined">
                - {{ formatDuration(step.duration) }}
              </span>
            </div>
            <div v-if="step.status === 'processing'" class="w-16 bg-amber-200 dark:bg-zinc-700 rounded-full h-1 mt-1">
              <div class="bg-amber-500 h-1 rounded-full transition-all duration-500" 
                :style="{ width: `${step.progress}%` }">
              </div>
            </div>
          </div>
          
          <!-- Arrow -->
          <div v-if="index < job.steps.length - 1" class="text-zinc-400 dark:text-zinc-500 mx-2">
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
}

const props = defineProps<Props>();
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

</script> 
