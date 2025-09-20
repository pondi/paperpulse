<template>
  <div class="space-y-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">PulseDav Statistics</h3>
    
    <!-- Statistics in vertical layout matching JobStats -->
    <div class="bg-white dark:bg-gray-800 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <DocumentIcon class="h-5 w-5 text-gray-400 mr-2" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Files</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ stats.total }}</span>
      </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <ClockIcon class="h-5 w-5 text-yellow-400 mr-2" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ stats.pending }}</span>
      </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <CogIcon class="h-5 w-5 text-blue-400 mr-2 animate-spin" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Processing</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ stats.processing }}</span>
      </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <CheckCircleIcon class="h-5 w-5 text-green-400 mr-2" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Completed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ stats.completed }}</span>
      </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <XCircleIcon class="h-5 w-5 text-red-400 mr-2" />
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Failed</p>
        </div>
        <span class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ stats.failed }}</span>
      </div>
    </div>

    <!-- View All Link -->
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-700">
      <Link :href="route('pulsedav.index')" 
            class="text-sm text-indigo-400 hover:text-indigo-300 flex items-center justify-center">
        View all scanner files
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { CheckCircleIcon, XCircleIcon, ClockIcon, CogIcon, DocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      pending: 0,
      processing: 0,
      completed: 0,
      failed: 0
    })
  },
  recentFiles: {
    type: Array,
    default: () => []
  }
});
</script>
