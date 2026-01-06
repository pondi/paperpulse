<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    AI Processing Analytics
                    <span class="ml-2 text-xs font-normal text-zinc-500 dark:text-zinc-400">(Admin Only)</span>
                </h2>
                <div class="flex items-center gap-x-2">
                    <select
                        v-model="selectedDays"
                        @change="changePeriod"
                        class="rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                    >
                        <option :value="7">Last 7 Days</option>
                        <option :value="14">Last 14 Days</option>
                        <option :value="30">Last 30 Days</option>
                        <option :value="90">Last 90 Days</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- Overall Stats -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Processed"
                        :value="stats.total_processed"
                    />
                    <StatCard
                        title="Success Rate"
                        :value="stats.success_rate + '%'"
                        :subtitle="`${stats.total_success} succeeded, ${stats.total_failed} failed`"
                    />
                    <StatCard
                        title="Avg Confidence"
                        :value="(stats.avg_confidence * 100).toFixed(2) + '%'"
                    />
                    <StatCard
                        title="Avg Processing Time"
                        :value="stats.avg_duration_ms + 'ms'"
                    />
                </div>

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
                                    <tr v-for="type in documentTypes" :key="type.type">
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
                                    <tr v-for="item in failureDistribution" :key="item.category">
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
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
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

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<script>
// Stat Card Component
import { defineComponent } from 'vue';

export const StatCard = defineComponent({
    props: {
        title: String,
        value: [String, Number],
        subtitle: String,
    },
    template: `
        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400 truncate">{{ title }}</dt>
                <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ value }}</dd>
                <dd v-if="subtitle" class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ subtitle }}</dd>
            </div>
        </div>
    `,
});
</script>
