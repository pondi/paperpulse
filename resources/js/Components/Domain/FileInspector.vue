<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import DocumentPreview from '@/Components/Domain/DocumentPreview.vue';
import ProcessingLimit from '@/Components/Domain/ProcessingLimit.vue';
import ProcessingFailure from '@/Components/Domain/ProcessingFailure.vue';
import ProcessingProgress from '@/Components/Domain/ProcessingProgress.vue';

const props = defineProps({ fileId: { type: Number, required: true }, initial: { type: Object, default: null } });
const emit = defineEmits(['updated', 'dirty']);
const payload = ref(null);
const loading = ref(false);
const error = ref('');
const saving = ref(false);
const drafts = ref([]);
const collectionId = ref('');
const baseline = ref('');
let controller;
const dirty = computed(() => JSON.stringify(drafts.value) !== baseline.value && Boolean(payload.value));
watch(dirty, value => emit('dirty', value));
function hydrate(data) {
    payload.value = data;
    drafts.value = data.extractedEntities.map(extraction => ({
        entity_type: extraction.entity_type, entity_id: extraction.entity_id,
        vendor: extraction.entity.merchant?.name || '',
        total_amount: extraction.entity.total_amount, tax_amount: extraction.entity.tax_amount,
        currency: extraction.entity.currency, receipt_date: extraction.entity.receipt_date?.slice(0, 10),
        category_id: extraction.entity.category_id ?? extraction.entity.category?.id ?? null,
        title: extraction.entity.title || '', summary: extraction.entity.summary || '',
        line_items: (extraction.entity.lineItems || []).map(item => ({ id: item.id, text: item.description, qty: item.quantity, price: item.unit_price, total: item.total_amount })),
    }));
    baseline.value = JSON.stringify(drafts.value);
}
async function load() {
    controller?.abort();
    controller = new AbortController();
    const request = controller;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('files.show', props.fileId), { signal: request.signal, headers: { Accept: 'application/json' } });
        if (!request.signal.aborted) hydrate(data);
    } catch (exception) {
        if (!request.signal.aborted) error.value = 'Could not load this file. Check your connection and try again.';
    } finally {
        if (!request.signal.aborted) loading.value = false;
    }
}
watch(() => props.fileId, () => {
    payload.value = null;
    if (props.initial?.file.id === props.fileId) hydrate(props.initial);
    else load();
}, { immediate: true });
let poll;
onMounted(() => { poll = setInterval(() => { if (!dirty.value && !saving.value && !loading.value && (['pending', 'processing'].includes(payload.value?.file.status) || payload.value?.file.preview_pending)) load(); }, 10000); });
onBeforeUnmount(() => { controller?.abort(); clearInterval(poll); });
function save(draft) {
    const pendingDrafts = JSON.parse(JSON.stringify(drafts.value));
    const previousBaseline = JSON.parse(baseline.value);
    saving.value = true;
    error.value = '';
    router.post(route('workspace.actions'), { ...draft, action: 'save', file_ids: [props.fileId] }, {
        preserveScroll: true,
        onSuccess: async () => {
            await load();
            drafts.value = drafts.value.map((value, index) => {
                const pending = pendingDrafts.find(item => item.entity_type === value.entity_type && item.entity_id === value.entity_id);
                const original = previousBaseline.find(item => item.entity_type === value.entity_type && item.entity_id === value.entity_id);
                return pending && !(value.entity_type === draft.entity_type && value.entity_id === draft.entity_id) && JSON.stringify(pending) !== JSON.stringify(original) ? pending : value;
            });
            emit('updated');
        },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { saving.value = false; },
    });
}
function review(action) {
    if (dirty.value) { error.value = 'Save your changes before reviewing this file.'; return; }
    if (action === 'approve' && !confirm('Approve this file after checking the extracted values against the original?')) return;
    saving.value = true;
    router.post(route('workspace.actions'), { action, file_ids: [props.fileId] }, {
        preserveScroll: true,
        onSuccess: async () => { await load(); emit('updated'); },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { saving.value = false; },
    });
}
function addToCollection() {
    if (!collectionId.value || dirty.value) return;
    saving.value = true;
    router.post(route('collections.files.add', collectionId.value), { file_ids: [props.fileId] }, {
        preserveScroll: true,
        onSuccess: async () => { collectionId.value = ''; await load(); },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { saving.value = false; },
    });
}
const editable = computed(() => payload.value?.file.can_edit && ['completed', 'needs_review'].includes(payload.value.file.status));
defineExpose({ dirty });
</script>

