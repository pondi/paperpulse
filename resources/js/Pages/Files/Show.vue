<template>
  <Head title="File Details" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
          {{ file.original_name }}
        </h2>
        <Link
          :href="route('files.index')"
          class="inline-flex items-center px-4 py-2 bg-zinc-800 dark:bg-zinc-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700 dark:hover:bg-zinc-600 transition"
        >
          Back to Files
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Extraction Issues Warning -->
        <div v-if="file.extraction?.has_extraction_issues" class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-yellow-700 dark:text-yellow-200">
                Some information could not be extracted from this file.
                {{ file.extraction.entities_created }} of {{ file.extraction.entities_detected }} entities were saved.
              </p>
              <a :href="route('api.files.extraction-report', file.id)" target="_blank" class="mt-2 inline-block text-sm font-medium text-yellow-700 dark:text-yellow-200 underline">
                View extraction report
              </a>
            </div>
          </div>
        </div>

        <!-- File Information Card -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 mb-6 border border-zinc-200 dark:border-zinc-700">
          <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">File Information</h3>

          <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="font-medium text-zinc-500 dark:text-zinc-400">Status</dt>
              <dd class="mt-1">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="getStatusClass(file.status)"
                >
                  {{ file.status }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="font-medium text-zinc-500 dark:text-zinc-400">File Type</dt>
              <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ file.file_type }}</dd>
            </div>
            <div>
              <dt class="font-medium text-zinc-500 dark:text-zinc-400">Uploaded</dt>
              <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ formatDateTime(file.uploaded_at) }}</dd>
            </div>
            <div v-if="file.processing_provider">
              <dt class="font-medium text-zinc-500 dark:text-zinc-400">Processing Provider</dt>
              <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ file.processing_provider }}</dd>
            </div>
          </dl>
        </div>

        <!-- Extracted Entities Section -->
        <div v-if="extractedEntities && extractedEntities.length > 0" class="space-y-6">
          <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Extracted Information</h3>

          <div class="space-y-4">
            <div v-for="extraction in extractedEntities" :key="`${extraction.entity_type}-${extraction.entity_id}`" class="space-y-2">
              <!-- Entity Type Badge -->
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 capitalize">
                  {{ formatEntityType(extraction.entity_type) }}
                </span>
                <span
                  v-if="extraction.is_primary"
                  class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-xs rounded font-medium"
                >
                  Primary
                </span>
                <span
                  v-if="extraction.confidence_score"
                  class="px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400 text-xs rounded"
                >
                  {{ Math.round(extraction.confidence_score * 100) }}% confidence
                </span>
              </div>

              <!-- Dynamic Entity Card -->
              <component
                :is="getEntityComponent(extraction.entity_type)"
                v-if="extraction.entity"
                :="getEntityProps(extraction.entity_type, extraction.entity)"
                @view="viewEntity(extraction.entity_type, $event)"
                @redeem="redeemVoucher"
              />
            </div>
          </div>
        </div>

        <!-- Legacy Data Display (for backward compatibility) -->
        <div v-else-if="hasLegacyData" class="space-y-6">
          <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <p class="text-sm text-yellow-800 dark:text-yellow-300">
              This file was processed using the legacy system. Multi-entity extraction is not available.
            </p>
          </div>

          <div v-if="file.primary_receipt">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Receipt Information</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
              <Link :href="route('receipts.show', file.primary_receipt.id)" class="text-blue-600 dark:text-blue-400 hover:underline">
                View full receipt details →
              </Link>
            </p>
          </div>

          <div v-if="file.primary_document">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Document Information</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
              <Link :href="route('documents.show', file.primary_document.id)" class="text-blue-600 dark:text-blue-400 hover:underline">
                View full document details →
              </Link>
            </p>
          </div>
        </div>

        <!-- No Data -->
        <div v-else class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-8 text-center">
          <p class="text-zinc-600 dark:text-zinc-400">
            No extracted data available for this file.
          </p>
          <p v-if="file.status === 'processing'" class="text-sm text-zinc-500 dark:text-zinc-500 mt-2">
            The file is currently being processed. Please check back later.
          </p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VoucherCard from '@/Components/Entities/VoucherCard.vue';
import WarrantyCard from '@/Components/Entities/WarrantyCard.vue';
import ReturnPolicyCard from '@/Components/Entities/ReturnPolicyCard.vue';
import InvoiceCard from '@/Components/Entities/InvoiceCard.vue';
import ContractCard from '@/Components/Entities/ContractCard.vue';
import BankStatementCard from '@/Components/Entities/BankStatementCard.vue';

const props = defineProps({
  file: {
    type: Object,
    required: true
  },
  extractedEntities: {
    type: Array,
    default: () => []
  },
  hasLegacyData: {
    type: Boolean,
    default: false
  }
});

const formatEntityType = (type) => {
  return type.replace(/_/g, ' ');
};

const getEntityComponent = (entityType) => {
  const componentMap = {
    'voucher': VoucherCard,
    'warranty': WarrantyCard,
    'return_policy': ReturnPolicyCard,
    'invoice': InvoiceCard,
    'contract': ContractCard,
    'bank_statement': BankStatementCard,
  };
  return componentMap[entityType];
};

const getEntityProps = (entityType, entity) => {
  const propsMap = {
    'voucher': { voucher: entity },
    'warranty': { warranty: entity },
    'return_policy': { returnPolicy: entity },
    'invoice': { invoice: entity },
    'contract': { contract: entity },
    'bank_statement': { statement: entity },
  };
  return propsMap[entityType] || {};
};

const viewEntity = (entityType, entityId) => {
  const routeMap = {
    'voucher': 'vouchers.show',
    'warranty': 'warranties.show',
    'return_policy': 'return-policies.show',
    'invoice': 'invoices.show',
    'contract': 'contracts.show',
    'bank_statement': 'bank-statements.show',
  };

  const routeName = routeMap[entityType];
  if (routeName) {
    router.visit(route(routeName, entityId));
  }
};

const redeemVoucher = (voucherId) => {
  router.post(route('vouchers.redeem', voucherId), {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Optionally show a success message
    }
  });
};

const getStatusClass = (status) => {
  const classes = {
    'completed': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
    'processing': 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    'pending': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
    'failed': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
  };
  return classes[status] || 'bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300';
};

const formatDateTime = (dateTime) => {
  if (!dateTime) return '';
  return new Date(dateTime).toLocaleString('no-NO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>
