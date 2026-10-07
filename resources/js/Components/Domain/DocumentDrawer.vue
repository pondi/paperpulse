<script setup>
import { computed } from 'vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
const props = defineProps({ document: { type: Object, default: null }, show: Boolean });
defineEmits(['close']);
const item = computed(() => props.document ? { ...props.document, title: props.document.title || props.document.file_name, file_id: props.document.file_id || props.document.file?.id } : null);
</script>
<template>
    <FilePreviewModal :show="show" :item="item" @close="$emit('close')" />
</template>
