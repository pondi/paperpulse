<template>
  <div
    aria-live="assertive"
    class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6 z-50"
  >
    <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
      <TransitionGroup
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5"
        >
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <CheckCircleIcon
                  v-if="toast.type === 'success'"
                  class="h-6 w-6 text-green-400"
                  aria-hidden="true"
                />
                <XCircleIcon
                  v-else-if="toast.type === 'error'"
                  class="h-6 w-6 text-red-400"
                  aria-hidden="true"
                />
                <ExclamationTriangleIcon
                  v-else-if="toast.type === 'warning'"
                  class="h-6 w-6 text-yellow-400"
                  aria-hidden="true"
                />
                <InformationCircleIcon
                  v-else-if="toast.type === 'info'"
                  class="h-6 w-6 text-blue-400"
                  aria-hidden="true"
                />
              </div>
              <div class="ml-3 w-0 flex-1 pt-0.5">
                <p
                  class="text-sm font-medium"
                  :class="{
                    'text-gray-900 dark:text-gray-100': toast.type === 'success' || toast.type === 'info',
                    'text-red-900 dark:text-red-100': toast.type === 'error',
                    'text-yellow-900 dark:text-yellow-100': toast.type === 'warning',
                  }"
                >
                  {{ toast.message }}
                </p>
              </div>
              <div class="ml-4 flex flex-shrink-0">
                <button
                  type="button"
                  @click="removeToast(toast.id)"
                  class="inline-flex rounded-md bg-white dark:bg-gray-800 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                  <span class="sr-only">Close</span>
                  <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';

const page = usePage();
const toasts = ref([]);
let nextId = 0;

const addToast = (type, message) => {
  const id = nextId++;
  toasts.value.push({ id, type, message });

  // Auto-remove toast after 5 seconds
  setTimeout(() => {
    removeToast(id);
  }, 5000);
};

const removeToast = (id) => {
  const index = toasts.value.findIndex((toast) => toast.id === id);
  if (index !== -1) {
    toasts.value.splice(index, 1);
  }
};

// Watch for flash messages from Inertia
watch(
  () => page.props.flash,
  (flash) => {
    if (flash.success) {
      addToast('success', flash.success);
    }
    if (flash.error) {
      addToast('error', flash.error);
    }
    if (flash.warning) {
      addToast('warning', flash.warning);
    }
    if (flash.info) {
      addToast('info', flash.info);
    }
  },
  { deep: true, immediate: true }
);
</script>
