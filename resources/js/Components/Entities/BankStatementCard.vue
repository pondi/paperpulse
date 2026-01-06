<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-emerald-500 dark:border-emerald-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">{{ statement.bank_name || 'Bank Statement' }}</h3>
        <p v-if="statement.account_holder_name" class="text-sm text-zinc-600 dark:text-zinc-400">{{ statement.account_holder_name }}</p>
        <p v-if="statement.account_number" class="text-xs text-zinc-500 dark:text-zinc-500 mt-1 font-mono">
          Account: {{ maskedAccountNumber }}
        </p>
      </div>
    </div>

    <div class="space-y-3">
      <div v-if="statement.statement_period_start && statement.statement_period_end" class="text-sm text-zinc-600 dark:text-zinc-400">
        <span class="font-medium">Period:</span>
        {{ formatDate(statement.statement_period_start) }} - {{ formatDate(statement.statement_period_end) }}
      </div>

      <div class="grid grid-cols-2 gap-3 bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded border border-emerald-200 dark:border-emerald-800">
        <div>
          <span class="text-xs text-zinc-600 dark:text-zinc-400 block mb-1">Opening Balance</span>
          <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
            {{ formatCurrency(statement.opening_balance, statement.currency) }}
          </span>
        </div>
        <div>
          <span class="text-xs text-zinc-600 dark:text-zinc-400 block mb-1">Closing Balance</span>
          <span class="text-lg font-bold" :class="balanceChangeClass">
            {{ formatCurrency(statement.closing_balance, statement.currency) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Total Credits</span>
          <span class="text-green-600 dark:text-green-400 font-semibold">
            {{ formatCurrency(statement.total_credits, statement.currency) }}
          </span>
        </div>
        <div>
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Total Debits</span>
          <span class="text-red-600 dark:text-red-400 font-semibold">
            {{ formatCurrency(statement.total_debits, statement.currency) }}
          </span>
        </div>
      </div>

      <div v-if="statement.transaction_count" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3">
        <p>{{ statement.transaction_count }} transaction(s) in this period</p>
      </div>
    </div>

    <div v-if="showActions" class="mt-4">
      <button
        @click="$emit('view', statement.id)"
        class="w-full text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Transactions
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  statement: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['view']);

const maskedAccountNumber = computed(() => {
  if (!props.statement.account_number) return '';
  const accountNumber = props.statement.account_number;
  if (accountNumber.length <= 4) return accountNumber;
  return '****' + accountNumber.slice(-4);
});

const balanceChange = computed(() => {
  return props.statement.closing_balance - props.statement.opening_balance;
});

const balanceChangeClass = computed(() => {
  if (balanceChange.value > 0) {
    return 'text-green-600 dark:text-green-400';
  } else if (balanceChange.value < 0) {
    return 'text-red-600 dark:text-red-400';
  }
  return 'text-zinc-900 dark:text-zinc-100';
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
