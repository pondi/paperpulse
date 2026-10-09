<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { DocumentTextIcon, ChevronUpIcon, ChevronDownIcon, XMarkIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { fileStatusLabel } from '@/utils/fileStatus';
import FileInspector from '@/Components/Domain/FileInspector.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ files: { type: Array, default: () => [] }, tags: { type: Array, default: () => [] }, loading: Boolean, display: { type: String, default: 'list' }, reviewMode: Boolean, emptyTitle: { type: String, default: 'No documents here' }, emptyDescription: { type: String, default: 'Upload a file or adjust your filters to get started.' } });
const { formatDate, formatCurrency } = useDateFormatter();
const page = usePage();
const workspace = ref(null);
const previewHeading = ref(null);
const showReviewDetails = ref(props.reviewMode);
watch(() => props.reviewMode, value => { showReviewDetails.value = value; });
const hasAmounts = computed(() => props.files.some(file => file.identity?.amount != null));
const returnTo = computed(() => /^\/(library|search)(\?|$)/.test(page.url) ? page.url : undefined);
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
function open(file, focusPreview = false) {
    if (file.id === activeId.value) return true;
    if (dirty.value && !confirm('Discard unsaved changes and open another file?')) return false;
    dirty.value = false;
    activeId.value = file.id;
    if (focusPreview && window.innerWidth < 1280) nextTick(() => { previewHeading.value?.focus(); previewHeading.value?.scrollIntoView({ block: 'start' }); });
    return true;
}
function moveFromRow(index, offset) {
    const file = props.files[index + offset];
    if (file && open(file)) nextTick(() => workspace.value?.querySelector(`[dusk="library-file-${file.id}"]`)?.focus());
}
function close() {
    if (dirty.value && !confirm('Discard unsaved changes?')) return;
    dirty.value = false;
    const previousId = activeId.value;
    activeId.value = null;
    nextTick(() => workspace.value?.querySelector(`[dusk="library-file-${previousId}"]`)?.focus());
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
    <section ref="workspace" class="workspace-surface overflow-hidden" aria-label="Document workspace" :aria-busy="loading || busy">
        <div class="flex min-h-14 flex-wrap items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <template v-if="selected.length">
                <span class="mr-2 text-sm font-medium tabular-nums" role="status">{{ selected.length }} selected</span>
                <button class="workspace-primary" :disabled="busy || !canApprove" @click="action('approve')" title="Approve completed files or reconciled receipt reviews">Approve</button>
                <button class="workspace-button" :disabled="busy" @click="exportSelected">Export CSV</button>
                <select v-model="tagId" aria-label="Tag selected files" class="workspace-filter max-w-[180px]"><option value="">Choose tag</option><option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select>
                <button class="workspace-button" :disabled="busy || !tagId" @click="action('tag')">Apply tag</button>
                <button class="workspace-button !text-red-700 dark:!text-red-400" :disabled="busy" @click="action('delete')">Delete</button>
                <button class="ml-auto rounded-md px-2 py-2 text-sm text-zinc-500" :disabled="busy" @click="selected = []">Clear selection</button>
            </template>
            <template v-else>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Open a document to preview its contents.</p>
                <button v-if="display === 'list' && !activeId" type="button" class="ml-auto rounded-md px-2 py-1 text-sm font-medium text-zinc-600 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800" :aria-pressed="showReviewDetails" @click="showReviewDetails = !showReviewDetails">{{ showReviewDetails ? 'Hide review columns' : 'Show review columns' }}</button>
            </template>
        </div>
        <p v-if="error" role="alert" class="border-b border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{{ error }}</p>
        <div class="grid min-w-0 items-start" :class="activeId ? 'xl:grid-cols-[minmax(240px,1fr)_minmax(0,2.6fr)]' : ''">
            <div class="min-w-0 overflow-auto" :class="activeId ? 'hidden xl:block xl:max-h-[78vh]' : ''" tabindex="0" role="region" aria-label="Files, scroll to see additional columns">
                <table v-if="display === 'list' || activeId" class="w-full text-left text-sm">
                    <caption class="sr-only">Documents with upload date, amount and processing status. Enable review columns for tax and extraction confidence.</caption>
                    <thead class="sticky top-0 z-10 border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400"><tr>
                        <th scope="col" class="w-10 px-3 py-3"><input type="checkbox" aria-label="Select all editable files on this page" :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" :disabled="!selectable.length || busy" @change="toggleAll" /></th>
                        <th scope="col" class="px-2 py-3 font-medium">Document</th>
                        <template v-if="!activeId">
                            <th scope="col" class="hidden whitespace-nowrap px-4 py-3 font-medium md:table-cell">Uploaded</th>
                            <th v-if="hasAmounts" scope="col" class="px-3 py-3 text-right font-medium">Amount</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium sm:table-cell">Status</th>
                            <th v-if="showReviewDetails" scope="col" class="px-4 py-3 text-right font-medium">Tax</th>
                            <th v-if="showReviewDetails" scope="col" class="whitespace-nowrap px-4 py-3 text-right font-medium">AI confidence</th>
                        </template>
                    </tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="(file, index) in files" :key="file.id" :class="activeId === file.id ? 'bg-orange-50 dark:bg-orange-950/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50'" class="cursor-pointer" @click="open(file, true)">
                            <td class="px-3 py-4" @click.stop><input v-model="selected" :value="file.id" type="checkbox" :disabled="!file.can_edit || busy" :aria-label="'Select ' + file.name" /></td>
                            <td class="max-w-[160px] px-2 py-4 sm:max-w-[280px]">
                                <div class="flex min-w-0 items-center gap-3"><DocumentTextIcon class="hidden h-6 w-6 shrink-0 text-zinc-400 sm:block" aria-hidden="true" /><div class="min-w-0 flex-1">
                                    <button :dusk="'library-file-' + file.id" :aria-pressed="activeId === file.id" :title="file.original_name || file.name" class="block w-full truncate text-left text-sm font-medium text-zinc-800 dark:text-zinc-100" @click.stop="open(file, true)" @keydown.down.prevent="moveFromRow(index, 1)" @keydown.up.prevent="moveFromRow(index, -1)">{{ file.identity?.title || file.name || file.fileName }}</button>
                                    <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ file.original_name || file.name }}<span v-if="file.is_shared"> · Shared</span></p>
                                    <p :class="activeId ? '' : 'sm:hidden'" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ fileStatusLabel(file) }}<span v-if="!activeId"> · {{ formatDate(file.uploaded_at || file.created_at) }}</span></p>
                                </div></div>
                            </td>
                            <template v-if="!activeId">
                                <td class="hidden whitespace-nowrap px-4 py-4 text-xs text-zinc-500 dark:text-zinc-400 md:table-cell">{{ formatDate(file.uploaded_at || file.created_at) }}</td>
                                <td v-if="hasAmounts" class="px-3 py-4 text-right text-xs font-medium tabular-nums text-zinc-700 sm:text-sm dark:text-zinc-200">{{ file.identity?.amount == null ? '—' : formatCurrency(file.identity.amount, file.identity.currency) }}</td>
                                <td class="hidden px-4 py-4 text-xs sm:table-cell"><span class="inline-flex items-center gap-2 whitespace-nowrap" :class="['failed', 'needs_review'].includes(file.status) ? 'text-orange-700 dark:text-orange-300' : 'text-zinc-600 dark:text-zinc-300'"><span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="['failed', 'needs_review'].includes(file.status) ? 'bg-orange-600' : file.status === 'completed' ? 'bg-emerald-600' : 'bg-zinc-400'" />{{ fileStatusLabel(file) }}</span><span v-if="file.review_status" class="mt-1 block capitalize text-zinc-500">{{ file.review_status }}</span></td>
                                <td v-if="showReviewDetails" class="whitespace-nowrap px-4 py-4 text-right text-xs tabular-nums text-zinc-500">{{ file.identity?.tax == null ? '—' : formatCurrency(file.identity.tax, file.identity.currency) }}</td>
                                <td v-if="showReviewDetails" class="px-4 py-4 text-right text-xs tabular-nums text-zinc-500">{{ file.confidence == null ? '—' : Math.round(Number(file.confidence) * 100) + '%' }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
                <ul v-else class="grid gap-px bg-zinc-200 sm:grid-cols-2 2xl:grid-cols-3 dark:bg-zinc-800">
                    <li v-for="file in files" :key="file.id" class="relative flex min-w-0 flex-col gap-3 bg-white p-5 dark:bg-zinc-900">
                        <div class="flex items-center justify-between"><DocumentTextIcon class="h-8 w-8 text-zinc-400" aria-hidden="true" /><input v-model="selected" :value="file.id" type="checkbox" :disabled="!file.can_edit || busy" :aria-label="'Select ' + file.name" /></div>
                        <button :dusk="'library-file-' + file.id" class="truncate text-left text-sm font-medium" :title="file.original_name || file.name" @click="open(file, true)">{{ file.identity?.title || file.name }}</button>
                        <p class="truncate text-xs text-zinc-500">{{ file.original_name || file.name }}</p>
                        <div class="flex flex-wrap justify-between gap-2 text-xs text-zinc-500"><span>{{ formatDate(file.uploaded_at) }}</span><span>{{ fileStatusLabel(file) }}</span></div>
                        <p v-if="file.identity?.amount != null" class="text-sm tabular-nums">{{ formatCurrency(file.identity.amount, file.identity.currency) }}</p>
                    </li>
                </ul>
                <div v-if="!files.length" class="workspace-empty"><DocumentTextIcon class="h-8 w-8 text-zinc-400" aria-hidden="true" /><h3 class="section-heading">{{ emptyTitle }}</h3><p>{{ emptyDescription }}</p></div>
            </div>
            <aside v-if="activeId" class="min-h-[70vh] min-w-0 border-t border-zinc-200 xl:sticky xl:top-20 xl:max-h-[80vh] xl:min-h-0 xl:overflow-y-auto xl:border-l xl:border-t-0 dark:border-zinc-800" aria-label="Selected document">
                <div class="sticky top-0 z-20 flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 ref="previewHeading" tabindex="-1" class="mr-auto text-sm font-semibold focus:outline-none">Document {{ activeIndex + 1 }} of {{ files.length }}</h2>
                    <Link :href="route('files.show', { file: activeId, return_to: returnTo })" class="workspace-button" aria-label="Open document in full view"><ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" /></Link>
                    <button class="workspace-button" :disabled="activeIndex <= 0" aria-label="Previous document" @click="move(-1)"><ChevronUpIcon class="h-4 w-4" aria-hidden="true" /></button>
                    <button class="workspace-button" :disabled="activeIndex >= files.length - 1" aria-label="Next document" @click="move(1)"><ChevronDownIcon class="h-4 w-4" aria-hidden="true" /></button>
                    <button class="workspace-button" aria-label="Close preview" @click="close"><XMarkIcon class="h-4 w-4" aria-hidden="true" /></button>
                </div>
                <FileInspector :key="activeId + '-' + inspectorKey" :file-id="activeId" @dirty="dirty = $event" />
            </aside>
        </div>
    </section>
</template>

<style scoped>
input[type='checkbox'] { @apply rounded-sm border-zinc-300 text-orange-600 focus:ring-orange-600 disabled:opacity-30 dark:border-zinc-600 dark:bg-zinc-800; }
</style>
