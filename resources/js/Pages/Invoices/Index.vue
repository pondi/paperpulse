<template>
  <AppLayout title="Invoices">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Financial records</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-100">Invoices</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Compare payment state, amounts and due dates.</p>
      </div>

      <!-- Filters -->
      <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label for="invoice-status" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Payment status</label>
            <select id="invoice-status" v-model="filters.paymentStatus" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="partial">Partially Paid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>

          <div>
            <label for="invoice-type" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Document type</label>
            <select id="invoice-type" v-model="filters.type" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All Types</option>
              <option value="invoice">Invoice</option>
              <option value="credit_note">Credit Note</option>
              <option value="debit_note">Debit Note</option>
              <option value="proforma">Proforma</option>
            </select>
          </div>

          <div>
            <label for="invoice-sort" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sort by</label>
            <select id="invoice-sort" v-model="filters.sort" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="invoice_date">Invoice date</option><option value="due_date">Due date</option><option value="total_amount">Total amount</option><option value="invoice_number">Invoice number</option>
            </select>
          </div>

          <div>
            <label for="invoice-sort-direction" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Order</label>
            <select id="invoice-sort-direction" v-model="filters.sort_direction" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="desc">Descending</option><option value="asc">Ascending</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label for="invoice-search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Search invoices</label>
            <input
              id="invoice-search"
              v-model="filters.search"
              @input="applyFilters"
              type="search"
              placeholder="Invoice number or company"
              class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100"
            />
          </div>
        </div>
      </div>

      <!-- Invoices List -->
      <div v-if="invoices.length > 0" class="space-y-3">
        <InvoiceCard
          v-for="invoice in invoices"
          :key="invoice.id"
          :invoice="invoice"
        />
      </div>

      <div v-else class="text-center py-12">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400">{{ hasFilters ? 'No invoices match these filters.' : 'No invoices yet.' }}</p>
      </div>
      <Pagination v-bind="pagination" />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import InvoiceCard from '@/Components/Entities/InvoiceCard.vue'

const props = defineProps({
  invoices: {
    type: Array,
    required: true
  },
  filters: Object,
  pagination: Object,
})

const filters = ref({ sort: 'invoice_date', sort_direction: 'desc', search: '', paymentStatus: '', type: '', ...props.filters })
const hasFilters = computed(() => Boolean(filters.value.search || filters.value.paymentStatus || filters.value.type))

function applyFilters() {
  router.get(route('invoices.index'), { ...filters.value, page: 1 }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

</script>
