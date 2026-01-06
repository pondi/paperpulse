<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-indigo-500 dark:border-indigo-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">Invoice #{{ invoice.invoice_number }}</h3>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">From: {{ invoice.from_name }}</p>
      </div>
      <span class="px-2 py-1 text-xs rounded font-medium" :class="paymentStatusClass">
        {{ paymentStatusLabel }}
      </span>
    </div>

    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div v-if="invoice.invoice_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Invoice Date</span>
          <span class="text-zinc-900 dark:text-zinc-100">{{ formatDate(invoice.invoice_date) }}</span>
        </div>
        <div v-if="invoice.due_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Due Date</span>
          <span :class="{'text-red-600 dark:text-red-400 font-semibold': isOverdue}">
            {{ formatDate(invoice.due_date) }}
          </span>
        </div>
      </div>

      <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded border border-indigo-200 dark:border-indigo-800">
        <div class="flex justify-between items-center">
          <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Total Amount</span>
          <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
            {{ formatCurrency(invoice.total_amount, invoice.currency) }}
          </span>
        </div>
        <div v-if="invoice.amount_due > 0" class="flex justify-between items-center mt-2 pt-2 border-t border-indigo-200 dark:border-indigo-700">
          <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Amount Due</span>
          <span class="text-lg font-bold text-red-600 dark:text-red-400">
            {{ formatCurrency(invoice.amount_due, invoice.currency) }}
          </span>
        </div>
      </div>

      <div v-if="invoice.line_items && invoice.line_items.length > 0" class="text-xs text-zinc-600 dark:text-zinc-400">
        <p class="font-medium mb-1">{{ invoice.line_items.length }} line item(s)</p>
      </div>

      <div v-if="invoice.payment_terms" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3">
        <p class="font-medium mb-1">Payment Terms:</p>
        <p class="line-clamp-2">{{ invoice.payment_terms }}</p>
      </div>
    </div>

    <div v-if="showActions" class="mt-4">
      <button
        @click="$emit('view', invoice.id)"
        class="w-full text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Invoice
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  invoice: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['view']);

const isOverdue = computed(() => {
  if (!props.invoice.due_date) return false;
  return new Date(props.invoice.due_date) < new Date() && props.invoice.payment_status !== 'paid';
});

const paymentStatusLabel = computed(() => {
  const labels = {
    'paid': 'Paid',
    'unpaid': 'Unpaid',
    'partial': 'Partially Paid',
    'overdue': 'Overdue'
  };
  return labels[props.invoice.payment_status] || props.invoice.payment_status;
});

const paymentStatusClass = computed(() => {
  const classes = {
    'paid': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    'unpaid': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    'partial': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    'overdue': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
  };
  return classes[props.invoice.payment_status] || 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300';
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('no-NO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatCurrency = (amount, currency = 'NOK') => {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: currency
  }).format(amount);
};
</script>
