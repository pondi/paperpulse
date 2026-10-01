<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({ modelValue: { default: null }, excludeId: { default: null } });
const emit = defineEmits(['update:modelValue']);
const opened = ref(false);
const parent = ref(null);
const path = ref([]);
const folders = ref(null);
const loading = ref(false);
const error = ref('');
const selectedName = ref(props.modelValue ? 'Current folder' : 'Top level');
let requestVersion = 0;
const browse = async (parentId = null, page = 1) => {
    opened.value = true;
    loading.value = true;
    error.value = '';
    const version = ++requestVersion;
    try {
        const response = await axios.get(route('collections.folders'), { params: { parent_id: parentId, page } });
        if (version !== requestVersion) return;
        folders.value = response.data.folders;
        parent.value = response.data.parent;
        path.value = response.data.path;
    } catch {
        if (version === requestVersion) error.value = 'Folders could not be loaded. Please try again.';
    } finally {
        if (version === requestVersion) loading.value = false;
    }
};
const select = () => {
    emit('update:modelValue', parent.value?.id ?? null);
    selectedName.value = path.value.map(folder => folder.label).join(' / ') || 'Top level';
    opened.value = false;
};
</script>

<template>
    <div class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
        <div class="flex items-center gap-3">
            <span>Location: {{ modelValue === null ? 'Top level' : selectedName }}</span>
            <button type="button" class="text-blue-600 dark:text-blue-400" @click="browse()">Choose folder</button>
        </div>
        <div v-if="opened" class="space-y-3 rounded-md border border-zinc-300 dark:border-zinc-700 p-3">
            <p>{{ path.map(folder => folder.label).join(' / ') || 'Top level' }}</p>
            <div class="flex flex-wrap gap-3">
                <button v-if="parent" type="button" @click="browse(parent.parent_id)">Up one level</button>
                <button type="button" :disabled="loading || parent?.id === excludeId" @click="select">Use this location</button>
                <button type="button" @click="opened = false">Cancel</button>
            </div>
            <p v-if="loading" role="status">Loading folders…</p>
            <p v-else-if="error" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</p>
            <template v-else>
                <div class="flex flex-wrap gap-3">
                    <button v-for="folder in folders?.data.filter(folder => folder.id !== excludeId)" :key="folder.id" type="button" class="rounded-md bg-zinc-100 dark:bg-zinc-800 px-3 py-2" @click="browse(folder.id)">{{ folder.name }} →</button>
                </div>
                <p v-if="!folders?.data.length">No subfolders.</p>
                <div v-if="folders?.last_page > 1" class="flex gap-3">
                    <button type="button" :disabled="folders.current_page === 1" @click="browse(parent?.id, folders.current_page - 1)">Previous</button>
                    <span>Page {{ folders.current_page }} of {{ folders.last_page }}</span>
                    <button type="button" :disabled="folders.current_page === folders.last_page" @click="browse(parent?.id, folders.current_page + 1)">Next</button>
                </div>
            </template>
        </div>
    </div>
</template>
