<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-teal-500 dark:border-teal-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">Return Policy</h3>
        <p v-if="returnPolicy.merchant" class="text-sm text-zinc-600 dark:text-zinc-400">{{ returnPolicy.merchant.name }}</p>
      </div>
      <span
        v-if="returnPolicy.is_final_sale"
        class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-xs rounded font-medium"
      >
        Final Sale
      </span>
    </div>

    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div v-if="returnPolicy.return_deadline">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Return Deadline</span>
          <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ formatDate(returnPolicy.return_deadline) }}</span>
        </div>
        <div v-if="returnPolicy.exchange_deadline">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Exchange Deadline</span>
          <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ formatDate(returnPolicy.exchange_deadline) }}</span>
        </div>
      </div>

      <div v-if="returnPolicy.refund_method" class="bg-teal-50 dark:bg-teal-900/20 p-3 rounded border border-teal-200 dark:border-teal-800">
        <p class="text-sm text-zinc-700 dark:text-zinc-300">
          <span class="font-medium">Refund Method:</span> {{ refundMethodLabel }}
        </p>
      </div>

      <div v-if="returnPolicy.restocking_fee" class="text-sm text-zinc-700 dark:text-zinc-300">
        <span class="font-medium">Restocking Fee:</span>
        {{ formatCurrency(returnPolicy.restocking_fee) }}
        <span v-if="returnPolicy.restocking_fee_percentage">({{ returnPolicy.restocking_fee_percentage }}%)</span>
      </div>

      <div v-if="returnPolicy.conditions" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3">
        <p class="font-medium mb-1">Conditions:</p>
        <p class="line-clamp-3">{{ returnPolicy.conditions }}</p>
      </div>

      <div class="flex flex-wrap gap-2 text-xs">
        <span v-if="returnPolicy.requires_receipt" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded text-zinc-700 dark:text-zinc-300">
          Receipt Required
        </span>
        <span v-if="returnPolicy.requires_original_packaging" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded text-zinc-700 dark:text-zinc-300">
          Original Packaging Required
        </span>
      </div>
    </div>

    <div v-if="showActions" class="mt-4">
      <button
        @click="$emit('view', returnPolicy.id)"
        class="w-full text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Details
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  returnPolicy: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['view']);

const refundMethodLabel = computed(() => {
  const labels = {
    'full_refund': 'Full Refund',
    'store_credit': 'Store Credit Only',
    'exchange_only': 'Exchange Only',
    'no_refund': 'No Refunds'
  };
  return labels[props.returnPolicy.refund_method] || props.returnPolicy.refund_method;
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: 'NOK'
  }).format(amount);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('no-NO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};
</script>
