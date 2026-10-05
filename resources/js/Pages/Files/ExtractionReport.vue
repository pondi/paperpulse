<template>
  <AuthenticatedLayout>
    <Head title="Extraction Report" />
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Extraction report</h2>
        <Link :href="route('files.show', report.file.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Back to file</Link>
      </div>
    </template>

    <div class="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8 sm:px-6">
      <section class="rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ report.file.name }}</h3>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
          <div><dt class="text-zinc-500 dark:text-zinc-400">Status</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ report.file.status === 'needs_review' ? __('needs_review') : label(report.file.status) }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Document type</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ report.classification.type || report.file.file_type }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Classification confidence</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ confidence(report.classification.confidence) }}</dd></div>
          <div><dt class="text-zinc-500 dark:text-zinc-400">Extraction confidence</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ confidence(report.extraction.confidence_score) }}</dd></div>
          <div v-if="report.coverage.total_pages != null"><dt class="text-zinc-500 dark:text-zinc-400">Pages processed</dt><dd class="text-zinc-900 dark:text-zinc-100">{{ report.coverage.processed_pages }} / {{ report.coverage.total_pages }}</dd></div>
        </dl>
        <p v-if="report.classification.reasoning" class="mt-4 text-sm text-zinc-700 dark:text-zinc-300">{{ report.classification.reasoning }}</p>
      </section>

      <section v-if="report.extraction.has_extraction_issues" class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-6 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
        <h3 class="font-semibold">Extraction needs attention</h3>
        <p v-if="report.review.reason">Review: {{ label(report.review.reason) }}<span v-if="report.review.page_limit"> (page limit: {{ report.review.page_limit }})</span></p>
        <p v-if="report.review.reasoning">{{ report.review.reasoning }}</p>
        <p v-if="report.failure.category">Processing failed: {{ label(report.failure.category) }}. {{ report.failure.retryable ? 'You can retry processing from the file list.' : 'Review the source file before retrying.' }}</p>
        <ul v-if="report.extraction.validation_warnings.length" class="flex list-disc flex-col gap-2 pl-5">
          <li v-for="(warning, index) in report.extraction.validation_warnings" :key="index">{{ warning }}</li>
        </ul>
        <Link :href="route('files.index')" class="font-medium underline">Open file processing</Link>
      </section>

      <section class="rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Extracted entities</h3>
        <ul v-if="report.entities.length" class="mt-4 flex flex-col gap-3 text-sm text-zinc-700 dark:text-zinc-300">
          <li v-for="entity in report.entities" :key="`${entity.type}-${entity.id}`" class="flex flex-wrap items-center gap-3">
            <span class="capitalize">{{ label(entity.type) }}</span>
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
import { Head, Link } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({ report: { type: Object, required: true } });

const { __ } = useTranslations();
const confidence = value => value == null ? 'Not recorded' : `${Math.round(Number(value) * 100)}%`;
const label = value => value.replace(/_/g, ' ');
</script>
