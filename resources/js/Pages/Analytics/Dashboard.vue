<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Analytics Dashboard</h2>
                <div class="flex items-center gap-x-2">
                    <select
                        v-model="selectedPeriod"
                        @change="changePeriod"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="month">Last Month</option>
                        <option value="quarter">Last Quarter</option>
                        <option value="year">Last Year</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Receipts</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ stats.total_receipts }}</dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Spending</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ formatCurrency(stats.total_amount) }}</dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">Period Spending</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ formatCurrency(stats.period_amount) }}</dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">Avg Receipt Value</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ formatCurrency(stats.avg_receipt_value) }}</dd>
                        </div>
                    </div>
                </div>

                <!-- Charts Grid -->
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <!-- Spending by Category -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Spending by Category</h3>
                            <div v-if="charts.spending_by_category.length > 0" class="space-y-3">
                                <div v-for="category in charts.spending_by_category" :key="category.category" class="relative">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">{{ category.category }}</span>
                                        <span class="font-medium">{{ formatCurrency(category.total) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-indigo-600 h-2 rounded-full"
                                            :style="{ width: getCategoryPercentage(category.total) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-500 text-center py-8">
                                No data available
                            </div>
                        </div>
                    </div>

                    <!-- Top Merchants -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Top Merchants</h3>
                            <div v-if="charts.top_merchants.length > 0" class="space-y-3">
                                <div v-for="merchant in charts.top_merchants.slice(0, 5)" :key="merchant.merchant">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ merchant.merchant }}</div>
                                            <div class="text-xs text-gray-500">{{ merchant.receipt_count }} receipts</div>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ formatCurrency(merchant.total) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-500 text-center py-8">
                                No data available
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Trend -->
                    <div class="bg-white overflow-hidden shadow rounded-lg lg:col-span-2">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Monthly Spending Trend</h3>
                            <div v-if="charts.monthly_trend.length > 0" class="h-64">
                                <canvas ref="trendChart"></canvas>
                            </div>
                            <div v-else class="text-gray-500 text-center py-8">
                                No data available
                            </div>
                        </div>
                    </div>

                    <!-- Recent Receipts -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Receipts</h3>
                            <div v-if="recent_receipts.length > 0" class="space-y-3">
                                <Link
                                    v-for="receipt in recent_receipts"
                                    :key="receipt.id"
                                    :href="route('receipts.show', receipt.id)"
                                    class="block hover:bg-gray-50 -mx-2 px-2 py-2 rounded-md"
                                >
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ receipt.merchant }}</div>
                                            <div class="text-xs text-gray-500">{{ receipt.date }}</div>
                                        </div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ formatCurrency(receipt.total) }}
                                        </div>
                                    </div>
                                </Link>
                            </div>
                            <div v-else class="text-gray-500 text-center py-8">
                                No recent receipts
                            </div>
                        </div>
                    </div>

                    <!-- Top Items -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Most Purchased Items</h3>
                            <div v-if="charts.top_items.length > 0" class="space-y-2">
                                <div v-for="item in charts.top_items.slice(0, 10)" :key="item.name" class="text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 truncate flex-1 mr-2">{{ item.name }}</span>
                                        <span class="text-gray-900 font-medium">{{ item.purchases }}x</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-gray-500 text-center py-8">
                                No data available
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Chart from 'chart.js/auto';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
    stats: Object,
    charts: Object,
    recent_receipts: Array,
    current_period: String,
});

const selectedPeriod = ref(props.current_period);
const trendChart = ref(null);
let chartInstance = null;

const page = usePage();
const { formatCurrency } = useDateFormatter();

const getCategoryPercentage = (amount) => {
    const total = props.charts.spending_by_category.reduce((sum, cat) => sum + cat.total, 0);
    return total > 0 ? (amount / total) * 100 : 0;
};

const changePeriod = () => {
    router.visit(route('analytics.index', { period: selectedPeriod.value }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const initTrendChart = () => {
    if (!trendChart.value || props.charts.monthly_trend.length === 0) return;

    if (chartInstance) {
        chartInstance.destroy();
    }

    const ctx = trendChart.value.getContext('2d');
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.charts.monthly_trend.map(item => item.month),
            datasets: [{
                label: 'Monthly Spending',
                data: props.charts.monthly_trend.map(item => item.total),
                borderColor: 'rgb(79, 70, 229)',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
};

onMounted(() => {
    nextTick(() => {
        initTrendChart();
    });
});
</script>