<template>
  <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6 border border-gray-200 dark:border-gray-700">
    <div class="flex gap-4">
      <select 
        :value="form.status"
        @input="$emit('update:form', { ...form, status: ($event.target as HTMLSelectElement).value })"
        class="bg-white text-gray-900 dark:bg-gray-700 dark:text-white rounded-md border border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
      >
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="processing">Processing</option>
        <option value="completed">Completed</option>
        <option value="failed">Failed</option>
      </select>

      <select 
        :value="form.queue"
        @input="$emit('update:form', { ...form, queue: ($event.target as HTMLSelectElement).value })"
        class="bg-white text-gray-900 dark:bg-gray-700 dark:text-white rounded-md border border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50"
      >
        <option value="">All Queues</option>
        <option v-for="queue in queues" :key="queue" :value="queue">
          {{ queue }}
        </option>
      </select>

      <input 
        :value="form.search"
        @input="$emit('update:form', { ...form, search: ($event.target as HTMLInputElement).value })"
        type="text" 
        placeholder="Search jobs..." 
        class="bg-white text-gray-900 dark:bg-gray-700 dark:text-white rounded-md border border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 focus:ring-opacity-50 placeholder-gray-400"
      >
    </div>
  </div>
</template>

<script setup lang="ts">
interface Form {
  status: string;
  queue: string;
  search: string;
  page: number;
}

interface Props {
  form: Form;
  queues: string[];
}

defineProps<Props>();
defineEmits<{
  (e: 'update:form', form: Form): void;
}>();
</script> 
