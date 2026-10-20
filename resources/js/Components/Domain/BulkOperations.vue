<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Common/Modal.vue';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';

const props = defineProps({ selectedCount: { type: Number, required: true }, selectedIds: { type: Array, required: true }, categories: { type: Array, default: () => [] } });
const emit = defineEmits(['clear-selection']);
const modal = ref(null);
const categoryId = ref('');
const busy = ref(false);
const error = ref('');
const exportMessage = ref('');
function open(action) { error.value = ''; modal.value = action; }
function submit() {
    if (busy.value || (modal.value === 'categorize' && !categoryId.value)) return;
    busy.value = true;
    error.value = '';
    router.post(route(`bulk.receipts.${modal.value}`), { receipt_ids: props.selectedIds, ...(modal.value === 'categorize' ? { category_id: categoryId.value } : {}) }, {
        preserveScroll: true,
        onSuccess: () => { modal.value = null; categoryId.value = ''; emit('clear-selection'); },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { busy.value = false; },
    });
}
async function exportSelected(format) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    exportMessage.value = '';
    try {
        const response = await axios.post(route(`bulk.receipts.export.${format}`), { receipt_ids: props.selectedIds }, { responseType: 'blob', headers: { Accept: 'application/json' } });
        if (response.status === 202) {
            router.visit(route('exports.index'));
        } else {
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = `paperpulse-receipts.${format}`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            exportMessage.value = `${format.toUpperCase()} download started.`;
        }
    } catch (failure) {
        try {
            const data = JSON.parse(await failure.response.data.text());
            error.value = Object.values(data.errors || {}).flat().join(' ') || data.message;
        } catch { error.value = 'Export could not be downloaded. Please try again.'; }
    } finally { busy.value = false; }
}
</script>

<template>
    <div>
        <div v-if="selectedCount" class="workspace-surface flex flex-wrap items-center gap-2 p-3" role="region" aria-label="Selected receipt actions" :aria-busy="busy">
            <span class="mr-auto text-sm font-medium" role="status">{{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }} selected</span>
            <button type="button" class="workspace-button" :disabled="busy" @click="open('categorize')">Categorize</button>
            <Menu as="div" class="relative">
                <MenuButton class="workspace-button" :disabled="busy">Export</MenuButton>
                <MenuItems class="absolute right-0 z-20 mt-1 w-44 rounded-md border border-zinc-200 bg-white p-1 text-sm shadow-lg focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-900">
                    <MenuItem v-for="format in ['csv', 'pdf']" :key="format" v-slot="{ active }"><button type="button" class="min-h-11 w-full rounded-sm px-3 py-2 text-left" :class="active ? 'bg-zinc-100 dark:bg-zinc-800' : ''" @click="exportSelected(format)">Export as {{ format.toUpperCase() }}</button></MenuItem>
                </MenuItems>
            </Menu>
            <button type="button" class="workspace-button text-red-700! dark:text-red-400!" :disabled="busy" @click="open('delete')">Delete</button>
            <button type="button" class="workspace-button" :disabled="busy" @click="emit('clear-selection')">Clear selection</button>
            <p v-if="error && !modal" role="alert" class="w-full text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <p v-if="exportMessage" role="status" class="w-full text-sm text-zinc-500">{{ exportMessage }}</p>
        </div>
        <Modal :show="!!modal" :closeable="!busy" max-width="md" @close="modal = null" v-slot="{ titleId }">
            <form class="flex flex-col gap-4 p-5 sm:p-6" @submit.prevent="submit">
                <h2 :id="titleId" class="section-heading">{{ modal === 'delete' ? 'Delete' : 'Categorize' }} {{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }}</h2>
                <p v-if="modal === 'delete'" class="text-sm text-zinc-500 dark:text-zinc-400">Delete the selected receipts and their original files? This action cannot be undone.</p>
                <template v-else>
                    <label for="bulk-receipt-category" class="field-label">Category</label>
                    <select id="bulk-receipt-category" v-model="categoryId" class="workspace-filter w-full"><option value="">Select a category</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                    <p v-if="!categories.length" class="text-sm text-zinc-500">Create a category in <Link :href="route('categories.index')" class="underline">Categories</Link> before categorizing receipts.</p>
                </template>
                <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="workspace-button" :disabled="busy" @click="modal = null">Cancel</button>
                    <button type="submit" :class="modal === 'delete' ? 'workspace-danger' : 'workspace-primary'" :disabled="busy || (modal === 'categorize' && !categoryId)">{{ busy ? 'Working…' : modal === 'delete' ? 'Delete' : 'Apply Category' }}</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
