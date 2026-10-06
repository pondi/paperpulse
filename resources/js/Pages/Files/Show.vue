<script setup>
import { computed, watch } from 'vue';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowTopRightOnSquareIcon, ExclamationCircleIcon, DocumentIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { fileStatusLabel } from '@/utils/fileStatus';
import ProcessingLimit from '@/Components/Domain/ProcessingLimit.vue';
import ProcessingFailure from '@/Components/Domain/ProcessingFailure.vue';
import ProcessingProgress from '@/Components/Domain/ProcessingProgress.vue';
import DocumentPreview from '@/Components/Domain/DocumentPreview.vue';
import VoucherCard from '@/Components/Entities/VoucherCard.vue';
import WarrantyCard from '@/Components/Entities/WarrantyCard.vue';
import ReturnPolicyCard from '@/Components/Entities/ReturnPolicyCard.vue';
import InvoiceCard from '@/Components/Entities/InvoiceCard.vue';
import ContractCard from '@/Components/Entities/ContractCard.vue';
import BankStatementCard from '@/Components/Entities/BankStatementCard.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    file: { type: Object, required: true },
    extractedEntities: { type: Array, default: () => [] },
    hasLegacyData: { type: Boolean, default: false },
});
const { start, stop } = usePoll(5000, { only: ['file', 'extractedEntities'] }, { autoStart: false });
watch(() => ['pending', 'processing'].includes(props.file.status), active => active ? start() : stop(), { immediate: true });
const { formatDate, formatDateTime, formatCurrency } = useDateFormatter();
const components = { voucher: VoucherCard, warranty: WarrantyCard, return_policy: ReturnPolicyCard, invoice: InvoiceCard, contract: ContractCard, bank_statement: BankStatementCard };
const entityProps = extraction => ({ [extraction.entity_type === 'bank_statement' ? 'statement' : extraction.entity_type === 'return_policy' ? 'returnPolicy' : extraction.entity_type]: extraction.entity });
const entityUrl = extraction => {
    const names = { receipt: 'receipts.show', document: 'documents.show', invoice: 'invoices.show', contract: 'contracts.show', bank_statement: 'bank-statements.show', voucher: 'vouchers.show' };
    return names[extraction.entity_type] ? route(names[extraction.entity_type], extraction.entity_id) : null;
};
const legacy = computed(() => props.file.primary_receipt
    ? { label: 'Receipt', href: route('receipts.show', props.file.primary_receipt.id) }
    : props.file.primary_document ? { label: 'Document', href: route('documents.show', props.file.primary_document.id) } : null);
