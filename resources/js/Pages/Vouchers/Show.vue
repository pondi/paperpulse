<template>
  <AppLayout :title="`Voucher ${voucher.code || voucher.id}`">
    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-6">
        <Link :href="route('vouchers.index')" class="text-blue-600 dark:text-blue-400 hover:underline mb-2 inline-block">
          &larr; Back to Vouchers
        </Link>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
          {{ voucherTypeLabel }}
        </h1>
      </div>

      <!-- Voucher Details Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
              {{ voucher.merchant?.name || 'Voucher Details' }}
            </h2>
            <p v-if="voucher.code" class="font-mono text-xl text-gray-700 dark:text-gray-300 mt-2">
              {{ voucher.code }}
            </p>
          </div>
          <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusClass">
            {{ statusLabel }}
          </span>
        </div>

        <!-- Financial Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Financial Details</h3>
            <dl class="space-y-2">
              <div v-if="voucher.original_value">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Original Value</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                  {{ formatCurrency(voucher.original_value) }}
                </dd>
              </div>
              <div v-if="voucher.current_value">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Current Value</dt>
                <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                  {{ formatCurrency(voucher.current_value) }}
                </dd>
              </div>
            </dl>
          </div>

          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Important Dates</h3>
            <dl class="space-y-2">
              <div v-if="voucher.issue_date">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Issue Date</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ formatDate(voucher.issue_date) }}</dd>
              </div>
              <div v-if="voucher.expiry_date">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Expiry Date</dt>
                <dd class="font-semibold" :class="{'text-red-600 dark:text-red-400': isExpiringSoon}">
                  {{ formatDate(voucher.expiry_date) }}
                  <span v-if="daysUntilExpiry > 0" class="text-sm text-gray-500 dark:text-gray-400">
                    ({{ daysUntilExpiry }} days remaining)
                  </span>
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Payment Plan Details -->
        <div v-if="isPaymentPlan" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
          <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Payment Plan Details</h3>
          <dl class="grid grid-cols-2 gap-4">
            <div v-if="voucher.installment_count">
              <dt class="text-sm text-blue-700 dark:text-blue-300">Installments</dt>
              <dd class="font-semibold text-blue-900 dark:text-blue-100">{{ voucher.installment_count }} payments</dd>
            </div>
            <div v-if="voucher.monthly_payment">
              <dt class="text-sm text-blue-700 dark:text-blue-300">Monthly Payment</dt>
              <dd class="font-semibold text-blue-900 dark:text-blue-100">{{ formatCurrency(voucher.monthly_payment) }}</dd>
            </div>
            <div v-if="voucher.first_payment_date">
              <dt class="text-sm text-blue-700 dark:text-blue-300">First Payment</dt>
              <dd class="text-blue-900 dark:text-blue-100">{{ formatDate(voucher.first_payment_date) }}</dd>
            </div>
            <div v-if="voucher.final_payment_date">
              <dt class="text-sm text-blue-700 dark:text-blue-300">Final Payment</dt>
              <dd class="text-blue-900 dark:text-blue-100">{{ formatDate(voucher.final_payment_date) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Terms and Conditions -->
        <div v-if="voucher.terms_and_conditions" class="mb-6">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Terms and Conditions</h3>
          <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ voucher.terms_and_conditions }}</p>
        </div>

        <!-- Restrictions -->
        <div v-if="voucher.restrictions" class="mb-6">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Restrictions</h3>
          <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ voucher.restrictions }}</p>
        </div>

        <!-- Redemption Details -->
        <div v-if="voucher.is_redeemed" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Redemption Details</h3>
          <dl class="space-y-2">
            <div>
              <dt class="text-sm text-gray-600 dark:text-gray-400">Redeemed At</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ formatDateTime(voucher.redeemed_at) }}</dd>
            </div>
            <div v-if="voucher.redemption_location">
              <dt class="text-sm text-gray-600 dark:text-gray-400">Location</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ voucher.redemption_location }}</dd>
            </div>
          </dl>
        </div>

        <!-- Actions -->
        <div class="flex space-x-3">
          <button
            v-if="!voucher.is_redeemed && !isExpired"
            @click="markAsRedeemed"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
          >
            Mark as Redeemed
          </button>
          <Link
            v-if="voucher.file_id"
            :href="route('files.show', voucher.file_id)"
            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 rounded-lg transition"
          >
            View Original File
          </Link>
        </div>
      </div>

      <!-- Tags -->
      <div v-if="voucher.tags && voucher.tags.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="tag in voucher.tags"
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
  voucher: {
    type: Object,
    required: true
  }
})

const voucherTypeLabel = computed(() => {
  const labels = {
    'gift_card': 'Gift Card',
    'payment_plan': 'Payment Plan',
    'store_credit': 'Store Credit',
    'coupon': 'Coupon',
  }
  return labels[props.voucher.voucher_type] || 'Voucher'
})

const isPaymentPlan = computed(() => {
  return props.voucher.voucher_type === 'payment_plan'
})

const isExpired = computed(() => {
  return props.voucher.expiry_date && new Date(props.voucher.expiry_date) < new Date()
})

const isExpiringSoon = computed(() => {
  if (!props.voucher.expiry_date) return false
  const daysUntil = Math.ceil(
    (new Date(props.voucher.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  )
  return daysUntil <= 30 && daysUntil > 0
})

const daysUntilExpiry = computed(() => {
  if (!props.voucher.expiry_date) return 0
  return Math.ceil(
    (new Date(props.voucher.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  )
})

const statusLabel = computed(() => {
  if (props.voucher.is_redeemed) return 'Redeemed'
  if (isExpired.value) return 'Expired'
  return 'Active'
})

const statusClass = computed(() => {
  if (props.voucher.is_redeemed) return 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
  if (isExpired.value) return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
  return 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300'
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: props.voucher.currency || 'NOK'
  }).format(amount)
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('no-NO')
}

function formatDateTime(dateTime) {
  return new Date(dateTime).toLocaleString('no-NO')
}

function markAsRedeemed() {
  if (confirm('Are you sure you want to mark this voucher as redeemed?')) {
    router.post(route('vouchers.redeem', props.voucher.id))
  }
}
</script>
