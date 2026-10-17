<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import WorkspaceDrawer from '@/Components/Common/WorkspaceDrawer.vue';
import { ArrowLeftIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
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
    <WorkspaceDrawer :show="show" :title="item?.title || 'Document preview'" @close="close">
        <FileInspector v-if="show && fileId" :file-id="Number(fileId)" framed @dirty="dirty = $event">
            <template #leading><button class="workspace-button shrink-0" aria-label="Close preview" @click="close"><ArrowLeftIcon class="h-4 w-4" aria-hidden="true" /><span class="hidden sm:inline">Back</span></button></template>
            <template #actions><Link :href="route('files.show', fileId)" class="workspace-button shrink-0" aria-label="Open document workspace" @click="event => { if (dirty && !confirm('Discard unsaved changes?')) event.preventDefault(); else emit('close'); }"><ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" /></Link></template>
        </FileInspector>
        <template v-else>
            <header class="flex min-h-[48px] shrink-0 items-center gap-3 border-b border-zinc-200 px-3 py-1.5 dark:border-zinc-800"><button class="workspace-button" aria-label="Close preview" @click="close"><ArrowLeftIcon class="h-4 w-4" /></button><h2 class="truncate text-sm font-semibold">{{ item?.title || 'Document preview' }}</h2></header>
            <DocumentPreview v-if="item?.file" :file="item.file" framed class="min-h-0 flex-1" />
            <p v-else class="p-4 text-sm text-zinc-500">No preview available for this item.</p>
        </template>
    </WorkspaceDrawer>
</template>
