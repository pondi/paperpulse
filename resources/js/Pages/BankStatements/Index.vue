<template>
  <AppLayout title="Bank Statements">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Bank Statements</h1>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Sort By</label>
            <select v-model="filters.sort" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="date_desc">Newest First</option>
              <option value="date_asc">Oldest First</option>
              <option value="balance_desc">Highest Balance</option>
              <option value="balance_asc">Lowest Balance</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Search</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search by bank name, account holder..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- Statements List -->
      <div v-if="filteredStatements.length > 0" class="space-y-4">
        <div
          v-for="statement in filteredStatements"
          :key="statement.id"
          @click="viewStatement(statement.id)"
          class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 cursor-pointer hover:shadow-md hover:ring-1 hover:ring-amber-300 dark:hover:ring-amber-600 transition-all"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                  {{ statementTitle(statement) }}
                </h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-300">
                  Statement
                </span>
              </div>

              <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <span v-if="statement.account_holder_name">{{ statement.account_holder_name }}</span>
                <span v-if="statement.account_holder_name && statement.account_number"> &middot; </span>
                <span v-if="statement.account_number" class="font-mono">{{ statement.account_number }}</span>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                <span v-if="statement.statement_period_start && statement.statement_period_end">
                  {{ formatDate(statement.statement_period_start) }} &ndash; {{ formatDate(statement.statement_period_end) }}
                </span>
                <span v-else-if="statement.statement_date">
                  {{ formatDate(statement.statement_date) }}
                </span>
                <span v-if="statement.transaction_count" class="text-gray-500 dark:text-gray-400">
                  {{ statement.transaction_count }} transactions
                </span>
              </div>
            </div>

            <div class="text-right flex-shrink-0 ml-4">
              <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                {{ formatCurrency(statement.closing_balance, statement.currency) }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Closing Balance</div>
              <div class="mt-1 flex gap-3 text-xs">
                <span class="text-green-600 dark:text-green-400">+{{ formatCurrency(statement.total_credits, statement.currency) }}</span>
                <span class="text-red-600 dark:text-red-400">-{{ formatCurrency(statement.total_debits, statement.currency) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-12">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400">No bank statements found.</p>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Upload a bank statement PDF or CSV to get started.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useDateFormatter } from '@/Composables/useDateFormatter'

const { formatDate, formatCurrency } = useDateFormatter()

const props = defineProps({
  statements: {
    type: Array,
    required: true
  }
})

const filters = ref({
  sort: 'date_desc',
  search: ''
})

const filteredStatements = computed(() => {
  let result = [...props.statements]

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(s =>
      (s.bank_name && s.bank_name.toLowerCase().includes(search)) ||
      (s.account_holder_name && s.account_holder_name.toLowerCase().includes(search)) ||
      (s.account_number && s.account_number.toLowerCase().includes(search))
    )
  }

  switch (filters.value.sort) {
    case 'date_asc':
      result.sort((a, b) => (a.statement_date || '').localeCompare(b.statement_date || ''))
      break
    case 'balance_desc':
      result.sort((a, b) => (b.closing_balance || 0) - (a.closing_balance || 0))
      break
    case 'balance_asc':
      result.sort((a, b) => (a.closing_balance || 0) - (b.closing_balance || 0))
      break
    case 'date_desc':
    default:
      result.sort((a, b) => (b.statement_date || '').localeCompare(a.statement_date || ''))
      break
  }

  return result
})

function viewStatement(id) {
  router.visit(route('bank-statements.show', id))
}

function statementTitle(statement) {
  if (statement.bank_name) return statement.bank_name
  if (statement.statement_period_start && statement.statement_period_end) {
    return formatDate(statement.statement_period_start) + ' – ' + formatDate(statement.statement_period_end)
  }
  if (statement.statement_date) return formatDate(statement.statement_date)
  return 'Bank Statement'
}
</script>
