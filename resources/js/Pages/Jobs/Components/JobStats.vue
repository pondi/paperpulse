<template>
  <div class="grid grid-cols-1 gap-px bg-white/5 sm:grid-cols-2 lg:grid-cols-4 mb-8 rounded-lg overflow-hidden">
    <div class="bg-gray-900 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex items-center">
        <ClockIcon class="h-5 w-5 text-yellow-400 mr-2" />
        <p class="text-sm font-medium text-gray-400">Pending Jobs</p>
      </div>
      <p class="mt-2 flex items-baseline gap-x-2">
        <span class="text-4xl font-semibold tracking-tight text-white">{{ stats.pending }}</span>
      </p>
    </div>
    <div class="bg-gray-900 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex items-center">
        <ArrowPathIcon class="h-5 w-5 text-blue-400 mr-2 animate-spin" />
        <p class="text-sm font-medium text-gray-400">Processing</p>
      </div>
      <p class="mt-2 flex items-baseline gap-x-2">
        <span class="text-4xl font-semibold tracking-tight text-white">{{ stats.processing }}</span>
      </p>
    </div>
    <div class="bg-gray-900 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex items-center">
        <CheckCircleIcon class="h-5 w-5 text-green-400 mr-2" />
        <p class="text-sm font-medium text-gray-400">Completed</p>
      </div>
      <p class="mt-2 flex items-baseline gap-x-2">
        <span class="text-4xl font-semibold tracking-tight text-white">{{ stats.completed }}</span>
      </p>
    </div>
    <div class="bg-gray-900 px-4 py-6 sm:px-6 lg:px-8">
      <div class="flex items-center">
        <ExclamationTriangleIcon class="h-5 w-5 text-red-400 mr-2" />
        <p class="text-sm font-medium text-gray-400">Failed</p>
      </div>
      <p class="mt-2 flex items-baseline gap-x-2">
        <span class="text-4xl font-semibold tracking-tight text-white">{{ stats.failed }}</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ClockIcon, ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

interface Props {
  stats: {
    pending: number;
    processing: number;
    completed: number;
    failed: number;
  }
}

defineProps<Props>();
</script> 