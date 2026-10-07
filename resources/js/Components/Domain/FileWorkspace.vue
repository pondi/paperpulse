<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import FileInspector from '@/Components/Domain/FileInspector.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ files: { type: Array, default: () => [] }, tags: { type: Array, default: () => [] }, loading: Boolean });
const { formatDate, formatCurrency } = useDateFormatter();
const activeId = ref(null);
const selected = ref([]);
const tagId = ref('');
const busy = ref(false);
const error = ref('');
const dirty = ref(false);
const inspectorKey = ref(0);
const selectable = computed(() => props.files.filter(file => file.can_edit));
const allSelected = computed(() => selectable.value.length > 0 && selectable.value.every(file => selected.value.includes(file.id)));
const activeIndex = computed(() => props.files.findIndex(file => file.id === activeId.value));
const selectedFiles = computed(() => props.files.filter(file => selected.value.includes(file.id)));
const canApprove = computed(() => selectedFiles.value.length && selectedFiles.value.every(file => file.status === 'completed' || (file.status === 'needs_review' && file.review?.reason === 'receipt_totals')));
function status(file) {
    if (['failed', 'needs_review'].includes(file.status)) return 'Flagged';
    return file.review_status === 'approved' ? 'Approved' : file.review_status === 'flagged' ? 'Flagged' : 'Pending';
}
function open(file) {
    if (file.id === activeId.value) return;
    if (dirty.value && !confirm('Discard unsaved changes and open another file?')) return;
    dirty.value = false;
    activeId.value = file.id;
}
function close() {
    if (dirty.value && !confirm('Discard unsaved changes?')) return;
    dirty.value = false;
    activeId.value = null;
}
function move(offset) { const file = props.files[activeIndex.value + offset]; if (file) open(file); }
function toggleAll() { selected.value = allSelected.value ? [] : selectable.value.map(file => file.id); }
watch(() => props.files, files => {
    selected.value = selected.value.filter(id => files.some(file => file.id === id && file.can_edit));
    if (activeId.value && !files.some(file => file.id === activeId.value)) { activeId.value = null; dirty.value = false; }
});
function action(actionName) {
    if (dirty.value) { error.value = 'Save or discard the open file’s changes first.'; return; }
    if (actionName === 'delete' && !confirm(`Delete ${selected.value.length} selected files and their extracted records?`)) return;
    if (actionName === 'approve' && !confirm(`Confirm that you checked all ${selected.value.length} selected files against their originals.`)) return;
    busy.value = true;
    error.value = '';
    router.post(route('workspace.actions'), { action: actionName, file_ids: selected.value, ...(actionName === 'tag' ? { tag_id: tagId.value } : {}) }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; inspectorKey.value++; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { busy.value = false; },
    });
}
function exportSelected() {
    const cell = value => {
        let text = String(value ?? '');
        if (/^[=+@\-\t\r]/.test(text)) text = "'" + text;
        return '"' + text.replaceAll('"', '""') + '"';
    };
    const rows = [['File', 'Upload date', 'Vendor', 'Amount', 'Tax', 'Currency', 'Status', 'AI confidence'], ...selectedFiles.value.map(file => [file.name, file.uploaded_at, file.identity?.title, file.identity?.amount, file.identity?.tax, file.identity?.currency, status(file), file.confidence])];
    const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a'); link.href = url; link.download = 'paperpulse-selected-files.csv'; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function keyboard(event) {
    if (event.target.closest('input, select, textarea, [contenteditable="true"]')) return;
    if (event.key === 'Escape') close();
}
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
let removeBefore;
onMounted(() => {
    window.addEventListener('keydown', keyboard);
    window.addEventListener('beforeunload', beforeUnload);
    removeBefore = router.on('before', event => {
        if (dirty.value && !busy.value && event.detail.visit.method === 'get' && !confirm('Discard unsaved changes and leave this file?')) event.preventDefault();
    });
});
onBeforeUnmount(() => { window.removeEventListener('keydown', keyboard); window.removeEventListener('beforeunload', beforeUnload); removeBefore?.(); });
</script>

