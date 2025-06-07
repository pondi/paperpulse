<template>
  <div class="mt-4 flex justify-center gap-2">
    <button 
      v-for="pageNum in pagination.last_page" 
      :key="pageNum"
      @click="$emit('update:page', pageNum)"
      :class="{
        'px-4 py-2 rounded-md': true,
        'bg-indigo-600 text-white hover:bg-indigo-500': pageNum === pagination.current_page,
        'bg-gray-700 text-gray-300 hover:bg-gray-600': pageNum !== pagination.current_page
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