<template>
    <section class="file-inspector min-w-0 bg-white text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200" aria-label="Preview and edit" :aria-busy="loading || saving">
        <p v-if="loading" class="p-6 text-sm text-zinc-500" role="status">Loading document…</p>
        <div v-if="error" class="border-b border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" role="alert">{{ error }} <button v-if="!payload" type="button" @click="load" class="underline">Try again</button></div>
        <template v-if="payload && !loading">
            <div class="flex items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                <h2 class="truncate text-sm font-semibold" :title="payload.file.name">{{ payload.file.name }}</h2>
                <span class="shrink-0 text-xs capitalize text-zinc-500">{{ payload.file.review_status || payload.file.status.replaceAll('_', ' ') }}</span>
            </div>
            <div class="inspector-content grid min-w-0">
                <DocumentPreview :file="payload.file" />
                <div class="min-w-0">
                    <div class="border-b border-zinc-200 p-3 dark:border-zinc-800">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Document information</h3>
                        <ProcessingLimit v-if="payload.file.review?.reason === 'processing_limit'" :review="payload.file.review" class="mt-3" />
                        <ProcessingFailure v-else-if="payload.file.status === 'failed' && payload.file.can_edit" :failure="payload.file.failure" :processing="payload.file.processing" :file-id="fileId" class="mt-3" />
                        <ProcessingProgress v-if="payload.file.processing && ['pending', 'processing'].includes(payload.file.status)" :processing="payload.file.processing" class="mt-3" />
                        <p v-if="payload.file.review?.reason === 'receipt_totals'" class="mt-2 text-xs text-orange-700 dark:text-orange-300">Check the total and line items against the original before approving.</p>
                        <p v-if="payload.file.review?.reason === 'uncertain_classification'" class="mt-2 text-xs">The document type needs correction. <Link :href="route('files.index', { status: 'needs_review' })" class="underline">Review processing</Link></p>
                        <p v-if="!payload.file.can_edit" class="mt-2 text-xs text-zinc-500">Shared file · read only</p>
                    </div>
                    <form v-for="(draft, index) in drafts" :key="draft.entity_type + draft.entity_id" @submit.prevent="save(draft)" class="flex flex-col gap-3 border-b border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="flex justify-between gap-2 text-xs"><h3 class="font-semibold capitalize">{{ draft.entity_type.replaceAll('_', ' ') }}</h3><span class="tabular-nums text-zinc-500">{{ payload.extractedEntities[index].confidence_score == null ? 'Confidence unavailable' : Math.round(Number(payload.extractedEntities[index].confidence_score) * 100) + '% confidence' }}</span></div>
                        <fieldset :disabled="!editable || saving" class="flex min-w-0 flex-col gap-3">
                            <template v-if="draft.entity_type === 'receipt'">
                                <label>Vendor<input v-model="draft.vendor" type="text" maxlength="255" /></label>
                                <div class="grid grid-cols-2 gap-2"><label>Total<input v-model="draft.total_amount" type="number" step="any" required /></label><label>Tax<input v-model="draft.tax_amount" type="number" step="any" /></label></div>
                                <div class="grid grid-cols-2 gap-2"><label>Date<input v-model="draft.receipt_date" type="date" required /></label><label>Currency<input v-model="draft.currency" maxlength="3" minlength="3" required /></label></div>
                                <label v-if="editable">Category<select v-model="draft.category_id"><option :value="null">Uncategorized</option><option v-for="category in payload.file.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                                <details :open="draft.line_items.length <= 5">
                                    <summary class="cursor-pointer text-xs font-semibold">Line items · {{ draft.line_items.length }}</summary>
                                    <div v-for="(item, itemIndex) in draft.line_items" :key="item.id" class="mt-3 flex flex-col gap-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                                        <label :for="'line-' + draft.entity_id + '-' + item.id">Item {{ itemIndex + 1 }}<input :id="'line-' + draft.entity_id + '-' + item.id" v-model="item.text" maxlength="255" required /></label>
                                        <div class="grid grid-cols-3 gap-2"><label>Qty<input v-model="item.qty" type="number" min="0" step="any" required /></label><label>Price<input v-model="item.price" type="number" min="0" step="any" required /></label><label>Total<input v-model="item.total" type="number" min="0" step="any" required /></label></div>
                                    </div>
                                </details>
                            </template>
                            <template v-else-if="draft.entity_type === 'document'">
                                <label>Title<input v-model="draft.title" maxlength="255" required /></label>
                                <label>Summary<textarea v-model="draft.summary" rows="5" maxlength="1000" /></label>
                            </template>
                            <template v-else>
                                <dl class="grid grid-cols-2 gap-2 text-xs"><template v-for="(value, key) in payload.extractedEntities[index].entity" :key="key"><template v-if="value != null && typeof value !== 'object' && !['id', 'file_id', 'user_id'].includes(key)"><dt class="break-words capitalize text-zinc-500">{{ key.replaceAll('_', ' ') }}</dt><dd class="break-words">{{ value }}</dd></template></template></dl>
                            </template>
                            <button v-if="editable && ['receipt', 'document'].includes(draft.entity_type)" type="submit" class="workspace-primary self-start" :disabled="saving || !dirty">{{ saving ? 'Saving…' : 'Save changes' }}</button>
                        </fieldset>
                    </form>
                    <details v-if="payload.file.can_edit" class="border-b border-zinc-200 p-3 dark:border-zinc-800">
                        <summary class="cursor-pointer text-xs font-semibold">Sharing</summary>
                        <div v-for="extraction in payload.extractedEntities.filter(item => ['receipt', 'document'].includes(item.entity_type))" :key="extraction.entity_type + extraction.entity_id" class="mt-3">
                            <SharingControls :file-id="extraction.entity_id" :file-type="extraction.entity_type" :current-shares="extraction.shares || []" :enable-share-links="false" :readonly="dirty" @shares-updated="extraction.shares = $event" />
                        </div>
                    </details>
                    <p v-if="!drafts.length" class="p-4 text-sm text-zinc-500">{{ ['pending', 'processing'].includes(payload.file.status) ? 'Extraction is in progress. Refresh to check for results.' : 'No extracted metadata is available. You can still inspect the original.' }} <button @click="load" class="underline">Refresh</button></p>
                    <div class="flex flex-col gap-2 border-b border-zinc-200 p-3 dark:border-zinc-800">
                        <h3 class="text-xs font-semibold">Collections</h3>
                        <div class="flex flex-wrap gap-2"><Link v-for="collection in payload.file.collections" :key="collection.id" :href="route('collections.show', collection.id)" class="text-xs text-zinc-500 underline">{{ collection.name }}</Link><span v-if="!payload.file.collections?.length" class="text-xs text-zinc-500">Not assigned to a collection</span></div>
                        <form v-if="payload.file.can_edit" @submit.prevent="addToCollection" class="flex items-end gap-2"><label class="flex-1">Add to collection<select v-model="collectionId" :disabled="saving || dirty"><option value="">Choose collection</option><option v-for="collection in payload.file.available_collections" :key="collection.id" :value="collection.id">{{ collection.path || collection.name }}</option></select></label><button class="workspace-button" :disabled="!collectionId || saving || dirty">Add</button></form>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 p-3">
                        <button v-if="editable" type="button" class="workspace-primary" :disabled="saving || dirty" @click="review('approve')">Approve</button>
                        <button v-if="editable" type="button" class="workspace-button" :disabled="saving || dirty" @click="review('flag')">Flag</button>
                        <Link v-if="payload.file.can_view_extraction_report" :href="route('files.extraction-report', fileId)" class="text-xs text-zinc-500 underline">Extraction report</Link>
                        <span v-if="dirty" class="text-xs text-orange-700 dark:text-orange-300">Unsaved changes</span>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<style scoped>
.file-inspector { container-type: inline-size; }
@container (min-width: 420px) { .inspector-content { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
label { @apply flex min-w-0 flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-400; }
input, select, textarea { @apply w-full min-w-0 rounded border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-900 focus:border-orange-600 focus:ring-orange-600 disabled:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:disabled:bg-zinc-950; }
</style>
