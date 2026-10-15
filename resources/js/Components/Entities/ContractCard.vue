<template>
  <Link
    :href="route('contracts.show', contract.id)"
    class="group block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-amber-400 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-800 dark:focus-visible:ring-offset-zinc-900 sm:p-5"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ contractTypeLabel }}</p>
        <h2 class="mt-1 truncate text-base font-semibold text-zinc-950 group-hover:text-amber-700 dark:text-zinc-100 dark:group-hover:text-amber-300">
          {{ contract.contract_title || contract.contract_number || 'Untitled contract' }}
        </h2>
        <p v-if="contract.contract_number" class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Contract #{{ contract.contract_number }}</p>
      </div>
      <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass">{{ statusLabel }}</span>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-700 sm:grid-cols-4">
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Effective</dt>
        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ contract.effective_date ? formatDate(contract.effective_date) : '—' }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Expires</dt>
        <dd class="mt-1 text-sm" :class="isExpiringSoon ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-zinc-800 dark:text-zinc-200'">{{ contract.expiry_date ? formatDate(contract.expiry_date) : 'No expiry date' }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Value</dt>
        <dd class="mt-1 font-semibold tabular-nums text-zinc-950 dark:text-zinc-100">{{ contract.contract_value == null ? '—' : formatCurrency(contract.contract_value, contract.currency) }}</dd>
      </div>
      <div v-if="partySummary">
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Parties</dt>
        <dd class="mt-1 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ partySummary }}</dd>
      </div>
    </dl>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useDateFormatter } from '@/Composables/useDateFormatter'

const props = defineProps({ contract: { type: Object, required: true } })
const { formatDate, formatCurrency } = useDateFormatter()
const contractTypeLabel = computed(() => ({ employment: 'Employment', service: 'Service', rental: 'Rental', purchase: 'Purchase', nda: 'Non-disclosure', lease: 'Lease' }[props.contract.contract_type] || 'Contract'))
const statusLabel = computed(() => ({ draft: 'Draft', active: 'Active', expired: 'Expired', terminated: 'Terminated', renewed: 'Renewed' }[props.contract.status] || 'Unknown status'))
const statusClass = computed(() => ({
  active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  expired: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
  terminated: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
  renewed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
}[props.contract.status] || 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200'))
const partySummary = computed(() => (Array.isArray(props.contract.parties) ? props.contract.parties : []).map(party => typeof party === 'string' ? party : party?.name).filter(Boolean).slice(0, 2).join(', '))
const isExpiringSoon = computed(() => {
  if (!props.contract.expiry_date || props.contract.status !== 'active') return false
  const daysRemaining = (new Date(`${props.contract.expiry_date}T00:00:00`) - new Date(new Date().toDateString())) / 86400000
  return daysRemaining >= 0 && daysRemaining <= 60
})
</script>
