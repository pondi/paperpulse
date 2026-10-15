<template>
  <AppLayout title="Contracts">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Agreements</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-100">Contracts</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Keep parties, key dates and agreement status in view.</p>
      </div>

      <!-- Filters -->
      <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label for="contract-type" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Contract type</label>
            <select id="contract-type" v-model="filters.type" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All Types</option>
              <option value="employment">Employment</option>
              <option value="service">Service</option>
              <option value="rental">Rental</option>
              <option value="purchase">Purchase</option>
              <option value="nda">NDA</option>
            </select>
          </div>

          <div>
            <label for="contract-status" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</label>
            <select id="contract-status" v-model="filters.status" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="">All</option>
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="terminated">Terminated</option>
              <option value="renewed">Renewed</option>
            </select>
          </div>

          <div>
            <label for="contract-sort" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sort by</label>
            <select id="contract-sort" v-model="filters.sort" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="effective_date">Effective date</option><option value="expiry_date">Expiry date</option><option value="contract_title">Title</option><option value="contract_value">Contract value</option>
            </select>
          </div>

          <div>
            <label for="contract-sort-direction" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Order</label>
            <select id="contract-sort-direction" v-model="filters.sort_direction" @change="applyFilters" class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
              <option value="asc">Ascending</option><option value="desc">Descending</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label for="contract-search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Search contracts</label>
            <input
              id="contract-search"
              v-model="filters.search"
              @input="applyFilters"
              type="search"
              placeholder="Title or contract number"
              class="w-full rounded-lg border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100"
            />
          </div>
        </div>
      </div>

      <!-- Contracts List -->
      <div v-if="contracts.length > 0" class="space-y-3">
        <ContractCard
          v-for="contract in contracts"
          :key="contract.id"
          :contract="contract"
        />
      </div>

      <div v-else class="text-center py-12">
        <div class="text-gray-400 dark:text-gray-500 mb-2">
          <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400">{{ hasFilters ? 'No contracts match these filters.' : 'No contracts yet.' }}</p>
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
import ContractCard from '@/Components/Entities/ContractCard.vue'

const props = defineProps({
  contracts: {
    type: Array,
    required: true
  },
  filters: Object,
  pagination: Object,
})

const filters = ref({ sort: 'effective_date', sort_direction: 'asc', search: '', status: '', type: '', ...props.filters })
const hasFilters = computed(() => Boolean(filters.value.search || filters.value.status || filters.value.type))

function applyFilters() {
  router.get(route('contracts.index'), { ...filters.value, page: 1 }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

</script>
