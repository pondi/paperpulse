<template>
  <AppLayout :title="`Invoice ${invoice.invoice_number}`">
    <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-6">
        <Link :href="route('invoices.index')" class="text-blue-600 dark:text-blue-400 hover:underline mb-2 inline-block">
          &larr; Back to Invoices
        </Link>
        <div class="flex justify-between items-start">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
            Invoice #{{ invoice.invoice_number }}
          </h1>
          <span class="px-3 py-1 rounded-full text-sm font-medium" :class="paymentStatusClass">
            {{ invoice.payment_status }}
          </span>
        </div>
      </div>

      <!-- Invoice Details Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <!-- Parties Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 pb-8 border-b border-gray-200 dark:border-gray-700">
          <!-- From -->
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">From</h3>
            <div class="text-gray-900 dark:text-gray-100">
              <p class="font-semibold text-lg">{{ invoice.from_name }}</p>
              <p v-if="invoice.from_address" class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line mt-1">
                {{ invoice.from_address }}
              </p>
              <p v-if="invoice.from_vat_number" class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                VAT: {{ invoice.from_vat_number }}
              </p>
              <p v-if="invoice.from_email" class="text-sm text-gray-600 dark:text-gray-300">
                {{ invoice.from_email }}
              </p>
            </div>
          </div>

          <!-- To -->
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">To</h3>
            <div class="text-gray-900 dark:text-gray-100">
              <p class="font-semibold text-lg">{{ invoice.to_name }}</p>
              <p v-if="invoice.to_address" class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line mt-1">
                {{ invoice.to_address }}
              </p>
              <p v-if="invoice.to_vat_number" class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                VAT: {{ invoice.to_vat_number }}
              </p>
              <p v-if="invoice.to_email" class="text-sm text-gray-600 dark:text-gray-300">
                {{ invoice.to_email }}
              </p>
            </div>
          </div>
        </div>

        <!-- Invoice Meta -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <div>
            <dt class="text-sm text-gray-600 dark:text-gray-400">Invoice Date</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(invoice.invoice_date) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-600 dark:text-gray-400">Due Date</dt>
            <dd class="font-medium" :class="{'text-red-600 dark:text-red-400': isOverdue}">
              {{ formatDate(invoice.due_date) }}
            </dd>
          </div>
          <div v-if="invoice.delivery_date">
            <dt class="text-sm text-gray-600 dark:text-gray-400">Delivery Date</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(invoice.delivery_date) }}</dd>
          </div>
          <div v-if="invoice.invoice_type">
            <dt class="text-sm text-gray-600 dark:text-gray-400">Type</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoice_type }}</dd>
          </div>
        </div>

        <!-- Line Items -->
        <div v-if="invoice.line_items && invoice.line_items.length > 0" class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Line Items</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unit Price</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tax</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="item in invoice.line_items" :key="item.id">
                  <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ item.description }}</td>
                  <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                  <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.unit_price) }}</td>
                  <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-300">{{ item.tax_rate }}%</td>
                  <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.total_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Totals -->
        <div class="flex justify-end">
          <div class="w-full md:w-1/2 space-y-2">
            <div v-if="invoice.subtotal" class="flex justify-between text-gray-700 dark:text-gray-300">
              <span>Subtotal:</span>
              <span>{{ formatCurrency(invoice.subtotal) }}</span>
            </div>
            <div v-if="invoice.tax_amount" class="flex justify-between text-gray-700 dark:text-gray-300">
              <span>Tax:</span>
              <span>{{ formatCurrency(invoice.tax_amount) }}</span>
            </div>
            <div v-if="invoice.discount_amount" class="flex justify-between text-gray-700 dark:text-gray-300">
              <span>Discount:</span>
              <span class="text-red-600 dark:text-red-400">-{{ formatCurrency(invoice.discount_amount) }}</span>
            </div>
            <div v-if="invoice.shipping_amount" class="flex justify-between text-gray-700 dark:text-gray-300">
              <span>Shipping:</span>
              <span>{{ formatCurrency(invoice.shipping_amount) }}</span>
            </div>
            <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-gray-100 pt-2 border-t border-gray-300 dark:border-gray-600">
              <span>Total:</span>
              <span>{{ formatCurrency(invoice.total_amount) }}</span>
            </div>
            <div v-if="invoice.amount_paid > 0" class="flex justify-between text-green-600 dark:text-green-400">
              <span>Paid:</span>
              <span>{{ formatCurrency(invoice.amount_paid) }}</span>
            </div>
            <div v-if="invoice.amount_due > 0" class="flex justify-between text-red-600 dark:text-red-400 font-semibold">
              <span>Amount Due:</span>
              <span>{{ formatCurrency(invoice.amount_due) }}</span>
            </div>
          </div>
        </div>

        <!-- Payment Terms -->
        <div v-if="invoice.payment_terms" class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Payment Terms</h3>
          <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ invoice.payment_terms }}</p>
        </div>

        <!-- Notes -->
        <div v-if="invoice.notes" class="mt-6">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Notes</h3>
          <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ invoice.notes }}</p>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex space-x-3">
          <Link
            v-if="invoice.file_id"
            :href="route('files.show', invoice.file_id)"
            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 rounded-lg transition"
          >
            View Original File
          </Link>
        </div>
      </div>

      <!-- Tags -->
      <div v-if="invoice.tags && invoice.tags.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="tag in invoice.tags"
            :key="tag.id"
            class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm"
          >
            {{ tag.name }}
          </span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  invoice: {
    type: Object,
    required: true
  }
})

const isOverdue = computed(() => {
  return props.invoice.due_date &&
         new Date(props.invoice.due_date) < new Date() &&
         props.invoice.payment_status !== 'paid'
})

const paymentStatusClass = computed(() => {
  const classes = {
    'paid': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    'unpaid': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    'partial': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    'overdue': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
  }
  return classes[props.invoice.payment_status] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: props.invoice.currency || 'NOK'
  }).format(amount)
}

function formatDate(date) {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('no-NO')
}
</script>
