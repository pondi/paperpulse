<template>
    <AuthenticatedLayout>
        <Head :title="reportTitle" />
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-100 leading-tight">{{ reportTitle }}</h2>
        </template>

        <div class="py-12">
            <div class="report-content mx-auto w-full min-w-0 max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Tab Navigation + Period Selector -->
                <div class="report-controls mb-6 flex min-w-0 flex-col items-stretch gap-4 sm:flex-row sm:items-center">
                    <div role="group" aria-label="Report type" class="flex w-full min-w-0 max-w-full gap-1 overflow-x-auto rounded-lg border border-amber-200 bg-amber-50 p-1 dark:border-zinc-700 dark:bg-zinc-800 sm:flex-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="changeTab(tab.key)"
                            :aria-pressed="activeTab === tab.key"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium whitespace-nowrap transition-all duration-200',
                                activeTab === tab.key
                                    ? 'bg-white dark:bg-amber-900/30 text-amber-800 dark:text-amber-400 shadow-xs'
                                    : 'text-zinc-500 dark:text-zinc-400 hover:text-amber-700 dark:hover:text-amber-300 hover:bg-amber-100/50 dark:hover:bg-zinc-700'
                            ]"
                        >
                            {{ tab.label }}
                            <span
                                v-if="overview_counts[tab.countKey] !== undefined"
                                class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full"
                                :class="activeTab === tab.key
                                    ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
                                    : 'bg-amber-100/50 dark:bg-zinc-600 text-zinc-500 dark:text-zinc-400'"
                            >
                                {{ overview_counts[tab.countKey] }}
                            </span>
                        </button>
                    </div>
                    <select
                        v-model="selectedPeriod"
                        @change="updateParams"
                        class="rounded-md border-amber-200 dark:border-zinc-700 bg-amber-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-xs focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                    >
                        <option value="all">All Time</option>
                        <option value="month">Past Month</option>
                        <option value="quarter">Past Quarter</option>
                        <option value="year">Past Year</option>
                    </select>
                    <button type="button" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-xs hover:bg-zinc-50 focus:outline-hidden focus:ring-2 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" @click="printReport">Print report</button>
                </div>

                <header class="report-heading mb-8 rounded-lg border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ reportTitle }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ reportPeriodLabel }} · {{ reportDateRange }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Financial totals are shown in {{ summary_currency }} using historical exchange rates where available.</p>
                </header>

                <p v-if="reportLoading" role="status" class="rounded-lg bg-white p-6 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">Loading report…</p>
                <div v-else-if="reportError" role="alert" class="rounded-lg bg-white p-6 text-red-700 dark:bg-zinc-800 dark:text-red-400">
                    <p>{{ reportError }}</p>
                    <button type="button" @click="updateParams" class="mt-3 underline">Try again</button>
                </div>
                <template v-else>
                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'">
                    <!-- Financial Summary -->
                    <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div class="rounded-lg bg-white px-4 py-5 shadow-sm dark:bg-zinc-800 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Receipt Spending</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.receipts, summary_currency) }}</dd>
                        </div>
                        <div class="rounded-lg bg-white px-4 py-5 shadow-sm dark:bg-zinc-800 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Total</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.invoices, summary_currency) }}</dd>
                        </div>
                        <div class="rounded-lg bg-white px-4 py-5 shadow-sm dark:bg-zinc-800 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Contract Value</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.contracts, summary_currency) }}</dd>
                        </div>
                    </div>
                    <!-- Coverage summary -->
                    <div class="mb-8 overflow-hidden rounded-lg bg-white shadow-sm dark:bg-zinc-800">
                        <div class="border-b border-zinc-200 px-4 py-4 dark:border-zinc-700 sm:px-6">
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">Report coverage</h3>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Record counts across your workspace. Select a report to view its details.</p>
                        </div>
                        <div class="overflow-x-auto" tabindex="0" aria-label="Scrollable report coverage table">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead><tr class="text-left text-zinc-500 dark:text-zinc-400"><th scope="col" class="px-4 py-3 font-medium">Record type</th><th scope="col" class="px-4 py-3 text-right font-medium">Records</th><th scope="col" class="px-4 py-3 text-right font-medium">Report</th></tr></thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                    <tr v-for="entity in entityCards" :key="entity.key">
                                        <th scope="row" class="px-4 py-3 text-left font-medium text-zinc-800 dark:text-zinc-200">{{ entity.label }}</th>
                                        <td class="px-4 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ overview_counts[entity.key] }}</td>
                                        <td class="px-4 py-3 text-right"><button v-if="entity.tab" type="button" class="font-medium text-amber-700 underline hover:text-amber-900 dark:text-amber-400" @click="changeTab(entity.tab)">Open {{ entity.reportLabel }} report</button><span v-else class="text-zinc-500 dark:text-zinc-400">Coverage only</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Expiring Soon Alerts -->
                    <div v-if="totalExpiring > 0" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-8">
                        <h3 class="text-sm font-bold text-amber-800 dark:text-amber-300 mb-2">Expiring Within 30 Days</h3>
                        <div class="flex gap-6 text-sm">
                            <span v-if="tab_data.expiring_soon?.vouchers" class="text-amber-700 dark:text-amber-400">
                                {{ tab_data.expiring_soon.vouchers }} voucher{{ tab_data.expiring_soon.vouchers !== 1 ? 's' : '' }}
                            </span>
                            <span v-if="tab_data.expiring_soon?.warranties" class="text-amber-700 dark:text-amber-400">
                                {{ tab_data.expiring_soon.warranties }} warrant{{ tab_data.expiring_soon.warranties !== 1 ? 'ies' : 'y' }}
                            </span>
                            <span v-if="tab_data.expiring_soon?.contracts" class="text-amber-700 dark:text-amber-400">
                                {{ tab_data.expiring_soon.contracts }} contract{{ tab_data.expiring_soon.contracts !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>

                    <!-- Combined Monthly Trend -->
                    <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Receipt and invoice totals by Observed Month</h3>
                            <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">Only months with records are shown. Missing months are omitted; spacing does not represent elapsed time.</p>
                            <div v-if="tab_data.monthly_trend?.length > 0" class="relative h-64 min-w-0 max-w-full">
                                <canvas ref="overviewChart" role="img" aria-label="Monthly receipt and invoice totals in the selected reporting currency"></canvas>
                            </div>
                            <div v-else class="text-zinc-500 dark:text-zinc-400 text-center py-8">No data available</div>
                        </div>
                    </div>
                </div>

                <!-- Receipts Tab -->
                <div v-if="activeTab === 'receipts'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <StatCard label="Receipts" :value="tab_data.stats?.count" />
                        <StatCard label="Total Spending" :value="formatCurrency(tab_data.stats?.total)" />
                        <StatCard label="Avg Receipt" :value="formatCurrency(tab_data.stats?.avg)" />
                        <StatCard label="Total Tax" :value="formatCurrency(tab_data.stats?.tax)" />
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Spending by Category -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Category</h3>
                                <div v-if="tab_data.spending_by_category?.length > 0" class="space-y-3">
                                    <div v-for="(category, i) in tab_data.spending_by_category" :key="`${i}-${category.category}`" class="relative">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ category.category }}</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(category.total) }}</span>
                                        </div>
                                        <div class="w-full bg-amber-200 dark:bg-zinc-700 rounded-full h-2">
                                            <div class="bg-zinc-900 dark:bg-amber-600 h-2 rounded-full" :style="{ width: getPercentage(category.total, tab_data.spending_by_category) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Top Merchants -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Merchants</h3>
                                <div v-if="tab_data.top_merchants?.length > 0" class="space-y-3">
                                    <div v-for="(merchant, i) in tab_data.top_merchants.slice(0, 5)" :key="`${i}-${merchant.merchant}`">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ merchant.merchant }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ merchant.receipt_count }} receipts</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(merchant.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Monthly Trend -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Observed Month</h3>
                                <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">Only months with records are shown. Missing months are omitted; spacing does not represent elapsed time.</p>
                            <div v-if="tab_data.monthly_trend?.length > 0" class="relative h-64 min-w-0 max-w-full">
                                <canvas ref="receiptChart" role="img" aria-label="Monthly receipt spending in the selected reporting currency"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Recent Receipts -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Recent Receipts</h3>
                                <div v-if="tab_data.recent_receipts?.length > 0" class="space-y-3">
                                    <Link v-for="receipt in tab_data.recent_receipts" :key="receipt.id" :href="route('receipts.show', receipt.id)"
                                        class="block hover:bg-amber-50 dark:hover:bg-zinc-700 -mx-2 px-2 py-2 rounded-md"
                                    >
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ receipt.merchant }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ receipt.date }}</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(receipt.total, receipt.currency) }}</div>
                                        </div>
                                    </Link>
                                </div>
                                <div v-else class="text-zinc-500 dark:text-zinc-400 text-center py-8">No recent receipts</div>
                            </div>
                        </div>

                        <!-- Spending by Day of Week -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Day of Week</h3>
                                <div v-if="tab_data.day_of_week?.length > 0" class="space-y-3">
                                    <div v-for="(day, i) in tab_data.day_of_week" :key="`${i}-${day.day}`" class="relative">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ day.day }}</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(day.total) }}</span>
                                        </div>
                                        <div class="w-full bg-amber-200 dark:bg-zinc-700 rounded-full h-2">
                                            <div class="bg-zinc-900 dark:bg-amber-600 h-2 rounded-full" :style="{ width: getDayOfWeekPercentage(day.total) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoices Tab -->
                <div v-if="activeTab === 'invoices'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <StatCard label="Total Invoices" :value="tab_data.stats?.count" />
                        <StatCard label="Total Amount" :value="formatCurrency(tab_data.stats?.total)" />
                        <StatCard label="Avg Invoice" :value="formatCurrency(tab_data.stats?.avg)" />
                        <StatCard label="Recipients" :value="tab_data.stats?.recipient_count" />
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Top Recipients -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Recipients</h3>
                                <div v-if="tab_data.top_recipients?.length > 0" class="space-y-3">
                                    <div v-for="(recipient, i) in tab_data.top_recipients" :key="`${i}-${recipient.recipient}`">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ recipient.recipient }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ recipient.invoice_count }} invoice{{ recipient.invoice_count !== 1 ? 's' : '' }}</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(recipient.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Top Vendors -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Vendors</h3>
                                <div v-if="tab_data.top_vendors?.length > 0" class="space-y-3">
                                    <div v-for="(vendor, i) in tab_data.top_vendors.slice(0, 5)" :key="`${i}-${vendor.vendor}`">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ vendor.vendor }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ vendor.invoice_count }} invoices</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(vendor.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Monthly Trend -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Invoices by Observed Month</h3>
                                <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">Only months with records are shown. Missing months are omitted; spacing does not represent elapsed time.</p>
                            <div v-if="tab_data.monthly_trend?.length > 0" class="relative h-64 min-w-0 max-w-full">
                                    <canvas ref="invoiceChart" role="img" aria-label="Monthly invoice totals in the selected reporting currency"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banking Tab -->
                <div v-if="activeTab === 'banking'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <StatCard label="Statements" :value="tab_data.stats?.statement_count" />
                        <StatCard label="Transactions" :value="tab_data.stats?.transaction_count" />
                        <StatCard label="Money In" :value="formatCurrency(tab_data.stats?.total_credits)" />
                        <StatCard label="Net Cash Flow" :value="formatCurrency(tab_data.stats?.net_flow)" :alert="tab_data.stats?.net_flow < 0" />
                    </div>

                    <div v-if="!tab_data.stats?.transaction_count" class="rounded-lg bg-white p-6 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        <h3 class="text-lg font-medium">{{ !overview_counts.bank_statements ? 'No bank statements uploaded' : selectedPeriod === 'all' ? 'No bank transactions available' : 'No bank transactions in the selected period' }}</h3>
                        <p class="mt-2 text-sm">{{ !overview_counts.bank_statements ? 'Upload a bank statement to report on cash flow, categories, balances and counterparties.' : selectedPeriod === 'all' ? 'Your bank statements have no dated transactions to report.' : 'Choose All Time or another period to see your transactions.' }}</p>
                        <Link :href="route('documents.upload')" class="mt-4 inline-block text-amber-700 underline dark:text-amber-400">Upload a bank statement</Link>
                    </div>
                    <div v-else class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Money In vs Money Out -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Money In vs Money Out</h3>
                                <div v-if="tab_data.stats?.total_credits > 0 || tab_data.stats?.total_debits > 0" class="space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-green-600 dark:text-green-400 font-medium">Credits (Money In)</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.stats?.total_credits) }}</span>
                                        </div>
                                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                                            <div class="bg-green-500 h-3 rounded-full" :style="{ width: getCashFlowPercent('credits') + '%' }"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-red-600 dark:text-red-400 font-medium">Debits (Money Out)</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.stats?.total_debits) }}</span>
                                        </div>
                                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                                            <div class="bg-red-500 h-3 rounded-full" :style="{ width: getCashFlowPercent('debits') + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Spending by Category -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Category</h3>
                                <div v-if="tab_data.spending_by_category?.length > 0" class="space-y-3">
                                    <div v-for="(cat, i) in tab_data.spending_by_category" :key="`${i}-${cat.category}`" class="relative">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400 capitalize">{{ cat.category }}<span v-if="cat.subcategory"> · {{ cat.subcategory }}</span></span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(cat.total) }}</span>
                                        </div>
                                        <div class="w-full bg-amber-200 dark:bg-zinc-700 rounded-full h-2">
                                            <div class="bg-zinc-900 dark:bg-amber-600 h-2 rounded-full" :style="{ width: getPercentage(cat.total, tab_data.spending_by_category) + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Balance Trend -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Account Balance Trend</h3>
                                <div v-if="tab_data.balance_trend?.length > 0" class="relative h-64 min-w-0 max-w-full">
                                    <canvas ref="bankingChart" role="img" aria-label="Opening and closing balances for each bank account in the selected reporting currency"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Top Counterparties -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Counterparties by Spending</h3>
                                <div v-if="tab_data.top_counterparties?.length > 0" class="space-y-3">
                                    <div v-for="(party, i) in tab_data.top_counterparties" :key="`${i}-${party.name}`">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ party.name }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ party.transaction_count }} transactions</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(party.total) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contracts Tab -->
                <div v-if="activeTab === 'contracts'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                        <StatCard label="Total Contracts" :value="tab_data.stats?.total" />
                        <StatCard label="Active" :value="tab_data.stats?.active" />
                        <StatCard label="Expired" :value="tab_data.stats?.expired" :alert="tab_data.stats?.expired > 0" />
                        <StatCard label="Total Value" :value="formatCurrency(tab_data.stats?.total_value)" />
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Status Breakdown -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Status Breakdown</h3>
                                <div v-if="tab_data.status_breakdown?.length > 0" class="space-y-3">
                                    <div v-for="(s, i) in tab_data.status_breakdown" :key="`${i}-${s.status}`" class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-3 h-3 rounded-full" :class="contractStatusColor(s.status)"></span>
                                            <span class="text-sm text-zinc-700 dark:text-zinc-300 capitalize">{{ s.status }}</span>
                                        </div>
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ s.count }}</span>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Type Distribution -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Contract Types</h3>
                                <div v-if="tab_data.type_distribution?.length > 0" class="space-y-3">
                                    <div v-for="(t, i) in tab_data.type_distribution" :key="`${i}-${t.type}`">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 capitalize">{{ t.type }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ t.count }} contract{{ t.count !== 1 ? 's' : '' }}</div>
                                            </div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(t.value) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Expiring Soon -->
                        <div v-if="tab_data.expiring_soon?.length > 0" class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-amber-600 dark:text-amber-400 mb-4">Expiring Within 90 Days</h3>
                                <p class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">Values remain in each contract’s original currency.</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                                <th class="pb-2 font-medium">Contract</th>
                                                <th class="pb-2 font-medium">Type</th>
                                                <th class="pb-2 font-medium text-right">Original value</th>
                                                <th class="pb-2 font-medium text-right">Expiry Date</th>
                                                <th class="pb-2 font-medium text-right">Days Left</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                            <tr v-for="c in tab_data.expiring_soon" :key="c.id">
                                                <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ c.title }}</td>
                                                <td class="py-2 text-zinc-700 dark:text-zinc-300 capitalize">{{ c.type }}</td>
                                                <td class="py-2 text-right font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(c.value, c.currency) }}</td>
                                                <td class="py-2 text-right text-zinc-600 dark:text-zinc-400">{{ c.expiry_date }}</td>
                                                <td class="py-2 text-right font-medium" :class="c.days_until_expiry <= 14 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'">
                                                    {{ c.days_until_expiry }}d
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents Tab -->
                <div v-if="activeTab === 'documents'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 mb-8">
                        <StatCard label="Total Documents" :value="tab_data.stats?.total" />
                        <StatCard label="Total Pages" :value="tab_data.stats?.total_pages" />
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Type Distribution -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Document Types</h3>
                                <div v-if="tab_data.type_distribution?.length > 0" class="space-y-3">
                                    <div v-for="(t, i) in tab_data.type_distribution" :key="`${i}-${t.type}`" class="relative">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400 capitalize">{{ t.type }}</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ t.count }}</span>
                                        </div>
                                        <div class="w-full bg-amber-200 dark:bg-zinc-700 rounded-full h-2">
                                            <div class="bg-zinc-900 dark:bg-amber-600 h-2 rounded-full"
                                                :style="{ width: getCountPercentage(t.count, tab_data.type_distribution) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Monthly Trend -->
                        <div class="bg-white dark:bg-zinc-800 shadow-sm rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Uploads by Observed Month</h3>
                                <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">Only months with records are shown. Missing months are omitted; spacing does not represent elapsed time.</p>
                            <div v-if="tab_data.monthly_trend?.length > 0" class="relative h-64 min-w-0 max-w-full">
                                    <canvas ref="documentChart" role="img" aria-label="Document uploads by observed month"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>
                    </div>
                </div>
                </template>
                <details v-if="!reportLoading && !reportError && chartTable.rows.length" class="chart-data mt-8 rounded-lg border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800" open>
                    <summary class="cursor-pointer font-semibold text-zinc-900 dark:text-zinc-100">{{ chartTable.title }} data</summary>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Values use {{ summary_currency }} where applicable.</p>
                    <div class="mt-3 overflow-x-auto" tabindex="0" :aria-label="`Scrollable ${chartTable.title} data table`">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead><tr class="text-left text-zinc-500 dark:text-zinc-400"><th v-for="column in chartTable.columns" :key="column.key" scope="col" class="px-3 py-2 font-medium">{{ column.label }}</th></tr></thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700"><tr v-for="(row, rowIndex) in chartTable.rows" :key="rowIndex"><td v-for="column in chartTable.columns" :key="column.key" class="px-3 py-2 text-zinc-800 dark:text-zinc-200">{{ column.currency ? formatCurrency(row[column.key], summary_currency) : row[column.key] ?? 'Unavailable' }}</td></tr></tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { h, ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Chart from 'chart.js/auto';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const StatCard = {
    props: {
        label: String,
        value: [String, Number],
        alert: { type: Boolean, default: false },
    },
    render() {
        return h('div', { class: 'bg-white dark:bg-zinc-800 overflow-hidden shadow-sm rounded-lg' }, [
            h('div', { class: 'px-4 py-5 sm:p-6' }, [
                h('dt', { class: 'text-sm font-bold text-zinc-500 dark:text-zinc-400 truncate' }, this.label),
                h('dd', { class: ['mt-1 text-3xl font-semibold', this.alert ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100'] }, this.value ?? 0),
            ]),
        ]);
    },
};

const EmptyState = {
    render: () => h('p', { class: 'text-zinc-500 dark:text-zinc-400 text-center py-8' }, 'No data available'),
};

const props = defineProps({
    overview_counts: Object,
    tab_data: Object,
    current_period: String,
    current_tab: String,
    summary_currency: { type: String, default: 'NOK' },
    report_context: Object,
});

const selectedPeriod = ref(props.current_period);
const activeTab = ref(props.current_tab);
const reportLoading = ref(false);
const reportError = ref('');
const { formatCurrency } = useDateFormatter();

// Chart refs
const overviewChart = ref(null);
const receiptChart = ref(null);
const invoiceChart = ref(null);
const bankingChart = ref(null);
const documentChart = ref(null);

let chartInstances = {};

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'receipts', label: 'Receipts', countKey: 'receipts' },
    { key: 'invoices', label: 'Invoices', countKey: 'invoices' },
    { key: 'banking', label: 'Banking', countKey: 'bank_statements' },
    { key: 'contracts', label: 'Contracts', countKey: 'contracts' },
    { key: 'documents', label: 'Documents', countKey: 'documents' },
];

