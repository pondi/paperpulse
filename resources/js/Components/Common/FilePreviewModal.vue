<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/Common/Modal.vue';
import FileInspector from '@/Components/Domain/FileInspector.vue';
import DocumentPreview from '@/Components/Domain/DocumentPreview.vue';
const props = defineProps({ show: Boolean, item: { type: Object, default: null } });
const emit = defineEmits(['close']);
const dirty = ref(false);
const fileId = computed(() => props.item?.file_id || props.item?.file?.id);
watch(() => props.show, () => { dirty.value = false; });
function close() { if (!dirty.value || confirm('Discard unsaved changes?')) emit('close'); }
</script>
<template>
    <Modal :show="show" max-width="6xl" @close="close" v-slot="{ titleId }">
        <div class="max-h-[85vh] overflow-auto">
            <div class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900"><h2 :id="titleId" class="truncate text-sm font-semibold">{{ item?.title || 'Document workspace' }}</h2><button class="workspace-button" aria-label="Close preview" @click="close">Close</button></div>
            <FileInspector v-if="show && fileId" :file-id="Number(fileId)" @dirty="dirty = $event" />
            <DocumentPreview v-else-if="item?.file" :file="item.file" />
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><Link v-if="fileId" :href="route('files.show', fileId)" class="workspace-button" @click="event => { if (dirty && !confirm('Discard unsaved changes?')) event.preventDefault(); else emit('close'); }">Open document workspace</Link></div>
        </div>
    </Modal>
</template>
