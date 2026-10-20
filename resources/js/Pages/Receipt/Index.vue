<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BulkOperations from '@/Components/Domain/BulkOperations.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';
import { fileStatusLabel } from '@/utils/fileStatus';

const props = defineProps({
    receipts: { type: Array, required: true },
    merchant: { type: Object, default: null }, category: { type: Object, default: null },
    categories: { type: Array, default: () => [] }, filters: { type: Object, default: () => ({}) },
    pagination: { type: Object, default: () => ({}) },
});
const page = usePage();
const __ = key => page.props.language?.messages?.[key] || key;
const { formatDate, formatCurrency } = useDateFormatter();
const selectedReceipts = ref([]);
const selectedReceipt = ref(null);
const search = ref(props.filters.search || '');
const categoryId = ref(props.filters.category_id || '');
const loading = ref(false);
const error = ref('');
const allSelected = computed(() => props.receipts.length > 0 && selectedReceipts.value.length === props.receipts.length);
const filtered = computed(() => !!(props.merchant || props.category || props.filters.search));
const preview = computed(() => selectedReceipt.value ? { ...selectedReceipt.value, title: selectedReceipt.value.merchant?.name || __('unknown_merchant'), file_id: selectedReceipt.value.file?.id } : null);
watch(() => props.receipts, () => { selectedReceipts.value = []; });
watch(() => props.filters, filters => { search.value = filters.search || ''; categoryId.value = filters.category_id || ''; });
function browse() {
    error.value = '';
    router.get(route('receipts.index'), { ...props.filters, search: search.value || undefined, category_id: categoryId.value || undefined, merchant_id: props.merchant?.id || props.filters.merchant_id }, {
        preserveState: true, preserveScroll: true,
        onStart: () => { loading.value = true; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { loading.value = false; },
    });
}
function toggleAll() { selectedReceipts.value = allSelected.value ? [] : props.receipts.map(receipt => receipt.id); }
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="merchant || category ? `${__('receipts')} · ${(merchant || category).name}` : __('receipts')" />
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 basis-full sm:flex-1 sm:basis-0">
                    <h1 class="page-heading wrap-anywhere">{{ __('receipts') }}<span v-if="merchant || category"> · {{ (merchant || category).name }}</span></h1>
                    <p class="page-description">Compare merchants, amounts and dates, then open a receipt to review the original.</p>
                </div>
                <Link :href="route('documents.upload')" class="workspace-primary">{{ __('upload_receipts') }}</Link>
            </div>
        </template>
        <div class="flex min-w-0 flex-col gap-5">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Receipt overview · Table view. Page size and default sort come from Settings. Choose list or grid separately in <Link :href="route('library.index', { type: 'receipt' })" class="underline underline-offset-4">Library</Link>.</p>
            <form role="search" aria-label="Find receipts" class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="browse">
                <div class="min-w-0 flex-1"><label for="receipt-search" class="field-label mb-1">Search receipts</label><input id="receipt-search" v-model="search" type="search" maxlength="255" class="workspace-filter w-full" placeholder="Description or note" /></div>
                <div><label for="receipt-category" class="field-label mb-1">Category</label><select id="receipt-category" v-model="categoryId" class="workspace-filter w-full" @change="browse"><option value="">All categories</option><option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                <button class="workspace-button" :disabled="loading">Search</button>
                <Link v-if="filtered" :href="route('receipts.index')" class="workspace-button">{{ __('all_receipts') }}</Link>
            </form>
            <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <p v-if="loading" role="status" class="text-sm text-zinc-500">Loading receipts…</p>
            <BulkOperations :selected-count="selectedReceipts.length" :selected-ids="selectedReceipts" :categories="categories" @clear-selection="selectedReceipts = []" />
            <section class="workspace-surface overflow-hidden" aria-label="Receipt comparison" :aria-busy="loading">
                <div v-if="receipts.length" class="overflow-x-auto" tabindex="0" role="region" aria-label="Receipts, scroll to compare all columns">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Compare receipt amount, date, category and processing status. Selection applies to this page.</caption>
                        <thead class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-400"><tr>
                            <th scope="col" class="w-10 px-3 py-3"><input type="checkbox" :checked="allSelected" :indeterminate="selectedReceipts.length > 0 && !allSelected" aria-label="Select all receipts on this page" @change="toggleAll" /></th>
                            <th scope="col" class="px-3 py-3 font-medium">{{ __('merchant') }}</th><th scope="col" class="px-3 py-3 text-right font-medium">{{ __('amount') }}</th><th scope="col" class="px-3 py-3 font-medium">{{ __('date') }}</th><th scope="col" class="px-3 py-3 font-medium">{{ __('category') }}</th><th scope="col" class="px-3 py-3 font-medium">Status</th><th scope="col" class="px-3 py-3 font-medium">{{ __('tags') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="receipt in receipts" :key="receipt.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-3 py-4"><input v-model="selectedReceipts" :value="receipt.id" type="checkbox" :aria-label="`Select receipt #${receipt.id} from ${receipt.merchant?.name || 'unknown merchant'}`" /></td>
                                <td class="min-w-[160px] max-w-xs px-3 py-4"><button type="button" :dusk="`receipt-preview-${receipt.id}`" class="max-w-full truncate text-left font-medium text-zinc-900 hover:underline dark:text-zinc-100" @click="selectedReceipt = receipt">{{ receipt.merchant?.name || __('unknown_merchant') }}</button><p v-if="receipt.note || receipt.receipt_description" class="mt-1 max-w-xs truncate text-xs text-zinc-500">{{ receipt.note || receipt.receipt_description }}</p></td>
                                <td class="whitespace-nowrap px-3 py-4 text-right font-medium tabular-nums">{{ receipt.total_amount == null ? '—' : formatCurrency(receipt.total_amount, receipt.currency) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-zinc-600 dark:text-zinc-400">{{ formatDate(receipt.receipt_date) }}</td>
                                <td class="max-w-[180px] px-3 py-4 text-zinc-600 wrap-anywhere dark:text-zinc-400">{{ receipt.category?.name || receipt.receipt_category || __('uncategorized') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-zinc-500 dark:text-zinc-400">{{ receipt.file?.status ? fileStatusLabel(receipt.file) : 'No source file' }}</td>
                                <td class="max-w-[200px] px-3 py-4 text-xs text-zinc-500 wrap-anywhere dark:text-zinc-400">{{ receipt.tags?.map(tag => tag.name).join(', ') || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="workspace-empty">
                    <h2 class="section-heading">{{ category ? __('no_receipts_in_category').replace(':category', category.name) : merchant ? `No receipts from ${merchant.name}` : filters.search ? 'No receipts match your search' : __('upload_first_receipts') }}</h2>
                    <p>{{ filtered ? 'Adjust your search or choose All Receipts to clear the filters.' : __('no_receipts_description') }}</p>
                    <Link v-if="filtered" :href="route('receipts.index')" class="workspace-button">{{ __('all_receipts') }}</Link>
                    <Link v-else :href="route('documents.upload')" class="workspace-primary">{{ __('upload_receipts') }}</Link>
                </div>
                <Pagination v-if="pagination.links?.length > 3" :links="pagination.links" :from="pagination.from" :to="pagination.to" :total="pagination.total" />
            </section>
            <p v-if="pagination.total" class="text-sm text-zinc-500" role="status">{{ pagination.total }} receipts · Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
        </div>
        <FilePreviewModal :show="!!selectedReceipt?.file?.id" :item="preview" @close="selectedReceipt = null" />
        <div v-if="selectedReceipt && !selectedReceipt.file?.id" class="workspace-surface mt-4 p-4">
            <p class="text-sm">No original file is attached to this receipt.</p><Link :href="route('receipts.show', selectedReceipt.id)" class="workspace-button mt-3">Open receipt details</Link>
        </div>
    </AuthenticatedLayout>
</template>
