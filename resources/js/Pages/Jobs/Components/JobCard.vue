<template>
  <div class="bg-gray-800 shadow-sm rounded-lg p-6 border border-gray-700">
    <div class="flex justify-between items-start mb-4">
      <div>
        <h3 class="font-semibold text-lg text-white">{{ job.name }}</h3>
        <p class="text-sm text-gray-400">ID: {{ job.id }}</p>
      </div>
      <div class="text-right">
        <span :class="{
          'px-3 py-1 rounded-full text-sm font-semibold': true,
          'bg-yellow-900/50 text-yellow-300': job.status === 'pending',
          'bg-blue-900/50 text-blue-300': job.status === 'processing',
          'bg-green-900/50 text-green-300': job.status === 'completed',
          'bg-red-900/50 text-red-300': job.status === 'failed'
        }">
          {{ job.status }}
        </span>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="w-full bg-gray-700 rounded-full h-2.5 mb-4">
      <div class="bg-indigo-500 h-2.5 rounded-full transition-all duration-500" 
        :style="{ width: `${job.progress}%` }">
      </div>
    </div>

    <!-- Job Details -->
    <div class="grid grid-cols-3 gap-4 text-sm text-gray-400 mb-4">
      <div>
        <p>Started: {{ job.started_at }}</p>
        <p v-if="job.finished_at">Finished: {{ job.finished_at }}</p>
      </div>
      <div>
        <p>Queue: {{ job.queue }}</p>
        <p>Attempt: {{ job.attempt }}</p>
      </div>
      <div>
        <p v-if="job.duration">Duration: {{ job.duration }}s</p>
      </div>
    </div>

    <!-- Tasks -->
    <div v-if="job.tasks?.length" class="mt-4 pl-4 border-l-2 border-gray-700">
      <h4 class="font-semibold mb-2 text-white">Tasks</h4>
      <div v-for="task in job.tasks" :key="task.id" 
        class="text-sm mb-2 last:mb-0">
        <div class="flex justify-between items-center">
          <span class="text-gray-300">{{ task.name }}</span>
          <span :class="{
            'px-2 py-0.5 rounded text-xs font-semibold': true,
            'bg-yellow-900/50 text-yellow-300': task.status === 'pending',
            'bg-blue-900/50 text-blue-300': task.status === 'processing',
            'bg-green-900/50 text-green-300': task.status === 'completed',
            'bg-red-900/50 text-red-300': task.status === 'failed'
          }">
            {{ task.status }}
          </span>
        </div>
        <div v-if="task.exception" class="text-red-400 mt-1">
          {{ task.exception }}
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="job.exception" class="mt-4 text-red-400 text-sm">
      {{ job.exception }}
    </div>
  </div>
</template>

<script setup lang="ts">
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

interface Props {
  job: Job;
}

defineProps<Props>();
</script> 