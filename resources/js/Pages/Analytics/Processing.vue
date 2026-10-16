<template>
    <AuthenticatedLayout>
        <Head title="Processing analytics" />
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold leading-tight text-zinc-950 dark:text-zinc-100">Processing analytics</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Admin diagnostics for extraction quality and recurring failures.</p>
                </div>
                <label for="processing-period" class="flex flex-col gap-1.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 sm:min-w-48">
                    Activity period
                    <select
                        id="processing-period"
                        v-model="selectedDays"
                        @change="changePeriod"
                        class="min-h-11 rounded-lg border-zinc-300 bg-white text-zinc-900 shadow-sm focus:border-amber-600 focus:ring-amber-600 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200"
                    >
                        <option :value="7">Last 7 Days</option>
                        <option :value="14">Last 14 Days</option>
                        <option :value="30">Last 30 Days</option>
                        <option :value="90">Last 90 Days</option>
                    </select>
                </label>
            </div>
        </template>

        <div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <section aria-labelledby="processing-summary-heading" class="space-y-3">
                    <div>
                        <h3 id="processing-summary-heading" class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Processing snapshot</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Counts and averages across recorded processing attempts.</p>
                    </div>
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
                            <dt class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total processed</dt>
                            <dd class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-100">{{ stats.total_processed }}</dd>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
                            <dt class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Success rate</dt>
                            <dd class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-100">{{ stats.success_rate }}%</dd>
                            <dd class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ stats.total_success }} succeeded, {{ stats.total_failed }} failed</dd>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
                            <dt class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Average confidence</dt>
                            <dd class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-100">{{ (stats.avg_confidence * 100).toFixed(2) }}%</dd>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
                            <dt class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Average processing time</dt>
                            <dd class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-100">{{ stats.avg_duration_ms }}ms</dd>
                        </div>
                    </dl>
                </section>

                <!-- Document Type Distribution -->
                <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                            Document Type Distribution
                        </h3>
                    </div>
                    <div class="px-6 py-4">
                        <div v-if="documentTypes.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Type</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Total</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Success</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Failed</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Success Rate</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Avg Confidence</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Avg Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    <tr v-for="(type, i) in documentTypes" :key="`${i}-${type.type}`">
                                        <td class="px-3 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ type.type }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ type.total }}</td>
                                        <td class="px-3 py-4 text-sm text-green-600 dark:text-green-400">{{ type.success }}</td>
                                        <td class="px-3 py-4 text-sm text-red-600 dark:text-red-400">{{ type.failed }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ type.success_rate }}%</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ (type.avg_confidence * 100).toFixed(2) }}%</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ type.avg_duration_ms }}ms</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-zinc-500 dark:text-zinc-400 text-center py-8">
                            No processing data yet. Upload files to see analytics.
                        </div>
                    </div>
                </div>

                <section v-if="Object.values(qualityMetrics).length" aria-labelledby="quality-metrics-heading" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <header class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
                        <h3 id="quality-metrics-heading" class="font-semibold text-zinc-950 dark:text-zinc-100">Extraction quality by type</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Confidence, duration and validation warnings for successful extractions.</p>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Type</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Extractions</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Mean confidence</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Range</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg. time</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">With warnings</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                <tr v-for="metric in Object.values(qualityMetrics)" :key="metric.document_type">
                                    <th scope="row" class="px-4 py-3 text-left text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ metric.document_type }}</th>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ metric.total_extractions }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ (metric.avg_confidence * 100).toFixed(1) }}%</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ (metric.min_confidence * 100).toFixed(1) }}–{{ (metric.max_confidence * 100).toFixed(1) }}%</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ metric.avg_duration_ms }}ms</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ metric.extractions_with_warnings }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Unknown Document Types (Need Extractors) -->
                <div v-if="unknownTypes.length > 0" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-amber-200 dark:border-amber-800">
                        <h3 class="text-lg font-medium text-amber-900 dark:text-amber-100">
                            ⚠️ Unknown Document Types (Need Extractors)
                        </h3>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                            These document types were classified as "unknown" and may need new extractors created.
                        </p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="space-y-4">
                            <div v-for="(item, index) in unknownTypes" :key="index" class="p-4 bg-white dark:bg-zinc-800 rounded-md">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ item.reasoning }}</p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            First seen: {{ formatDate(item.first_seen) }} | Last seen: {{ formatDate(item.last_seen) }}
                                        </p>
                                    </div>
                                    <span class="ml-4 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                                        {{ item.count }} files
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Confidence Classifications -->
                <div v-if="lowConfidence.length > 0" class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                            Low Confidence Classifications (&lt; 70%)
                        </h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            These files may need manual review or prompt refinement.
                        </p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">File</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Type</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Confidence</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Reasoning</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    <tr v-for="item in lowConfidence" :key="item.file_id">
                                        <td class="px-3 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ item.filename }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ item.document_type }}</td>
                                        <td class="px-3 py-4 text-sm">
                                            <span class="text-amber-600 dark:text-amber-400 font-medium">{{ (item.confidence * 100).toFixed(2) }}%</span>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400 max-w-xs truncate">{{ item.reasoning }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ item.date }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <section aria-labelledby="validation-failures-heading" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <header class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
                        <h3 id="validation-failures-heading" class="font-semibold text-zinc-950 dark:text-zinc-100">Validation failures by extractor</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Types with repeated validation errors may need schema or prompt changes.</p>
                    </header>
                    <div v-if="validationFailures.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Document type</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Failures</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg. classification confidence</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">First seen</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Last seen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                <tr v-for="item in validationFailures" :key="item.type">
                                    <th scope="row" class="px-4 py-3 text-left text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ item.type }}</th>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-red-700 dark:text-red-300">{{ item.failure_count }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ (item.avg_confidence * 100).toFixed(1) }}%</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-400">{{ formatDate(item.first_failure) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-400">{{ formatDate(item.last_failure) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-8 text-center text-sm text-zinc-600 dark:text-zinc-400">No validation failures recorded.</p>
                </section>

                <section aria-labelledby="processing-timeline-heading" class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <header class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
                        <h3 id="processing-timeline-heading" class="font-semibold text-zinc-950 dark:text-zinc-100">Daily processing activity</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Volume, outcomes and average duration for the selected activity period.</p>
                    </header>
                    <div v-if="timeline.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Date</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Succeeded</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Failed</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Success rate</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Avg. time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                <tr v-for="item in timeline" :key="item.date">
                                    <th scope="row" class="px-4 py-3 text-left text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatDate(item.date) }}</th>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ item.total }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-green-700 dark:text-green-300">{{ item.success }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-red-700 dark:text-red-300">{{ item.failed }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ item.success_rate }}%</td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ item.avg_duration_ms }}ms</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-8 text-center text-sm text-zinc-600 dark:text-zinc-400">No processing activity recorded during this period.</p>
                </section>

                <!-- Failure Distribution -->
                <div v-if="failureDistribution.length > 0" class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                    <div class="px-6 py-5 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                            Failure Distribution
                        </h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Common failure patterns to prioritize fixes.
                        </p>
                    </div>
                    <div class="px-6 py-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Category</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Count</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Retryable</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">First Seen</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    <tr v-for="(item, i) in failureDistribution" :key="`${i}-${item.category}`">
                                        <td class="px-3 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ item.category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-red-600 dark:text-red-400">{{ item.count }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ item.retryable_count }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ formatDate(item.first_seen) }}</td>
                                        <td class="px-3 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ formatDate(item.last_seen) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { useDateFormatter } from '@/Composables/useDateFormatter';
const { formatDate } = useDateFormatter();
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    stats: Object,
    documentTypes: Array,
    qualityMetrics: Object,
    unknownTypes: Array,
    lowConfidence: Array,
    validationFailures: Array,
    failureDistribution: Array,
    timeline: Array,
    current_days: Number,
});

const selectedDays = ref(props.current_days);

const changePeriod = () => {
    router.get(route('analytics.processing'), { days: selectedDays.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};


</script>