const entityCards = [
    { key: 'receipts', label: 'Receipts', tab: 'receipts', reportLabel: 'receipts' },
    { key: 'invoices', label: 'Invoices', tab: 'invoices', reportLabel: 'invoices' },
    { key: 'contracts', label: 'Contracts', tab: 'contracts', reportLabel: 'contracts' },
    { key: 'bank_statements', label: 'Statements', tab: 'banking', reportLabel: 'banking' },
    { key: 'documents', label: 'Documents', tab: 'documents', reportLabel: 'documents' },
    { key: 'vouchers', label: 'Vouchers', tab: null },
    { key: 'warranties', label: 'Warranties', tab: null },
];

const tabLabel = computed(() => tabs.find(tab => tab.key === activeTab.value)?.label ?? 'Overview');
const reportTitle = computed(() => activeTab.value === 'overview' ? 'Reports overview' : `${tabLabel.value} report`);
const reportPeriodLabel = computed(() => props.report_context?.period_label ?? 'All time');
const reportDateRange = computed(() => props.report_context?.start_date
    ? `${props.report_context.start_date} to ${props.report_context.end_date}`
    : `All recorded dates through ${props.report_context?.end_date ?? ''}`);
const chartTable = computed(() => {
    const data = props.tab_data ?? {};
    if (activeTab.value === 'overview') return { title: 'Monthly receipt and invoice totals', columns: [{ key: 'month', label: 'Observed month' }, { key: 'receipts', label: 'Receipts', currency: true }, { key: 'invoices', label: 'Invoices', currency: true }], rows: data.monthly_trend ?? [] };
    if (activeTab.value === 'receipts') return { title: 'Monthly receipt totals', columns: [{ key: 'month', label: 'Observed month' }, { key: 'receipt_count', label: 'Receipts' }, { key: 'total', label: 'Total', currency: true }], rows: data.monthly_trend ?? [] };
    if (activeTab.value === 'invoices') return { title: 'Monthly invoice totals', columns: [{ key: 'month', label: 'Observed month' }, { key: 'invoice_count', label: 'Invoices' }, { key: 'total', label: 'Total', currency: true }], rows: data.monthly_trend ?? [] };
    if (activeTab.value === 'banking') return { title: 'Account balance trend', columns: [{ key: 'date', label: 'Date' }, { key: 'series_label', label: 'Bank account' }, { key: 'opening', label: 'Opening balance', currency: true }, { key: 'closing', label: 'Closing balance', currency: true }], rows: data.balance_trend ?? [] };
    if (activeTab.value === 'documents') return { title: 'Monthly document uploads', columns: [{ key: 'month', label: 'Observed month' }, { key: 'count', label: 'Documents' }], rows: data.monthly_trend ?? [] };
    return { title: '', columns: [], rows: [] };
});

