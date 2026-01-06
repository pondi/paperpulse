<template>
  <AppLayout title="Invoices">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Invoices</h1>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Payment Status</label>
            <select v-model="filters.paymentStatus" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="partial">Partially Paid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Invoice Type</label>
            <select v-model="filters.type" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All Types</option>
              <option value="invoice">Invoice</option>
              <option value="credit_note">Credit Note</option>
              <option value="debit_note">Debit Note</option>
              <option value="proforma">Proforma</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Search</label>
            <input
              v-model="filters.search"
              @input="applyFilters"
              type="text"
              placeholder="Search by invoice number or company..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- Invoices List -->
      <div v-if="filteredInvoices.length > 0" class="space-y-4">
        <InvoiceCard
          v-for="invoice in filteredInvoices"
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
        <p class="text-gray-500 dark:text-gray-400">No invoices found matching your filters.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InvoiceCard from '@/Components/Entities/InvoiceCard.vue'

const props = defineProps({
  invoices: {
    type: Array,
    required: true
  }
})

const filters = ref({
  paymentStatus: '',
  type: '',
  search: ''
})

const filteredInvoices = computed(() => {
  let result = props.invoices

  if (filters.value.paymentStatus) {
    result = result.filter(inv => inv.payment_status === filters.value.paymentStatus)
  }

  if (filters.value.type) {
    result = result.filter(inv => inv.invoice_type === filters.value.type)
  }

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(inv =>
      (inv.invoice_number && inv.invoice_number.toLowerCase().includes(search)) ||
      (inv.from_name && inv.from_name.toLowerCase().includes(search)) ||
      (inv.to_name && inv.to_name.toLowerCase().includes(search))
    )
  }

  return result
})

function applyFilters() {
  // Filters are applied reactively via computed property
}
</script>
