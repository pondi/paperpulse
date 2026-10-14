<script setup>
import { computed } from 'vue';
import { DocumentTextIcon, EyeIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ result: { type: Object, required: true }, searchQuery: { type: String, default: '' }, selected: Boolean });
const emit = defineEmits(['preview', 'toggle-select']);
const { formatDate, formatCurrency } = useDateFormatter();
const typeLabel = computed(() => ({ receipt: 'Receipt', document: 'Document', invoice: 'Invoice', contract: 'Contract', voucher: 'Voucher', warranty: 'Warranty', return_policy: 'Return policy', bank_statement: 'Bank statement' })[props.result.type] || 'Document');
const amountLabel = computed(() => ({ contract: 'Contract value', voucher: 'Current value', bank_statement: 'Closing balance' })[props.result.type] || 'Total');
const metadata = computed(() => [props.result.category, props.result.document_type, props.result.invoice_number && `Invoice #${props.result.invoice_number}`, props.result.contract_type, props.result.status, props.result.voucher_type, props.result.manufacturer, props.result.transaction_count && `${props.result.transaction_count} transactions`].filter(Boolean));
const escapeHtml = text => String(text ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
function highlightText(text) {
    const query = props.searchQuery.trim();
    if (!query) return escapeHtml(text);
    const pattern = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return String(text ?? '').split(pattern).map((part, index) => index % 2 ? `<mark class="rounded bg-orange-100 text-orange-950 dark:bg-orange-900 dark:text-orange-100">${escapeHtml(part)}</mark>` : escapeHtml(part)).join('');
}
</script>

<template>
    <article class="flex min-w-0 items-start gap-3 p-4 sm:gap-4" :class="selected ? 'bg-orange-50 dark:bg-orange-950/20' : 'bg-white dark:bg-zinc-900'">
        <input type="checkbox" :aria-label="`Select ${typeLabel.toLowerCase()} ${result.title} (#${result.id})`" :checked="selected" @change="emit('toggle-select')" class="mt-1 rounded border-zinc-300 text-orange-600 focus:ring-orange-600 dark:border-zinc-600 dark:bg-zinc-800" />
        <DocumentTextIcon class="hidden h-8 w-8 shrink-0 text-zinc-400 sm:block" aria-hidden="true" />
        <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:gap-4">
                <div class="min-w-0">
                    <p class="mb-1 text-xs text-zinc-500 dark:text-zinc-400">{{ typeLabel }}<span v-if="result.date"> · {{ formatDate(result.date) }}</span></p>
                    <h2 class="text-sm font-semibold"><button type="button" :aria-label="`Preview ${typeLabel.toLowerCase()} ${result.title} (#${result.id})`" @click="emit('preview', result)" class="rounded text-left text-zinc-900 hover:text-orange-700 dark:text-zinc-100 dark:hover:text-orange-400"><span class="break-words [overflow-wrap:anywhere]" v-html="highlightText(result.title)" /></button></h2>
                    <p v-if="result.filename || result.file?.filename" class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400" :title="result.filename || result.file.filename">{{ result.filename || result.file.filename }}</p>
                </div>
                <div v-if="result.total != null" class="min-w-0 shrink-0 sm:text-right">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ amountLabel }}</p>
                    <p class="break-words text-sm font-semibold tabular-nums">{{ formatCurrency(result.total, result.currency) }}</p>
                    <p v-if="result.payment_status" class="text-xs text-zinc-500 dark:text-zinc-400">{{ result.payment_status }}</p>
                </div>
            </div>
            <p v-if="result.description" class="line-clamp-2 break-words text-sm leading-6 text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" v-html="highlightText(result.description)" />
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                <button type="button" @click="emit('preview', result)" :aria-label="`Quick preview of ${result.title} (#${result.id})`" class="inline-flex items-center gap-1.5 rounded py-1 text-orange-700 dark:text-orange-400"><EyeIcon class="h-4 w-4" aria-hidden="true" />Preview</button>
                <a v-if="result.url" :href="result.url" :aria-label="`Open ${typeLabel.toLowerCase()} ${result.title} (#${result.id}) in new tab`" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded py-1 text-zinc-500 hover:text-orange-700 dark:text-zinc-400 dark:hover:text-orange-400">Open in new tab<ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" /></a>
                <details v-if="metadata.length || result.tags?.length || result.items?.length" class="min-w-0 basis-full">
                    <summary class="cursor-pointer rounded py-1 text-zinc-500 dark:text-zinc-400">Details &amp; tags</summary>
                    <div class="mt-2 flex flex-wrap gap-2 text-zinc-600 dark:text-zinc-300"><span v-for="item in metadata" :key="item" class="break-words rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">{{ item }}</span><span v-for="tag in result.tags" :key="tag" class="break-words rounded bg-zinc-100 px-2 py-1 dark:bg-zinc-800">{{ tag }}</span></div>
                    <ul v-if="result.items?.length" class="mt-2 flex flex-col gap-1 text-zinc-500 dark:text-zinc-400"><li v-for="(item, index) in result.items" :key="index" class="break-words">{{ item.description }}<span v-if="item.price"> · {{ item.price }}</span></li></ul>
                </details>
            </div>
        </div>
    </article>
</template>