const totalExpiring = computed(() => {
    const e = props.tab_data?.expiring_soon;
    if (!e) return 0;
    return (e.vouchers || 0) + (e.warranties || 0) + (e.contracts || 0);
});

const changeTab = (tab) => {
    if (!tab) return;
    activeTab.value = tab;
    updateParams();
};

const printReport = () => window.print();

const updateParams = () => {
    router.visit(route('analytics.index', { tab: activeTab.value, period: selectedPeriod.value }), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => { reportLoading.value = true; reportError.value = ''; },
        onFinish: () => { reportLoading.value = false; initCharts(); },
        onHttpException: () => {
            reportError.value = 'The report could not be loaded. Try again.';
            return false;
        },
        onNetworkError: () => { reportError.value = 'The report could not be loaded. Check your connection and try again.'; },
    });
};

const getPercentage = (amount, items) => {
    const total = items.reduce((sum, item) => sum + item.total, 0);
    return total > 0 ? (amount / total) * 100 : 0;
};

const getCountPercentage = (count, items) => {
    const max = Math.max(...items.map(i => i.count));
    return max > 0 ? (count / max) * 100 : 0;
};

const getCashFlowPercent = (type) => {
    const credits = props.tab_data?.stats?.total_credits || 0;
    const debits = props.tab_data?.stats?.total_debits || 0;
    const max = Math.max(credits, debits);
    if (max === 0) return 0;
    return type === 'credits' ? (credits / max) * 100 : (debits / max) * 100;
};

