<template>
  <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 border-l-4 border-amber-500 dark:border-amber-400">
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">{{ contract.contract_title || 'Contract' }}</h3>
        <p v-if="contract.contract_number" class="text-sm text-zinc-600 dark:text-zinc-400">Contract #{{ contract.contract_number }}</p>
        <p v-if="contract.contract_type" class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">{{ contractTypeLabel }}</p>
      </div>
      <span class="px-2 py-1 text-xs rounded font-medium" :class="statusClass">
        {{ statusLabel }}
      </span>
    </div>

    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div v-if="contract.effective_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Effective Date</span>
          <span class="text-zinc-900 dark:text-zinc-100">{{ formatDate(contract.effective_date) }}</span>
        </div>
        <div v-if="contract.expiry_date">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Expiry Date</span>
          <span :class="{'text-red-600 dark:text-red-400 font-semibold': isExpiringSoon}">
            {{ formatDate(contract.expiry_date) }}
          </span>
        </div>
        <div v-if="contract.contract_value" class="col-span-2">
          <span class="text-zinc-600 dark:text-zinc-400 block text-xs font-medium mb-1">Contract Value</span>
          <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
            {{ formatCurrency(contract.contract_value, contract.currency) }}
          </span>
        </div>
      </div>

      <div v-if="contract.parties && contract.parties.length > 0" class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded border border-amber-200 dark:border-amber-800">
        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-2">Parties:</p>
        <div class="space-y-1">
          <p v-for="(party, index) in contract.parties" :key="index" class="text-xs text-zinc-600 dark:text-zinc-400">
            <span v-if="party.role" class="font-medium">{{ party.role }}:</span> {{ party.name }}
          </p>
        </div>
      </div>

      <div v-if="contract.summary" class="text-xs text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700 pt-3">
        <p class="font-medium mb-1">Summary:</p>
        <p class="line-clamp-3">{{ contract.summary }}</p>
      </div>

      <div v-if="contract.governing_law || contract.jurisdiction" class="flex flex-wrap gap-2 text-xs">
        <span v-if="contract.governing_law" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded text-zinc-700 dark:text-zinc-300">
          {{ contract.governing_law }}
        </span>
        <span v-if="contract.jurisdiction" class="px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded text-zinc-700 dark:text-zinc-300">
          {{ contract.jurisdiction }}
        </span>
      </div>
    </div>

    <div v-if="showActions" class="mt-4">
      <button
        @click="$emit('view', contract.id)"
        class="w-full text-sm px-4 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-medium transition-colors"
      >
        View Contract Details
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  contract: {
    type: Object,
    required: true
  },
  showActions: {
    type: Boolean,
    default: true
  }
});

defineEmits(['view']);

const contractTypeLabel = computed(() => {
  const labels = {
    'employment': 'Employment Contract',
    'service': 'Service Contract',
    'rental': 'Rental Agreement',
    'purchase': 'Purchase Agreement',
    'nda': 'Non-Disclosure Agreement',
    'lease': 'Lease Agreement'
  };
  return labels[props.contract.contract_type] || props.contract.contract_type;
});

const statusLabel = computed(() => {
  const labels = {
    'draft': 'Draft',
    'active': 'Active',
    'expired': 'Expired',
    'terminated': 'Terminated',
    'renewed': 'Renewed'
  };
  return labels[props.contract.status] || props.contract.status;
});

const statusClass = computed(() => {
  const classes = {
    'draft': 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300',
    'active': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    'expired': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    'terminated': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    'renewed': 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300'
  };
  return classes[props.contract.status] || 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300';
});

const isExpiringSoon = computed(() => {
  if (!props.contract.expiry_date) return false;
  const daysUntilExpiry = Math.ceil(
    (new Date(props.contract.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  );
  return daysUntilExpiry <= 60 && daysUntilExpiry > 0;
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
