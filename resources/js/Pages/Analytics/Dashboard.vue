<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-100 leading-tight">Analytics Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Tab Navigation + Period Selector -->
                <div class="flex items-center gap-4 mb-8">
                    <div class="flex gap-1 bg-amber-50 dark:bg-zinc-800 border border-amber-200 dark:border-zinc-700 rounded-lg p-1 overflow-x-auto flex-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="changeTab(tab.key)"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium whitespace-nowrap transition-all duration-200',
                                activeTab === tab.key
                                    ? 'bg-white dark:bg-amber-900/30 text-amber-800 dark:text-amber-400 shadow-sm'
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
                        class="rounded-md border-amber-200 dark:border-zinc-700 bg-amber-50 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                    >
                        <option value="all">All Time</option>
                        <option value="month">Last Month</option>
                        <option value="quarter">Last Quarter</option>
                        <option value="year">Last Year</option>
                    </select>
                </div>

                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'">
                    <!-- Entity Count Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
                        <div v-for="entity in entityCards" :key="entity.key"
                            class="bg-white dark:bg-zinc-800 shadow rounded-lg px-4 py-4 cursor-pointer hover:ring-2 hover:ring-amber-500/50 transition-all"
                            @click="changeTab(entity.tab)"
                        >
                            <dt class="text-xs font-bold text-zinc-500 dark:text-zinc-400 truncate">{{ entity.label }}</dt>
                            <dd class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ overview_counts[entity.key] }}</dd>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg px-4 py-5 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Receipt Spending</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.receipts) }}</dd>
                        </div>
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg px-4 py-5 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Total</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.invoices) }}</dd>
                        </div>
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg px-4 py-5 sm:p-6">
                            <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Contract Value</dt>
                            <dd class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.financial_totals?.contracts) }}</dd>
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
                    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Monthly Spending Trend</h3>
                            <div v-if="tab_data.monthly_trend?.length > 0" class="h-64">
                                <canvas ref="overviewChart"></canvas>
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Category</h3>
                                <div v-if="tab_data.spending_by_category?.length > 0" class="space-y-3">
                                    <div v-for="category in tab_data.spending_by_category" :key="category.category" class="relative">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Merchants</h3>
                                <div v-if="tab_data.top_merchants?.length > 0" class="space-y-3">
                                    <div v-for="merchant in tab_data.top_merchants.slice(0, 5)" :key="merchant.merchant">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Monthly Spending Trend</h3>
                                <div v-if="tab_data.monthly_trend?.length > 0" class="h-64">
                                    <canvas ref="receiptChart"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Recent Receipts -->
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
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
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(receipt.total) }}</div>
                                        </div>
                                    </Link>
                                </div>
                                <div v-else class="text-zinc-500 dark:text-zinc-400 text-center py-8">No recent receipts</div>
                            </div>
                        </div>

                        <!-- Spending by Day of Week -->
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Day of Week</h3>
                                <div v-if="tab_data.day_of_week?.length > 0" class="space-y-3">
                                    <div v-for="day in tab_data.day_of_week" :key="day.day" class="relative">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Recipients</h3>
                                <div v-if="tab_data.top_recipients?.length > 0" class="space-y-3">
                                    <div v-for="recipient in tab_data.top_recipients" :key="recipient.recipient">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Vendors</h3>
                                <div v-if="tab_data.top_vendors?.length > 0" class="space-y-3">
                                    <div v-for="vendor in tab_data.top_vendors.slice(0, 5)" :key="vendor.vendor">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Monthly Invoice Trend</h3>
                                <div v-if="tab_data.monthly_trend?.length > 0" class="h-64">
                                    <canvas ref="invoiceChart"></canvas>
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
                        <StatCard label="Total Credits" :value="formatCurrency(tab_data.stats?.total_credits)" />
                        <StatCard label="Net Cash Flow" :value="formatCurrency(tab_data.stats?.net_flow)" :alert="tab_data.stats?.net_flow < 0" />
                    </div>

                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                        <!-- Income vs Expenses -->
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Income vs Expenses</h3>
                                <div v-if="tab_data.stats?.total_credits > 0 || tab_data.stats?.total_debits > 0" class="space-y-4">
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-green-600 dark:text-green-400 font-medium">Credits (Income)</span>
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(tab_data.stats?.total_credits) }}</span>
                                        </div>
                                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                                            <div class="bg-green-500 h-3 rounded-full" :style="{ width: getCashFlowPercent('credits') + '%' }"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-red-600 dark:text-red-400 font-medium">Debits (Expenses)</span>
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Spending by Category</h3>
                                <div v-if="tab_data.spending_by_category?.length > 0" class="space-y-3">
                                    <div v-for="cat in tab_data.spending_by_category" :key="cat.category" class="relative">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400 capitalize">{{ cat.category }}</span>
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Account Balance Trend</h3>
                                <div v-if="tab_data.balance_trend?.length > 0" class="h-64">
                                    <canvas ref="bankingChart"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>

                        <!-- Top Counterparties -->
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Top Counterparties</h3>
                                <div v-if="tab_data.top_counterparties?.length > 0" class="space-y-3">
                                    <div v-for="party in tab_data.top_counterparties" :key="party.name">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Status Breakdown</h3>
                                <div v-if="tab_data.status_breakdown?.length > 0" class="space-y-3">
                                    <div v-for="s in tab_data.status_breakdown" :key="s.status" class="flex items-center justify-between">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Contract Types</h3>
                                <div v-if="tab_data.type_distribution?.length > 0" class="space-y-3">
                                    <div v-for="t in tab_data.type_distribution" :key="t.type">
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
                        <div v-if="tab_data.expiring_soon?.length > 0" class="bg-white dark:bg-zinc-800 shadow rounded-lg lg:col-span-2">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-amber-600 dark:text-amber-400 mb-4">Expiring Within 90 Days</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-zinc-500 dark:text-zinc-400">
                                                <th class="pb-2 font-medium">Contract</th>
                                                <th class="pb-2 font-medium">Type</th>
                                                <th class="pb-2 font-medium text-right">Value</th>
                                                <th class="pb-2 font-medium text-right">Expiry Date</th>
                                                <th class="pb-2 font-medium text-right">Days Left</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                            <tr v-for="c in tab_data.expiring_soon" :key="c.id">
                                                <td class="py-2 text-zinc-900 dark:text-zinc-100">{{ c.title }}</td>
                                                <td class="py-2 text-zinc-700 dark:text-zinc-300 capitalize">{{ c.type }}</td>
                                                <td class="py-2 text-right font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(c.value) }}</td>
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Document Types</h3>
                                <div v-if="tab_data.type_distribution?.length > 0" class="space-y-3">
                                    <div v-for="t in tab_data.type_distribution" :key="t.type" class="relative">
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
                        <div class="bg-white dark:bg-zinc-800 shadow rounded-lg">
                            <div class="px-4 py-5 sm:p-6">
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Monthly Uploads</h3>
                                <div v-if="tab_data.monthly_trend?.length > 0" class="h-64">
                                    <canvas ref="documentChart"></canvas>
                                </div>
                                <EmptyState v-else />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
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
    template: `
        <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400 truncate">{{ label }}</dt>
                <dd class="mt-1 text-3xl font-semibold" :class="alert ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100'">{{ value ?? 0 }}</dd>
            </div>
        </div>
    `,
};