const getDayOfWeekPercentage = (total) => {
    const max = Math.max(...(props.tab_data?.day_of_week?.map(d => d.total) || [0]));
    return max > 0 ? (total / max) * 100 : 0;
};

const contractStatusColor = (status) => {
    const colors = {
        active: 'bg-green-500',
        expired: 'bg-red-500',
        draft: 'bg-zinc-400',
        terminated: 'bg-red-700',
    };
    return colors[status] || 'bg-zinc-400';
};

const destroyChart = (key) => {
    if (chartInstances[key]) {
        chartInstances[key].destroy();
        delete chartInstances[key];
    }
};

const destroyAllCharts = () => {
    Object.keys(chartInstances).forEach(destroyChart);
};

const initCharts = () => {
    destroyAllCharts();
    nextTick(() => {
        try {
        if (activeTab.value === 'overview' && overviewChart.value && props.tab_data?.monthly_trend?.length > 0) {
            chartInstances.overview = new Chart(overviewChart.value.getContext('2d'), {
                type: 'line',
                data: {
                    labels: props.tab_data.monthly_trend.map(i => i.month),
                    datasets: [
                        {
                            label: 'Receipts',
                            data: props.tab_data.monthly_trend.map(i => i.receipts),
                            borderColor: 'rgb(245, 158, 11)',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.1,
                        },
                        {
                            label: 'Invoices',
                            data: props.tab_data.monthly_trend.map(i => i.invoices),
                            borderColor: 'rgb(79, 70, 229)',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.1,
                        },
                    ],
                },
                options: chartOptions(),
            });
        }

        if (activeTab.value === 'receipts' && receiptChart.value && props.tab_data?.monthly_trend?.length > 0) {
            chartInstances.receipts = new Chart(receiptChart.value.getContext('2d'), {
                type: 'line',
                data: {
                    labels: props.tab_data.monthly_trend.map(i => i.month),
                    datasets: [{
                        label: 'Monthly Spending',
                        data: props.tab_data.monthly_trend.map(i => i.total),
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.1,
                    }],
                },
                options: chartOptions(),
            });
        }

        if (activeTab.value === 'invoices' && invoiceChart.value && props.tab_data?.monthly_trend?.length > 0) {
            chartInstances.invoices = new Chart(invoiceChart.value.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: props.tab_data.monthly_trend.map(i => i.month),
                    datasets: [{
                        label: 'Invoice Total',
                        data: props.tab_data.monthly_trend.map(i => i.total),
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        borderRadius: 4,
                    }],
                },
                options: chartOptions(),
            });
        }

        if (activeTab.value === 'banking' && bankingChart.value && props.tab_data?.balance_trend?.length > 0) {
            const balanceRows = props.tab_data.balance_trend;
            const series = [...new Map(balanceRows.map(row => [row.series_id, row.series_label])).entries()];
            const colors = ['rgb(34, 197, 94)', 'rgb(79, 70, 229)', 'rgb(245, 158, 11)', 'rgb(14, 165, 233)', 'rgb(236, 72, 153)'];
            chartInstances.banking = new Chart(bankingChart.value.getContext('2d'), {
                type: 'line',
                data: {
                    labels: balanceRows.map(row => row.date),
                    datasets: series.flatMap(([id, label], index) => [
                        { label: `${label} · opening`, data: balanceRows.map(row => row.series_id === id ? row.opening : null), borderColor: colors[index % colors.length], borderDash: [5, 5], tension: 0.1, pointRadius: 2 },
                        { label: `${label} · closing`, data: balanceRows.map(row => row.series_id === id ? row.closing : null), borderColor: colors[index % colors.length], backgroundColor: colors[index % colors.length].replace('rgb', 'rgba').replace(')', ', 0.1)'), fill: false, tension: 0.1, pointRadius: 3 },
                    ]),
                },
                options: chartOptions(),
            });
        }

        if (activeTab.value === 'documents' && documentChart.value && props.tab_data?.monthly_trend?.length > 0) {
            chartInstances.documents = new Chart(documentChart.value.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: props.tab_data.monthly_trend.map(i => i.month),
                    datasets: [{
                        label: 'Documents',
                        data: props.tab_data.monthly_trend.map(i => i.count),
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    ...chartOptions(),
                    scales: {
                        ...chartOptions().scales,
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, callback: value => value },
                        },
                    },
                },
            });
        }
        } catch {
            destroyAllCharts();
            reportError.value = 'The report chart could not be displayed. Try reloading the report.';
        }
    });
};

