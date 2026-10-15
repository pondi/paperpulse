<template>
  <Link
    :href="route('invoices.show', invoice.id)"
    class="group block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-amber-400 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-800 dark:focus-visible:ring-offset-zinc-900 sm:p-5"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ invoice.invoice_type || 'Invoice' }}</p>
        <h2 class="mt-1 truncate text-base font-semibold text-zinc-950 group-hover:text-amber-700 dark:text-zinc-100 dark:group-hover:text-amber-300">
          {{ invoice.invoice_number || 'Invoice without number' }}
        </h2>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ invoice.from_name || 'Unknown sender' }}</p>
      </div>
      <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="paymentStatusClass">
        {{ paymentStatusLabel }}
      </span>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-700 sm:grid-cols-4">
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Total</dt>
        <dd class="mt-1 font-semibold tabular-nums text-zinc-950 dark:text-zinc-100">{{ formatCurrency(invoice.total_amount, invoice.currency) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Amount due</dt>
        <dd class="mt-1 font-semibold tabular-nums" :class="amountDue > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-700 dark:text-zinc-200'">{{ formatCurrency(invoice.amount_due ?? 0, invoice.currency) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Invoice date</dt>
        <dd class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ invoice.invoice_date ? formatDate(invoice.invoice_date) : '—' }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Due date</dt>
        <dd class="mt-1 text-sm" :class="isOverdue ? 'font-semibold text-rose-700 dark:text-rose-300' : 'text-zinc-800 dark:text-zinc-200'">
          {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}<span v-if="isOverdue" class="sr-only">, overdue</span>
        </dd>
      </div>
    </dl>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useDateFormatter } from '@/Composables/useDateFormatter'

const props = defineProps({ invoice: { type: Object, required: true } })
const { formatDate, formatCurrency } = useDateFormatter()
const amountDue = computed(() => Number(props.invoice.amount_due ?? 0))
const isOverdue = computed(() => props.invoice.due_date && amountDue.value > 0 && new Date(`${props.invoice.due_date}T00:00:00`) < new Date(new Date().toDateString()))
const paymentStatusLabel = computed(() => ({ paid: 'Paid', unpaid: 'Unpaid', partial: 'Partially paid', overdue: 'Overdue' }[props.invoice.payment_status] || 'Unknown status'))
const paymentStatusClass = computed(() => ({
  paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  unpaid: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
  partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  overdue: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
}[props.invoice.payment_status] || 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200'))
</script>