const EmptyState = {
    template: `<div class="text-zinc-500 dark:text-zinc-400 text-center py-8">No data available</div>`,
};

const props = defineProps({
    overview_counts: Object,
    tab_data: Object,
    current_period: String,
    current_tab: String,
});

const selectedPeriod = ref(props.current_period);
const activeTab = ref(props.current_tab);
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
    { key: 'receipts', label: 'Receipts', tab: 'receipts' },
    { key: 'invoices', label: 'Invoices', tab: 'invoices' },
    { key: 'contracts', label: 'Contracts', tab: 'contracts' },
    { key: 'bank_statements', label: 'Statements', tab: 'banking' },
    { key: 'documents', label: 'Documents', tab: 'documents' },
    { key: 'vouchers', label: 'Vouchers', tab: 'overview' },
    { key: 'warranties', label: 'Warranties', tab: 'overview' },
];

const totalExpiring = computed(() => {
    const e = props.tab_data?.expiring_soon;
    if (!e) return 0;
    return (e.vouchers || 0) + (e.warranties || 0) + (e.contracts || 0);
});

const changeTab = (tab) => {
    activeTab.value = tab;
    router.visit(route('analytics.index', { tab, period: selectedPeriod.value }), {
        preserveScroll: true,
    });
};

const updateParams = () => {
    router.visit(route('analytics.index', { tab: activeTab.value, period: selectedPeriod.value }), {
        preserveScroll: true,
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
            chartInstances.banking = new Chart(bankingChart.value.getContext('2d'), {
                type: 'line',
                data: {
                    labels: props.tab_data.balance_trend.map(i => i.date),
                    datasets: [
                        {
                            label: 'Opening Balance',
                            data: props.tab_data.balance_trend.map(i => i.opening),
                            borderColor: 'rgb(156, 163, 175)',
                            borderDash: [5, 5],
                            tension: 0.1,
                            pointRadius: 3,
                        },
                        {
                            label: 'Closing Balance',
                            data: props.tab_data.balance_trend.map(i => i.closing),
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            fill: true,
                            tension: 0.1,
                        },
                    ],
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
                            ticks: { stepSize: 1 },
                        },
                    },
                },
            });
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
                    return formatCurrency(value);
                },
            },
        },
    },
});

onMounted(() => {
    initCharts();
});

watch(() => props.current_tab, () => {
    activeTab.value = props.current_tab;
    nextTick(initCharts);
});
</script>
