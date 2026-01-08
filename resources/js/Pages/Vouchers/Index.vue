<template>
  <AppLayout title="Vouchers">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">My Vouchers</h1>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Type</label>
            <select v-model="filters.type" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All Types</option>
              <option value="gift_card">Gift Cards</option>
              <option value="payment_plan">Payment Plans</option>
              <option value="store_credit">Store Credit</option>
              <option value="coupon">Coupons</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Status</label>
            <select v-model="filters.status" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="redeemed">Redeemed</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Search</label>
            <input
              v-model="filters.search"
              @input="applyFilters"
              type="text"
              placeholder="Search by code or merchant..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- Vouchers Grid -->
      <div v-if="filteredVouchers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <VoucherCard
          v-for="voucher in filteredVouchers"
          :key="voucher.id"
          :voucher="voucher"
          @redeem="markAsRedeemed"
          @view="viewVoucher"
        />
      </div>

      <div v-else class="text-center py-12">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400">No vouchers found matching your filters.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import VoucherCard from '@/Components/Entities/VoucherCard.vue'

const props = defineProps({
  vouchers: {
    type: Array,
    required: true
  }
})

const filters = ref({
  type: '',
  status: '',
  search: ''
})

const filteredVouchers = computed(() => {
  let result = props.vouchers

  if (filters.value.type) {
    result = result.filter(v => v.voucher_type === filters.value.type)
  }

  if (filters.value.status === 'active') {
    result = result.filter(v => !v.is_redeemed && !isExpired(v))
  } else if (filters.value.status === 'expired') {
    result = result.filter(v => isExpired(v))
  } else if (filters.value.status === 'redeemed') {
    result = result.filter(v => v.is_redeemed)
  }

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(v =>
      (v.code && v.code.toLowerCase().includes(search)) ||
      (v.merchant_name && v.merchant_name.toLowerCase().includes(search))
    )
  }

  return result
})

function isExpired(voucher) {
  return voucher.expiry_date && new Date(voucher.expiry_date) < new Date()
}

function applyFilters() {
  // Optionally, you can debounce this or update URL params
  // For now, the computed property handles filtering reactively
}

function viewVoucher(id) {
  router.visit(route('vouchers.show', id))
}

function markAsRedeemed(id) {
  if (!confirm('Are you sure you want to mark this voucher as redeemed?')) {
    return
  }

  router.post(route('vouchers.redeem', id), {}, {
    preserveScroll: true,
  })
}
</script>