const reviewMessages = {
    uncertain_classification: 'Confirm the document type to finish extraction.',
    receipt_totals: 'The extracted total and line items need checking against the original.',
};
const reviewMessage = computed(() => reviewMessages[props.file.review?.reason] || 'Check the extraction report for warnings and processing details.');
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="file.name || 'Document workspace'" />
        <template #header>
            <div class="flex flex-col gap-3">
                <Link :href="file.back_url || route('library.index')" dusk="workspace-back" class="inline-flex items-center gap-1.5 self-start text-xs text-zinc-500 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-400"><ArrowLeftIcon class="h-3.5 w-3.5" aria-hidden="true" />{{ file.back_label || 'Back to Library' }}</Link>
                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                    <div class="min-w-0 flex-1"><h1 class="break-words text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ file.name || 'Untitled document' }}</h1><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Uploaded {{ formatDateTime(file.uploaded_at) }}<span class="ml-2 uppercase">{{ file.extension }}</span></p></div>
                    <a :href="file.viewUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">Open original<ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" /></a>
                </div>
            </div>
        </template>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <div class="order-2 xl:order-1 xl:sticky xl:top-24"><DocumentPreview :file="file" /></div>
            <div class="order-1 flex min-w-0 flex-col gap-5 xl:order-2">
                <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Document information</h2>
                        <span :class="[file.status === 'needs_review' || file.status === 'failed' ? 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400' : file.status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400', 'rounded-md px-2 py-1 text-xs font-medium']">{{ fileStatusLabel(file) }}</span>
                    </div>
                    <ProcessingLimit v-if="file.review?.reason === 'processing_limit'" :review="file.review" class="mt-4" />
                    <ProcessingFailure v-else-if="file.status === 'failed' && file.can_view_extraction_report" :failure="file.failure" :processing="file.processing" :file-id="file.id" class="mt-4" />
                    <div v-else-if="file.extraction?.has_extraction_issues" class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 dark:bg-amber-500/10">
                        <ExclamationCircleIcon class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" /><p class="text-sm leading-5 text-amber-800 dark:text-amber-300">{{ reviewMessage }}</p>
                    </div>
                    <ProcessingProgress v-if="file.processing" :processing="file.processing" class="mt-4" />
                    <p v-if="file.note" class="mt-4 whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-300">{{ file.note }}</p>
                    <div v-if="file.collections?.length" class="mt-4 flex flex-wrap gap-2"><Link v-for="collection in file.collections" :key="collection.id" :href="route('collections.show', collection.id)" class="rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-600 hover:text-amber-700 dark:bg-zinc-800 dark:text-zinc-300">{{ collection.name }}</Link></div>
                    <div v-if="file.can_view_extraction_report" class="mt-4 flex flex-wrap gap-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <Link :href="route('files.extraction-report', file.id)" class="text-xs font-medium text-amber-700 dark:text-amber-400">Extraction report</Link>
                        <Link v-if="file.status !== 'completed'" :href="route('files.index', { status: file.status })" class="text-xs text-zinc-500 dark:text-zinc-400">Processing activity</Link>
                    </div>
                </section>

                <section v-for="extraction in extractedEntities" :key="extraction.entity_type + '-' + extraction.entity_id" class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <h2 class="text-sm font-semibold capitalize text-zinc-900 dark:text-white">{{ extraction.entity_type.replaceAll('_', ' ') }}</h2>
                        <Link v-if="entityUrl(extraction)" :href="entityUrl(extraction)" class="text-xs font-medium text-amber-700 dark:text-amber-400">Open details</Link>
                    </div>
                    <div class="p-5">
                        <template v-if="extraction.entity_type === 'receipt'">
                            <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="font-medium text-zinc-800 dark:text-white">{{ extraction.entity.merchant?.name || 'Receipt' }}</h3><p class="mt-1 text-xs text-zinc-500">{{ formatDate(extraction.entity.receipt_date) }}</p></div><p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ formatCurrency(extraction.entity.total_amount, extraction.entity.currency) }}</p></div>
                            <p v-if="extraction.entity.receipt_description" class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">{{ extraction.entity.receipt_description }}</p>
                            <ul v-if="extraction.entity.lineItems?.length" class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800"><li v-for="item in extraction.entity.lineItems" :key="item.id" class="flex justify-between gap-3 py-2 text-xs text-zinc-600 dark:text-zinc-400"><span>{{ item.description }}<span class="ml-1 text-zinc-400">× {{ item.quantity }}</span></span><span class="whitespace-nowrap">{{ formatCurrency(item.total_amount, extraction.entity.currency) }}</span></li></ul>
                        </template>
                        <template v-else-if="extraction.entity_type === 'document'">
                            <h3 class="mb-2 font-medium text-zinc-800 dark:text-white">{{ extraction.entity.title }}</h3><p class="whitespace-pre-wrap text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ extraction.entity.summary || extraction.entity.description || 'Open details to organize and share this document.' }}</p>
                        </template>
                        <component v-else-if="components[extraction.entity_type]" :is="components[extraction.entity_type]" v-bind="entityProps(extraction)" :show-actions="false" />
                    </div>
                </section>

                <section v-if="!extractedEntities.length" class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <DocumentIcon class="h-8 w-8 text-zinc-300 dark:text-zinc-600" aria-hidden="true" />
                    <template v-if="legacy"><h2 class="text-sm font-medium text-zinc-800 dark:text-white">{{ legacy.label }} information is available</h2><Link :href="legacy.href" class="text-sm font-medium text-amber-700 dark:text-amber-400">Open {{ legacy.label.toLowerCase() }} details</Link></template>
                    <template v-else><h2 class="text-sm font-medium text-zinc-800 dark:text-white">{{ file.status === 'failed' ? 'Extraction stopped' : ['pending', 'processing'].includes(file.status) ? 'Your document is being processed' : 'No extracted information yet' }}</h2><p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ ['pending', 'processing'].includes(file.status) ? 'You can view the original while its information is being extracted.' : 'You can still view the original. Check the extraction report for the next step.' }}</p></template>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
