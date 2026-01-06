<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-blue-500 dark:border-blue-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">{{ warranty.product_name }}</h3>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ warranty.manufacturer }}</p>
      </div>
      <span class="px-2 py-1 text-xs rounded font-medium" :class="statusClass">
        {{ statusLabel }}
      </span>
    </div>

    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div v-if="warranty.purchase_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Purchased</span>
          <span class="text-zinc-900 dark:text-zinc-100">{{ formatDate(warranty.purchase_date) }}</span>
        </div>
        <div v-if="warranty.warranty_end_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Warranty Expires</span>
          <span class="font-semibold" :class="{'text-red-600 dark:text-red-400': isExpiringSoon}">
            {{ formatDate(warranty.warranty_end_date) }}
          </span>
        </div>
        <div v-if="warranty.serial_number" class="col-span-2">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Serial Number</span>
          <span class="font-mono text-xs text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-900/50 px-2 py-1 rounded">
            {{ warranty.serial_number }}
          </span>
        </div>
        <div v-if="warranty.model_number" class="col-span-2">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Model</span>
          <span class="text-zinc-900 dark:text-zinc-100">{{ warranty.model_number }}</span>
        </div>
      </div>

      <div v-if="daysRemaining > 0" class="text-sm bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-800">
        <p class="text-zinc-700 dark:text-zinc-300">
          <span class="font-semibold">{{ daysRemaining }}</span> days remaining on warranty
        </p>
      </div>

      <div v-if="warranty.support_phone || warranty.support_email" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3 space-y-1">
        <p v-if="warranty.support_phone">Support: {{ warranty.support_phone }}</p>
        <p v-if="warranty.support_email">Email: {{ warranty.support_email }}</p>
      </div>
    </div>

    <div v-if="showActions" class="mt-4">
      <button
        @click="$emit('view', warranty.id)"
        class="w-full text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Warranty Details
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  warranty: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['view']);

const isExpired = computed(() => {
  if (!props.warranty.warranty_end_date) return false;
  return new Date(props.warranty.warranty_end_date) < new Date();
});

const isExpiringSoon = computed(() => {
  return daysRemaining.value <= 90 && daysRemaining.value > 0;
});

const daysRemaining = computed(() => {
  if (!props.warranty.warranty_end_date) return 0;
  return Math.ceil(
    (new Date(props.warranty.warranty_end_date) - new Date()) / (1000 * 60 * 60 * 24)
  );
});

const statusLabel = computed(() => {
  if (isExpired.value) return 'Expired';
  if (isExpiringSoon.value) return 'Expiring Soon';
  return 'Active';
});

const statusClass = computed(() => {
  if (isExpired.value) return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
  if (isExpiringSoon.value) return 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300';
  return 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('no-NO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};
</script>
