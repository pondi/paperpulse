<template>
  <Head :title="`Contract: ${contract.contract_title || contract.id}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
          {{ contract.contract_title || 'Contract' }}
        </h2>
        <Link
          :href="route('contracts.index')"
          class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
        >
          Back to Contracts
        </Link>
      </div>
    </template>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <div class="flex justify-between items-start">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
              {{ contract.contract_title || 'Contract' }}
            </h1>
            <p v-if="contract.contract_number" class="text-gray-600 dark:text-gray-400 mt-1">
              Contract #{{ contract.contract_number }}
            </p>
          </div>
          <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusClass">
            {{ contract.status }}
          </span>
        </div>
      </div>

      <!-- Contract Details Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 pb-8 border-b border-gray-200 dark:border-gray-700">
          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Contract Type</h3>
            <p class="text-gray-900 dark:text-gray-100 font-medium">{{ formatContractType(contract.contract_type) }}</p>
          </div>

          <div>
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Duration</h3>
            <p class="text-gray-900 dark:text-gray-100">{{ contract.duration || 'Not specified' }}</p>
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div v-if="contract.effective_date">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Effective Date</h3>
            <p class="text-gray-900 dark:text-gray-100">{{ formatDate(contract.effective_date) }}</p>
          </div>
          <div v-if="contract.expiry_date">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Expiry Date</h3>
            <p class="font-semibold" :class="{'text-red-600 dark:text-red-400': isExpiring}">
              {{ formatDate(contract.expiry_date) }}
              <span v-if="daysUntilExpiry > 0" class="text-sm text-gray-500 dark:text-gray-400 block">
                ({{ daysUntilExpiry }} days remaining)
              </span>
            </p>
          </div>
          <div v-if="contract.signature_date">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Signature Date</h3>
            <p class="text-gray-900 dark:text-gray-100">{{ formatDate(contract.signature_date) }}</p>
          </div>
        </div>

        <!-- Parties -->
        <div v-if="contract.parties && contract.parties.length > 0" class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Parties</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="(party, index) in contract.parties"
              :key="index"
              class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4"
            >
              <p class="font-semibold text-gray-900 dark:text-gray-100">{{ party.name }}</p>
              <p v-if="party.role" class="text-sm text-gray-600 dark:text-gray-400">{{ party.role }}</p>
              <p v-if="party.contact" class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ party.contact }}</p>
            </div>
          </div>
        </div>

        <!-- Financial Details -->
        <div v-if="contract.contract_value" class="mb-8 pb-8 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Financial Details</h3>
          <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
            <div class="flex justify-between items-center">
              <span class="text-green-700 dark:text-green-300 font-medium">Contract Value</span>
              <span class="text-2xl font-bold text-green-900 dark:text-green-100">
                {{ formatCurrency(contract.contract_value) }}
              </span>
            </div>
            <div v-if="contract.payment_schedule && contract.payment_schedule.length > 0" class="mt-4">
              <p class="text-sm text-green-700 dark:text-green-300 mb-2">Payment Schedule:</p>
              <ul class="space-y-1 text-sm text-green-800 dark:text-green-200">
                <li v-for="(payment, index) in contract.payment_schedule" :key="index">
                  {{ paymentLabel(payment) }}: {{ formatCurrency(payment.amount) }}
                  <span v-if="paymentDate(payment)">- {{ formatDate(paymentDate(payment)) }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Key Terms -->
        <div v-if="contract.key_terms && contract.key_terms.length > 0" class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Key Terms</h3>
          <ul class="space-y-2">
            <li
              v-for="(term, index) in contract.key_terms"
              :key="index"
              class="flex items-start"
            >
              <svg class="h-5 w-5 text-blue-500 dark:text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700 dark:text-gray-300">{{ term }}</span>
            </li>
          </ul>
        </div>

        <!-- Obligations -->
        <div v-if="contract.obligations && contract.obligations.length > 0" class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Obligations</h3>
          <div class="space-y-3">
            <div
              v-for="(obligation, index) in contract.obligations"
              :key="index"
              class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4"
            >
              <p v-if="obligation.party" class="text-sm text-yellow-800 dark:text-yellow-300 font-medium">
                {{ obligation.party }}
              </p>
              <p class="text-gray-700 dark:text-gray-300 mt-1">{{ obligation.description }}</p>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div v-if="contract.summary" class="mb-8 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
          <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">Summary</h3>
          <p class="text-blue-800 dark:text-blue-200 whitespace-pre-wrap">{{ contract.summary }}</p>
        </div>

        <!-- Renewal & Termination -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div v-if="contract.renewal_terms">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Renewal Terms</h3>
            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ contract.renewal_terms }}</p>
          </div>
          <div v-if="contract.termination_conditions">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Termination Conditions</h3>
            <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ contract.termination_conditions }}</p>
          </div>
        </div>

        <!-- Legal Information -->
        <div v-if="contract.governing_law || contract.jurisdiction" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-8">
          <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Legal Information</h3>
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="contract.governing_law">
              <dt class="text-sm text-gray-600 dark:text-gray-400">Governing Law</dt>
              <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ contract.governing_law }}</dd>
            </div>
            <div v-if="contract.jurisdiction">
              <dt class="text-sm text-gray-600 dark:text-gray-400">Jurisdiction</dt>
              <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ contract.jurisdiction }}</dd>
            </div>
          </dl>
        </div>

        <!-- File Preview & Actions -->
        <div v-if="contract.file" class="mt-8">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Original File</h3>
          <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
            <div v-if="contract.file.has_preview" class="mb-4">
              <img
                :src="contract.file.previewUrl || contract.file.url"
                :alt="contract.contract_title"
                class="w-full rounded-lg"
              />
            </div>
            <div class="flex space-x-3">
              <a
                v-if="contract.file.pdfUrl"
                :href="contract.file.pdfUrl"
                target="_blank"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition inline-flex items-center"
              >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                View PDF
              </a>
              <a
                :href="contract.file.url"
                download
                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 rounded-lg transition inline-flex items-center"
              >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Tags -->
      <div v-if="contract.tags && contract.tags.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Tags</h3>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="tag in contract.tags"
            :key="tag.id"
            class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm"
          >
            {{ tag.name }}
          </span>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  contract: {
    type: Object,
    required: true
  }
})

const isExpiring = computed(() => {
  if (!props.contract.expiry_date) return false
  const daysUntil = Math.ceil(
    (new Date(props.contract.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  )
  return daysUntil <= 90 && daysUntil > 0
})

const daysUntilExpiry = computed(() => {
  if (!props.contract.expiry_date) return 0
  return Math.ceil(
    (new Date(props.contract.expiry_date) - new Date()) / (1000 * 60 * 60 * 24)
  )
})

const statusClass = computed(() => {
  const classes = {
    'draft': 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
    'active': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    'expired': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    'terminated': 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300',
    'renewed': 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
  }
  return classes[props.contract.status] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300'
})

function formatContractType(type) {
  const types = {
    'employment': 'Employment Contract',
    'service': 'Service Agreement',
    'rental': 'Rental Agreement',
    'purchase': 'Purchase Agreement',
    'nda': 'Non-Disclosure Agreement',
  }
  return types[type] || type
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('no-NO', {
    style: 'currency',
    currency: props.contract.currency || 'NOK'
  }).format(amount)
}

function formatDate(date) {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('no-NO')
}

function paymentLabel(payment) {
  return payment.description || payment.milestone || payment.label || 'Payment'
}

function paymentDate(payment) {
  return payment.date || payment.due_date || payment.scheduled_at || null
}
</script>
