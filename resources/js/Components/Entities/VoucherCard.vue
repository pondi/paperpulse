<template>
  <article class="flex h-full flex-col rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 sm:p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ voucherTypeLabel }}</p>
        <h2 class="mt-1 truncate text-base font-semibold text-zinc-950 dark:text-zinc-100">{{ merchantName || 'Voucher' }}</h2>
      </div>
      <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass">{{ statusLabel }}</span>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-700">
      <div v-if="voucher.code" class="col-span-2">
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Code</dt>
        <dd class="mt-1 break-all font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ voucher.code }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Remaining value</dt>
        <dd class="mt-1 font-semibold tabular-nums text-zinc-950 dark:text-zinc-100">{{ voucher.current_value == null ? '—' : formatCurrency(voucher.current_value, voucher.currency) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Expires</dt>
        <dd class="mt-1 text-sm" :class="isExpiringSoon ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-zinc-800 dark:text-zinc-200'">{{ voucher.expiry_date ? formatDate(voucher.expiry_date) : 'No expiry date' }}</dd>
      </div>
    </dl>

    <div v-if="isPaymentPlan" class="mt-3 rounded-lg bg-sky-50 p-3 text-sm dark:bg-sky-900/20">
      <p class="text-zinc-800 dark:text-zinc-200"><span class="font-semibold">{{ voucher.installment_count ?? '—' }}</span> payments · <span class="font-semibold">{{ voucher.monthly_payment == null ? '—' : formatCurrency(voucher.monthly_payment, voucher.currency) }}</span> per month</p>
    </div>

    <div class="mt-auto flex flex-wrap gap-2 pt-5">
      <Link :href="route('vouchers.show', voucher.id)" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">View details</Link>
      <button v-if="canRedeem && showActions" type="button" @click="$emit('redeem', voucher.id)" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg bg-violet-700 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-800 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 dark:bg-violet-500 dark:hover:bg-violet-400 dark:focus-visible:ring-offset-zinc-800">Mark redeemed</button>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useDateFormatter } from '@/Composables/useDateFormatter'

const props = defineProps({ voucher: { type: Object, required: true }, showActions: { type: Boolean, default: true } })
defineEmits(['redeem'])
const { formatDate, formatCurrency, daysUntilDate } = useDateFormatter()
const voucherTypeLabel = computed(() => ({ gift_card: 'Gift card', payment_plan: 'Payment plan', store_credit: 'Store credit', coupon: 'Coupon', promo_code: 'Promo code' }[props.voucher.voucher_type] || 'Voucher'))
const merchantName = computed(() => props.voucher.merchant?.name || props.voucher.merchant_name || '')
const isPaymentPlan = computed(() => props.voucher.voucher_type === 'payment_plan')
const daysRemaining = computed(() => daysUntilDate(props.voucher.expiry_date))
const isExpired = computed(() => daysRemaining.value !== null && daysRemaining.value < 0)
const canRedeem = computed(() => !props.voucher.is_redeemed && !isExpired.value)
const isExpiringSoon = computed(() => canRedeem.value && daysRemaining.value !== null && daysRemaining.value <= 30)
const statusLabel = computed(() => props.voucher.is_redeemed ? 'Redeemed' : isExpired.value ? 'Expired' : 'Active')
const statusClass = computed(() => props.voucher.is_redeemed
  ? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200'
  : isExpired.value
    ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300')
</script>
