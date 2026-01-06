<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-purple-500 dark:border-purple-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">{{ voucherTypeLabel }}</h3>
        <p v-if="voucher.merchant" class="text-sm text-zinc-600 dark:text-zinc-400">{{ voucher.merchant.name }}</p>
      </div>
      <span
        v-if="!voucher.is_redeemed && !isExpired"
        class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-xs rounded font-medium"
      >
        Active
      </span>
      <span
        v-else-if="isExpired"
        class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-xs rounded font-medium"
      >
        Expired
      </span>
      <span
        v-else
        class="px-2 py-1 bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 text-xs rounded font-medium"
      >
        Redeemed
      </span>
    </div>

    <div class="space-y-3">
      <div v-if="voucher.code" class="font-mono text-xl font-bold text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-900/50 p-3 rounded text-center">
        {{ voucher.code }}
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div v-if="voucher.current_value">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Value</span>
          <span class="font-semibold text-zinc-900 dark:text-zinc-100">
            {{ formatCurrency(voucher.current_value, voucher.currency) }}
          </span>
        </div>
        <div v-if="voucher.expiry_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Expires</span>
          <span :class="{'text-red-600 dark:text-red-400': isExpiringSoon}" class="font-semibold">
            {{ formatDate(voucher.expiry_date) }}
          </span>
        </div>
      </div>

      <div v-if="isPaymentPlan" class="text-sm bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-800">
        <p class="text-zinc-700 dark:text-zinc-300">
          <span class="font-semibold">{{ voucher.installment_count }}</span> monthly payments of
          <span class="font-semibold">{{ formatCurrency(voucher.monthly_payment, voucher.currency) }}</span>
        </p>
        <p v-if="voucher.first_payment_date" class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
          First payment: {{ formatDate(voucher.first_payment_date) }}
        </p>
      </div>

      <div v-if="voucher.terms_and_conditions" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3">
        <p class="line-clamp-2">{{ voucher.terms_and_conditions }}</p>
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <button
        v-if="!voucher.is_redeemed && !isExpired && showActions"
        @click="$emit('redeem', voucher.id)"
        class="flex-1 text-sm px-4 py-2 bg-purple-600 hover:bg-purple-700 dark:bg-purple-500 dark:hover:bg-purple-600 text-white rounded-md font-medium transition-colors"
      >
        Mark as Redeemed
      </button>
      <button
        v-if="showActions"
        @click="$emit('view', voucher.id)"
        class="flex-1 text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Details
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  voucher: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['redeem', 'view']);

const voucherTypeLabel = computed(() => {
  const labels = {
    'gift_card': 'Gift Card',
    'payment_plan': 'Payment Plan',
    'store_credit': 'Store Credit',
    'coupon': 'Coupon',
    'promo_code': 'Promo Code'
  };
  return labels[props.voucher.voucher_type] || 'Voucher';
});

const isPaymentPlan = computed(() => {
  return props.voucher.voucher_type === 'payment_plan';
});

const isExpired = computed(() => {
  if (!props.voucher.expiry_date) return false;
  return new Date(props.voucher.expiry_date) < new Date();
});

const isExpiringSoon = computed(() => {
  if (!props.voucher.expiry_date) return false;
  const daysUntilExpiry = Math.ceil(
    (new Date(props.voucher.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  );
  return daysUntilExpiry <= 30 && daysUntilExpiry > 0;
});

const formatCurrency = (amount, currency = 'NOK') => {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: currency
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
