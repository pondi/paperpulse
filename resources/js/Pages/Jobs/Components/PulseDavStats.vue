<template>
  <div class="bg-gray-800 shadow-sm rounded-lg p-6 mb-6 border border-gray-700">
    <h3 class="text-lg font-semibold text-white mb-4">PulseDav File Processing Status</h3>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <div class="bg-gray-700 rounded-lg p-4">
        <div class="text-sm font-medium text-gray-300">Total Files</div>
        <div class="mt-1 text-2xl font-semibold text-white">{{ stats.total }}</div>
      </div>
      <div class="bg-yellow-600 bg-opacity-20 rounded-lg p-4 border border-yellow-600">
        <div class="text-sm font-medium text-yellow-400">Pending</div>
        <div class="mt-1 text-2xl font-semibold text-yellow-300">{{ stats.pending }}</div>
      </div>
      <div class="bg-blue-600 bg-opacity-20 rounded-lg p-4 border border-blue-600">
        <div class="text-sm font-medium text-blue-400">Processing</div>
        <div class="mt-1 text-2xl font-semibold text-blue-300">{{ stats.processing }}</div>
      </div>
      <div class="bg-green-600 bg-opacity-20 rounded-lg p-4 border border-green-600">
        <div class="text-sm font-medium text-green-400">Completed</div>
        <div class="mt-1 text-2xl font-semibold text-green-300">{{ stats.completed }}</div>
      </div>
      <div class="bg-red-600 bg-opacity-20 rounded-lg p-4 border border-red-600">
        <div class="text-sm font-medium text-red-400">Failed</div>
        <div class="mt-1 text-2xl font-semibold text-red-300">{{ stats.failed }}</div>
      </div>
    </div>

    <!-- Recent Files -->
    <div v-if="recentFiles.length > 0">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Recent Scanner Files</h4>
      <div class="space-y-2">
        <div v-for="file in recentFiles" :key="file.id" 
             class="flex items-center justify-between p-3 bg-gray-700 rounded-lg">
          <div class="flex items-center space-x-3">
            <div :class="getStatusIconClass(file.status)">
              <component :is="getStatusIcon(file.status)" class="h-5 w-5" />
            </div>
            <div>
              <div class="text-sm font-medium text-white">{{ file.filename }}</div>
              <div class="text-xs text-gray-400">
                {{ file.status === 'completed' && file.processed_at 
                  ? formatDate(file.processed_at) 
                  : formatDate(file.uploaded_at) }}
              </div>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <span class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getStatusClass(file.status)">
              {{ file.status }}
            </span>
            <Link v-if="file.receipt_id" 
                  :href="route('receipts.show', file.receipt_id)"
                  class="text-indigo-400 hover:text-indigo-300 text-sm">
              View
            </Link>
          </div>
        </div>
      </div>
      
      <!-- View All Link -->
      <div class="mt-4 text-center">
        <Link :href="route('pulsedav.index')" 
              class="text-sm text-indigo-400 hover:text-indigo-300">
          View all scanner files →
        </Link>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-else class="text-center py-4 text-gray-400">
      No scanner files uploaded yet
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { CheckCircleIcon, XCircleIcon, ClockIcon, CogIcon, DocumentIcon } from '@heroicons/vue/24/solid';

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

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusIcon = (status) => {
  const icons = {
    pending: ClockIcon,
    processing: CogIcon,
    completed: CheckCircleIcon,
    failed: XCircleIcon,
  };
  return icons[status] || DocumentIcon;
};

const getStatusIconClass = (status) => {
  const classes = {
    pending: 'text-yellow-400',
    processing: 'text-blue-400 animate-spin',
    completed: 'text-green-400',
    failed: 'text-red-400',
  };
  return classes[status] || 'text-gray-400';
};

const getStatusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-900 text-yellow-300',
    processing: 'bg-blue-900 text-blue-300',
    completed: 'bg-green-900 text-green-300',
    failed: 'bg-red-900 text-red-300',
  };
  return classes[status] || 'bg-gray-700 text-gray-300';
};
</script>