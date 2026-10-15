<template>
  <AppLayout title="Vouchers">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <p class="text-sm font-medium text-violet-700 dark:text-violet-300">Financial records</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-100">Vouchers</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Find remaining value, expiry and redemption status.</p>
      </div>

      <!-- Filters -->
      <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label for="voucher-type" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Type</label>
            <select id="voucher-type" v-model="filters.type" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All Types</option>
              <option value="gift_card">Gift Cards</option>
              <option value="payment_plan">Payment Plans</option>
              <option value="store_credit">Store Credit</option>
              <option value="coupon">Coupons</option>
            </select>
          </div>

          <div>
            <label for="voucher-status" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</label>
            <select id="voucher-status" v-model="filters.status" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="redeemed">Redeemed</option>
            </select>
          </div>

          <div>
            <label for="voucher-sort" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sort by</label>
            <select id="voucher-sort" v-model="filters.sort" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="expiry_date">Expiry date</option><option value="issue_date">Issue date</option><option value="current_value">Remaining value</option><option value="code">Code</option>
            </select>
          </div>

          <div>
            <label for="voucher-sort-direction" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Order</label>
            <select id="voucher-sort-direction" v-model="filters.sort_direction" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="asc">Ascending</option><option value="desc">Descending</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label for="voucher-search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Search vouchers</label>
            <input
              id="voucher-search"
              v-model="filters.search"
              @input="applyFilters"
              type="search"
              placeholder="Code or merchant"
              class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100"
            />
          </div>
        </div>
      </div>

      <!-- Vouchers Grid -->
      <div v-if="vouchers.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <VoucherCard
          v-for="voucher in vouchers"
          :key="voucher.id"
          :voucher="voucher"
          @redeem="markAsRedeemed"
        />
      </div>

      <div v-else class="text-center py-12">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400">{{ hasFilters ? 'No vouchers match these filters.' : 'No vouchers yet.' }}</p>
      </div>
      <Pagination v-bind="pagination" />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import VoucherCard from '@/Components/Entities/VoucherCard.vue'

const props = defineProps({
  vouchers: {
    type: Array,
    required: true
  },
  filters: Object,
  pagination: Object,
})

const filters = ref({ sort: 'expiry_date', sort_direction: 'asc', search: '', status: '', type: '', ...props.filters })
const hasFilters = computed(() => Boolean(filters.value.search || filters.value.status || filters.value.type))

function applyFilters() {
  router.get(route('vouchers.index'), { ...filters.value, page: 1 }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
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
