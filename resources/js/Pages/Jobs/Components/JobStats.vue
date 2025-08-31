<template>
  <div class="space-y-1 bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <h3 class="text-lg font-semibold text-white px-4 py-3 bg-gray-900/50 border-b border-gray-700">Job Statistics</h3>
    
    <div class="bg-gray-800 px-4 py-4 hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ClockIcon class="h-5 w-5 text-yellow-400 mr-2" />
          <p class="text-sm font-medium text-gray-400">Pending Jobs</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-white">{{ stats.pending }}</span>
      </div>
    </div>
    
    <div class="bg-gray-800 px-4 py-4 hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ArrowPathIcon class="h-5 w-5 text-blue-400 mr-2 animate-spin" />
          <p class="text-sm font-medium text-gray-400">Processing</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-white">{{ stats.processing }}</span>
      </div>
    </div>
    
    <div class="bg-gray-800 px-4 py-4 hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <CheckCircleIcon class="h-5 w-5 text-green-400 mr-2" />
          <p class="text-sm font-medium text-gray-400">Completed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-white">{{ stats.completed }}</span>
      </div>
    </div>
    
    <div class="bg-gray-800 px-4 py-4 hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ExclamationTriangleIcon class="h-5 w-5 text-red-400 mr-2" />
          <p class="text-sm font-medium text-gray-400">Failed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-white">{{ stats.failed }}</span>
      </div>
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