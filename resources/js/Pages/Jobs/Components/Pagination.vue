<template>
  <div class="mt-4 flex justify-center gap-2">
    <button 
      v-for="pageNum in pagination.last_page" 
      :key="pageNum"
      @click="$emit('update:page', pageNum)"
      :class="{
        'px-4 py-2 rounded-md border': true,
        'bg-zinc-900 dark:bg-amber-600 text-white hover:bg-zinc-800 dark:hover:bg-amber-700 border-amber-600': pageNum === pagination.current_page,
        'bg-white text-zinc-700 hover:bg-amber-50 border-zinc-300 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-amber-600 dark:border-zinc-600': pageNum !== pagination.current_page
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
