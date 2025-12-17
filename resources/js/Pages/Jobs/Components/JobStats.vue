<template>
  <div class="space-y-1 bg-white dark:bg-zinc-800 rounded-lg border border-amber-200 dark:border-zinc-700 overflow-hidden">
    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white px-4 py-3 bg-amber-50 dark:bg-zinc-900/50 border-b border-amber-200 dark:border-zinc-700">
      Job Statistics
      <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400 ml-2">(All time)</span>
    </h3>

    <button
      @click="$emit('filter-status', '')"
      class="w-full bg-white dark:bg-zinc-800 px-4 py-4 hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors text-left border-b border-amber-100 dark:border-zinc-700"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <QueueListIcon class="h-5 w-5 text-blue-400 mr-2" />
          <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Jobs</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ stats.total || 0 }}</span>
      </div>
    </button>

    <button
      @click="$emit('filter-status', 'pending')"
      class="w-full bg-white dark:bg-zinc-800 px-4 py-4 hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors text-left"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ClockIcon class="h-5 w-5 text-yellow-400 mr-2" />
          <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Pending Jobs</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ stats.pending }}</span>
      </div>
    </button>

    <button
      @click="$emit('filter-status', 'processing')"
      class="w-full bg-white dark:bg-zinc-800 px-4 py-4 hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors text-left"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ArrowPathIcon class="h-5 w-5 text-amber-400 mr-2 animate-spin" />
          <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Processing</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ stats.processing }}</span>
      </div>
    </button>

    <button
      @click="$emit('filter-status', 'completed')"
      class="w-full bg-white dark:bg-zinc-800 px-4 py-4 hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors text-left"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <CheckCircleIcon class="h-5 w-5 text-green-400 mr-2" />
          <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Completed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ stats.completed }}</span>
      </div>
    </button>

    <button
      @click="$emit('filter-status', 'failed')"
      class="w-full bg-white dark:bg-zinc-800 px-4 py-4 hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors text-left"
    >
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ExclamationTriangleIcon class="h-5 w-5 text-red-400 mr-2" />
          <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Failed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ stats.failed }}</span>
      </div>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ClockIcon, ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon, QueueListIcon } from '@heroicons/vue/24/outline';

interface Props {
  stats: {
    pending: number;
    processing: number;
    completed: number;
    failed: number;
    total?: number;
  }
}

defineProps<Props>();
defineEmits<{
  (e: 'filter-status', status: string): void;
}>();
</script> 
