<template>
  <AuthenticatedLayout>
    <Head title="Extraction Report" />
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Extraction report</h2>
        <Link :href="route('files.show', report.file.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Open file workspace</Link>
        <Link :href="route('files.index', { file_id: report.file.id })" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View processing record</Link>
      </div>
    </template>

    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8 sm:px-6">
      <section class="rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ report.file.name }}</h3>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
          <div><dt class="text-zinc-500 dark:text-zinc-400">Status</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ fileStatusLabel({ ...report.file, review: report.review }) }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Document type</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ report.classification.type || report.file.file_type }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Classification confidence</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ confidence(report.classification.confidence) }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Extraction confidence</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ confidence(report.extraction.confidence_score) }}</dd></div>
          <div v-if="report.coverage.total_pages != null"><dt class="text-zinc-500 dark:text-zinc-400">Pages processed</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ report.coverage.processed_pages }} / {{ report.coverage.total_pages }}</dd></div>
        </dl>
        <p v-if="report.classification.reasoning" class="mt-4 text-sm text-zinc-700 dark:text-zinc-300">{{ report.classification.reasoning }}</p>
      </section>

      <section v-if="report.extraction.has_extraction_issues" class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-6 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
        <h3 class="font-semibold">Extraction needs attention</h3>
        <ProcessingLimit v-if="report.review.reason === 'processing_limit'" :review="report.review" />
        <p v-else-if="report.review.reason">Review: {{ label(report.review.reason) }}<span v-if="report.review.page_limit"> (page limit: {{ report.review.page_limit }})</span></p>
        <div v-if="report.review.reason === 'receipt_totals'" class="flex flex-col gap-3">
          <dl v-if="report.reconciliation" class="flex flex-col gap-2">
            <div><dt class="inline font-medium">Line item total: </dt><dd class="inline">{{ formatCurrency(report.reconciliation.calculated_total, report.receipt_currency) }}</dd></div>
            <div><dt class="inline font-medium">Source discount: </dt><dd class="inline">− {{ formatCurrency(report.reconciliation.discount_amount, report.receipt_currency) }}</dd></div>
            <div v-if="Number(report.reconciliation.tip_amount)"><dt class="inline font-medium">Source tip: </dt><dd class="inline">{{ formatCurrency(report.reconciliation.tip_amount, report.receipt_currency) }}</dd></div>
            <div><dt class="inline font-medium">Final amount: </dt><dd class="inline">{{ formatCurrency(report.reconciliation.total_amount, report.receipt_currency) }}</dd></div>
          </dl>
          <p>Compare these values with the original in the file workspace. Correct saved values using the receipt details below, then return here to confirm. Missing source discounts or line items require correcting extraction and retrying; confirmation does not rerun extraction.</p>
          <form @submit.prevent="resolveReview" class="flex flex-col items-start gap-3">
            <label class="flex items-start gap-2"><input v-model="confirmed" type="checkbox" class="mt-1 rounded border-amber-400" />I checked the extracted values against the source.</label>
            <p v-if="reviewError" role="alert">{{ reviewError }}</p>
            <button type="submit" :disabled="!confirmed || resolving" class="rounded-md bg-amber-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Confirm reconciled totals</button>
          </form>
          <button type="button" @click="router.post(route('files.reprocess', report.file.id))" class="self-start font-medium underline">Retry extraction for missing source values</button>
        </div>
        <p v-if="report.review.reasoning">{{ report.review.reasoning }}</p>
        <ProcessingFailure v-if="report.file.status === 'failed'" :failure="report.failure" :processing="report.processing" :file-id="report.file.id" />
        <ul v-if="report.extraction.validation_warnings.length" class="flex list-disc flex-col gap-2 pl-5">
          <li v-for="(warning, index) in report.extraction.validation_warnings" :key="index">{{ warning }}</li>
        </ul>
        <Link :href="route('files.index', { file_id: report.file.id })" class="font-medium underline">Open file processing</Link>
      </section>

      <section class="rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Extracted entities</h3>
        <ul v-if="report.entities.length" class="mt-4 flex flex-col gap-3 text-sm text-zinc-700 dark:text-zinc-300">
          <li v-for="entity in report.entities" :key="`${entity.type}-${entity.id}`" class="flex flex-wrap items-center gap-3">
            <Link :href="entityUrl(entity)" class="capitalize text-blue-600 hover:underline dark:text-blue-400">{{ label(entity.type) }} #{{ entity.id }}</Link>
            <span v-if="entity.is_primary" class="rounded bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Primary</span>
            <span>{{ confidence(entity.confidence_score) }}</span>
            <span v-if="entity.provider">{{ entity.provider }}</span>
          </li>
        </ul>
        <p v-else class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">No extracted entities have been saved for this file.</p>
      </section>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDateFormatter } from '@/Composables/useDateFormatter';
import { fileStatusLabel } from '@/utils/fileStatus';
import ProcessingLimit from '@/Components/Domain/ProcessingLimit.vue';
import ProcessingFailure from '@/Components/Domain/ProcessingFailure.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ report: { type: Object, required: true } });
const entityRoutes = { receipt: 'receipts.show', document: 'documents.show', invoice: 'invoices.show', contract: 'contracts.show', bank_statement: 'bank-statements.show', voucher: 'vouchers.show' };
const entityUrl = entity => entityRoutes[entity.type] ? route(entityRoutes[entity.type], entity.id) : route('files.show', props.report.file.id);

const { formatCurrency } = useDateFormatter();
const confirmed = ref(false);
const resolving = ref(false);
const reviewError = ref('');
const resolveReview = () => {
    resolving.value = true;
    router.post(route('files.resolve-review', props.report.file.id), { confirmed: confirmed.value }, {
        onError: errors => { reviewError.value = errors.review || errors.confirmed; },
        onFinish: () => { resolving.value = false; },
    });
};

const confidence = value => value == null ? 'Not recorded' : `${Math.round(Number(value) * 100)}%`;
const label = value => value.replace(/_/g, ' ');
</script>
