<template>
  <div class="mt-4 flex justify-center gap-2">
    <button 
      v-for="pageNum in pagination.last_page" 
      :key="pageNum"
      @click="$emit('update:page', pageNum)"
      :class="{
        'px-4 py-2 rounded-md border': true,
        'bg-indigo-600 text-white hover:bg-indigo-500 border-indigo-600': pageNum === pagination.current_page,
        'bg-white text-gray-700 hover:bg-gray-50 border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 dark:border-gray-600': pageNum !== pagination.current_page
      }"
    >
      {{ pageNum }}
    </button>
  </div>
</template>

<script setup lang="ts">
interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Props {
  pagination: Pagination;
  page: number;
}

defineProps<Props>();
defineEmits<{
  (e: 'update:page', page: number): void;
}>();
</script> 
