<script setup>
import DocumentPreview from '@/Components/Domain/DocumentPreview.vue';
defineProps({ file: { type: Object, default: null } });
</script>
<template>
    <div class="h-full min-w-0 overflow-auto bg-zinc-50 dark:bg-zinc-950">
        <DocumentPreview v-if="file" :file="file" />
        <p v-else class="p-8 text-center text-sm text-zinc-500">Select a file to preview</p>
    </div>
</template>