<template>
    <section class="min-w-0 border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="Document workspace" :aria-busy="loading || busy">
        <div class="flex min-h-12 flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
            <template v-if="selected.length">
                <span class="mr-2 text-xs font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ selected.length }} selected</span>
                <button class="workspace-primary" :disabled="busy || !canApprove" @click="action('approve')" title="Approve completed files or reconciled receipt reviews">Approve</button>
                <button class="workspace-button" :disabled="busy" @click="exportSelected">Export CSV</button>
                <select v-model="tagId" aria-label="Tag selected files" class="rounded border-zinc-300 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"><option value="">Choose tag</option><option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select>
                <button class="workspace-button" :disabled="busy || !tagId" @click="action('tag')">Apply tag</button>
                <button class="workspace-button text-red-700 dark:text-red-400" :disabled="busy" @click="action('delete')">Delete</button>
                <button class="ml-auto text-xs text-zinc-500" @click="selected = []">Clear selection</button>
            </template>
            <template v-else><span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ files.length }} files on this page</span><span class="ml-auto text-xs text-zinc-500">Select a row to review · ↑ ↓ navigate · Esc close</span></template>
        </div>
        <p v-if="error" role="alert" class="border-b border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ error }}</p>
        <div class="grid min-w-0 items-start" :class="activeId ? 'xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' : ''">
            <div class="min-w-0 overflow-auto" :class="activeId ? 'max-h-[72vh]' : ''">
                <table class="w-full whitespace-nowrap text-left text-xs tabular-nums">
                    <caption class="sr-only">Documents with upload date, vendor, amount, tax, review status and extraction confidence</caption>
                    <thead class="sticky top-0 z-10 border-b border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400"><tr>
                        <th class="w-9 px-3 py-3"><input type="checkbox" aria-label="Select all editable files on this page" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" :disabled="!selectable.length" @change="toggleAll" /></th>
                        <th class="px-3 py-3 font-medium">Document / Vendor</th><th class="px-3 py-3 font-medium">Upload date</th><th class="px-3 py-3 text-right font-medium">Amount</th><th class="px-3 py-3 text-right font-medium">Tax</th><th class="px-3 py-3 font-medium">Status</th><th class="px-3 py-3 text-right font-medium">AI confidence</th>
                    </tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="(file, index) in files" :key="file.id" :class="activeId === file.id ? 'bg-orange-50/60 dark:bg-orange-950/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'" class="cursor-pointer" @click="open(file)">
                            <td class="px-3 py-3" @click.stop><input v-model="selected" :value="file.id" type="checkbox" :disabled="!file.can_edit || busy" :aria-label="'Select ' + file.name" /></td>
                            <td class="max-w-[240px] px-3 py-2"><button :dusk="'library-file-' + file.id" :aria-pressed="activeId === file.id" class="block w-full truncate text-left text-sm font-medium text-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-600 dark:text-zinc-100" @click.stop="open(file)" @keydown.down.prevent="open(files[Math.min(index + 1, files.length - 1)]); $event.currentTarget.closest('tr').nextElementSibling?.querySelector('button')?.focus()" @keydown.up.prevent="open(files[Math.max(index - 1, 0)]); $event.currentTarget.closest('tr').previousElementSibling?.querySelector('button')?.focus()">{{ file.identity?.title || file.name || file.fileName }}</button><p class="mt-0.5 truncate text-[11px] text-zinc-500">{{ file.identity?.title ? file.name : file.file_type }}<span v-if="file.is_shared"> · Shared</span></p></td>
                            <td class="px-3 py-3 text-zinc-500">{{ formatDate(file.uploaded_at || file.created_at) }}</td>
                            <td class="px-3 py-3 text-right font-medium text-zinc-700 dark:text-zinc-200">{{ file.identity?.amount == null ? '—' : formatCurrency(file.identity.amount, file.identity.currency) }}</td>
                            <td class="px-3 py-3 text-right text-zinc-500">{{ file.identity?.tax == null ? '—' : formatCurrency(file.identity.tax, file.identity.currency) }}</td>
                            <td class="px-3 py-3"><span :class="status(file) === 'Flagged' ? 'text-orange-700 dark:text-orange-300' : 'text-zinc-600 dark:text-zinc-300'" class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" :class="status(file) === 'Flagged' ? 'bg-orange-600' : status(file) === 'Approved' ? 'bg-emerald-600' : 'bg-zinc-400'" />{{ status(file) }}</span><span v-if="['pending', 'processing', 'failed'].includes(file.status)" class="mt-0.5 block text-[10px] capitalize text-zinc-500">{{ file.status === 'pending' ? 'Queued' : file.status }}</span></td>
                            <td class="px-3 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ file.confidence == null ? '—' : Math.round(Number(file.confidence) * 100) + '%' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!files.length" class="px-6 py-16 text-center"><h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">No documents here</h3><p class="mt-2 text-xs text-zinc-500">Upload a file or adjust your filters to get started.</p></div>
            </div>
            <aside v-if="activeId" class="min-w-0 border-t border-zinc-200 xl:sticky xl:top-16 xl:max-h-[80vh] xl:overflow-y-auto xl:border-l xl:border-t-0 dark:border-zinc-800" aria-label="Selected document">
                <div class="sticky top-0 z-20 flex items-center gap-2 border-b border-zinc-200 bg-white px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900"><span class="mr-auto text-xs font-semibold text-zinc-600 dark:text-zinc-300">Preview & edit · {{ activeIndex + 1 }} / {{ files.length }}</span><button class="workspace-button" :disabled="activeIndex <= 0" aria-label="Previous document" @click="move(-1)">↑</button><button class="workspace-button" :disabled="activeIndex >= files.length - 1" aria-label="Next document" @click="move(1)">↓</button><button class="workspace-button" aria-label="Close preview" @click="close">✕</button></div>
                <FileInspector :key="activeId + '-' + inspectorKey" :file-id="activeId" @dirty="dirty = $event" />
            </aside>
        </div>
    </section>
</template>

<style scoped>
input[type='checkbox'] { @apply rounded-sm border-zinc-300 text-orange-600 focus:ring-orange-600 disabled:opacity-30 dark:border-zinc-600 dark:bg-zinc-800; }
</style>