const chartOptions = () => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 16 } },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    return formatCurrency(value, props.summary_currency);
                },
            },
        },
    },
});

onMounted(() => {
    initCharts();
});

onBeforeUnmount(destroyAllCharts);

watch(() => props.current_tab, () => {
    activeTab.value = props.current_tab;
});
</script>

<style scoped>
.report-content canvas {
    max-width: 100%;
}

@media print {
    :global(.app-toolbar),
    :global(.workspace-navigation),
    :global(aside),
    .report-controls {
        display: none !important;
    }

    :global(body),
    :global(#app) {
        background: #fff !important;
        color: #111827 !important;
    }

    :global(.dark [class~="dark:bg-zinc-800"]),
    :global(.dark [class~="dark:bg-zinc-900"]) {
        background: #fff !important;
    }

    :global(.dark [class~="dark:text-zinc-100"]),
    :global(.dark [class~="dark:text-zinc-200"]),
    :global(.dark [class~="dark:text-zinc-300"]) {
        color: #111827 !important;
    }

    :global(main#main-content) {
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .report-content {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .report-heading,
    .chart-data,
    .bg-white {
        color: #111827 !important;
        background: #fff !important;
        box-shadow: none !important;
        break-inside: avoid;
    }

    .chart-data > summary {
        display: none;
    }

    .chart-data > div {
        display: block !important;
        overflow: visible !important;
    }

    canvas {
        max-height: 240px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        color: #111827 !important;
        border: 1px solid #d1d5db;
    }
}
</style>
