<template>
  <AppLayout title="Contracts">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Contracts</h1>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Contract Type</label>
            <select v-model="filters.type" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All Types</option>
              <option value="employment">Employment</option>
              <option value="service">Service</option>
              <option value="rental">Rental</option>
              <option value="purchase">Purchase</option>
              <option value="nda">NDA</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Status</label>
            <select v-model="filters.status" @change="applyFilters" class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
              <option value="">All</option>
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="terminated">Terminated</option>
              <option value="renewed">Renewed</option>
            </select>
          </div>

          <div class="col-span-1 md:col-span-2">
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Search</label>
            <input
              v-model="filters.search"
              @input="applyFilters"
              type="text"
              placeholder="Search by title or contract number..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
            />
          </div>
        </div>
      </div>

      <!-- Contracts List -->
      <div v-if="filteredContracts.length > 0" class="space-y-4">
        <ContractCard
          v-for="contract in filteredContracts"
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
        <p class="text-gray-500 dark:text-gray-400">No contracts found matching your filters.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContractCard from '@/Components/Entities/ContractCard.vue'

const props = defineProps({
  contracts: {
    type: Array,
    required: true
  }
})

const filters = ref({
  type: '',
  status: '',
  search: ''
})

const filteredContracts = computed(() => {
  let result = props.contracts

  if (filters.value.type) {
    result = result.filter(c => c.contract_type === filters.value.type)
  }

  if (filters.value.status) {
    result = result.filter(c => c.status === filters.value.status)
  }

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(c =>
      (c.contract_title && c.contract_title.toLowerCase().includes(search)) ||
      (c.contract_number && c.contract_number.toLowerCase().includes(search))
    )
  }

  return result
})

function applyFilters() {
  // Filters are applied reactively via computed property
}
</script>
