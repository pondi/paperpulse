<template>
  <AppLayout title="Bank statements">
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <header>
        <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Financial records</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-100">Bank statements</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Compare statement periods, activity and closing balances.</p>
      </header>

      <section aria-label="Statement filters" class="grid grid-cols-1 gap-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 sm:grid-cols-3">
        <div>
          <label for="statement-sort" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sort by</label>
          <select id="statement-sort" v-model="filters.sort" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm text-zinc-900 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
            <option value="statement_date">Newest statement</option>
            <option value="closing_balance">Closing balance</option>
          </select>
        </div>
        <div>
          <label for="statement-direction" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Order</label>
          <select id="statement-direction" v-model="filters.sort_direction" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm text-zinc-900 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
            <option value="desc">Highest / newest first</option>
            <option value="asc">Lowest / oldest first</option>
          </select>
        </div>
        <div>
          <label for="statement-search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Search statements</label>
          <input id="statement-search" v-model="filters.search" @input="applyFilters" type="search" placeholder="Bank, account holder or number" class="w-full rounded-lg border-zinc-300 bg-white text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100" />
        </div>
      </section>

      <div v-if="statements.length" class="space-y-3">
        <Link v-for="statement in statements" :key="statement.id" :href="route('bank-statements.show', statement.id)" class="group block rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-teal-400 hover:shadow-md focus:outline-hidden focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-800 dark:focus-visible:ring-offset-zinc-900 sm:p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-xs font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ statement.account_holder_name || 'Bank statement' }}</p>
              <h2 class="mt-1 truncate text-base font-semibold text-zinc-950 group-hover:text-teal-800 dark:text-zinc-100 dark:group-hover:text-teal-300">{{ statement.bank_name || 'Bank statement' }}</h2>
              <p v-if="statement.account_number" class="mt-1 font-mono text-sm text-zinc-600 dark:text-zinc-300">Account {{ statement.account_number }}</p>
            </div>
            <div class="min-w-36 text-left sm:text-right">
              <p class="text-xs text-zinc-500 dark:text-zinc-400">Closing balance</p>
              <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-950 dark:text-zinc-100">{{ formatCurrency(statement.closing_balance, statement.currency) }}</p>
            </div>
          </div>
          <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-zinc-100 pt-3 text-sm dark:border-zinc-700 sm:grid-cols-4">
            <div class="col-span-2">
              <dt class="text-xs text-zinc-500 dark:text-zinc-400">Statement period</dt>
              <dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ statementPeriod(statement) }}</dd>
            </div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Transactions</dt><dd class="mt-1 text-zinc-800 dark:text-zinc-200">{{ statement.transaction_count ?? 0 }}</dd></div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Credits</dt><dd class="mt-1 tabular-nums text-emerald-700 dark:text-emerald-300">{{ formatCurrency(statement.total_credits, statement.currency) }}</dd></div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Debits</dt><dd class="mt-1 tabular-nums text-rose-700 dark:text-rose-300">{{ formatCurrency(statement.total_debits, statement.currency) }}</dd></div>
          </dl>
        </Link>
      </div>

      <div v-else class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-600">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ filters.search ? 'No matching statements' : 'No bank statements yet' }}</h2>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ filters.search ? 'Try another bank or account search.' : 'Upload a bank statement PDF or CSV to review its periods and activity.' }}</p>
      </div>
      <Pagination v-bind="pagination" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import { useDateFormatter } from '@/Composables/useDateFormatter'

const props = defineProps({ statements: { type: Array, required: true }, filters: { type: Object, default: () => ({}) }, pagination: { type: Object, default: () => ({}) } })
const { formatDate, formatCurrency } = useDateFormatter()
const filters = ref({ sort: 'statement_date', sort_direction: 'desc', search: '', ...props.filters })
let searchTimer
function applyFilters() {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => router.get(route('bank-statements.index'), { ...filters.value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true }), filters.value.search ? 250 : 0)
}
function statementPeriod(statement) {
  const start = statement.statement_period_start
  const end = statement.statement_period_end
  if (start && end) return `${formatDate(start)} – ${formatDate(end)}`
  if (statement.statement_date) return formatDate(statement.statement_date)
  return 'Period unavailable'
}
</script>
