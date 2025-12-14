<template>
  <Modal :show="show" @close="close" max-width="6xl">
    <div class="flex h-[80vh]">
      <!-- Left Panel - File Preview -->
      <div class="flex-1 bg-gray-50 dark:bg-gray-900 overflow-auto border-r border-gray-200 dark:border-gray-700">
        <!-- PDF Viewer -->
        <template v-if="item?.file?.pdfUrl">
          <iframe
            :src="item.file.pdfUrl"
            class="w-full h-full border-0"
            title="Document Viewer"
          ></iframe>
        </template>

        <!-- Image Preview -->
        <template v-else-if="item?.type === 'receipt'">
          <ReceiptImage
            v-if="item.file"
            :file="item.file"
            :alt-text="item.title"
            error-message="Failed to load receipt image"
            no-image-message="No receipt image available"
            :show-pdf-button="false"
          />
        </template>
        <template v-else-if="item?.type === 'document'">
          <DocumentImage
            v-if="item.file"
            :file="item.file"
            :alt-text="item.title"
            error-message="Failed to load document preview"
            no-image-message="No document preview available"
            :show-pdf-button="false"
          />
        </template>

        <!-- No Preview Available -->
        <template v-else>
          <div class="flex flex-col items-center justify-center h-full bg-gray-50 dark:bg-gray-900">
            <DocumentIcon class="size-16 text-gray-400 mb-4" />
            <span class="text-sm text-gray-500 dark:text-gray-400">No preview available</span>
          </div>
        </template>
      </div>

      <!-- Right Panel - Details -->
      <div class="w-96 bg-white dark:bg-gray-800 overflow-y-auto">
        <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex justify-between items-start z-10">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <ReceiptRefundIcon v-if="item?.type === 'receipt'" class="size-5 text-gray-400" />
              <DocumentIcon v-else class="size-5 text-gray-400" />
              <span class="text-xs font-medium text-gray-500 uppercase">{{ item?.type }}</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white line-clamp-2">
              {{ item?.title }}
            </h3>
          </div>
          <button
            @click="close"
            class="ml-4 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
          >
            <XMarkIcon class="size-6" />
          </button>
        </div>

        <div class="p-4 space-y-4">
          <!-- Receipt Details -->
          <template v-if="item?.type === 'receipt'">
            <div v-if="item.total" class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
              <div class="text-sm text-indigo-600 dark:text-indigo-400 font-medium mb-1">Total Amount</div>
              <div class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ item.total }}</div>
            </div>

            <div v-if="item.date">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Date</div>
              <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(item.date) }}</div>
            </div>

            <div v-if="item.category">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Category</div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                {{ item.category }}
              </span>
            </div>

            <div v-if="item.description">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Description</div>
              <div class="text-sm text-gray-900 dark:text-white">{{ item.description }}</div>
            </div>

            <div v-if="item.items && item.items.length > 0">
              <div class="text-xs font-medium text-gray-500 uppercase mb-2">Line Items ({{ item.items.length }})</div>
              <div class="space-y-2">
                <div
                  v-for="(lineItem, idx) in item.items"
                  :key="idx"
                  class="text-sm p-2 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700"
                >
                  <div class="font-medium text-gray-900 dark:text-white">{{ lineItem.description }}</div>
                  <div class="text-xs text-gray-500 mt-1">
                    Qty: {{ lineItem.quantity }} × {{ lineItem.price }}
                  </div>
                </div>
              </div>
            </div>

            <div v-if="item.tags && item.tags.length > 0">
              <div class="text-xs font-medium text-gray-500 uppercase mb-2">Tags</div>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="tag in item.tags"
                  :key="tag"
                  class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </template>

          <!-- Document Details -->
          <template v-else-if="item?.type === 'document'">
            <div v-if="item.date">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Date</div>
              <div class="text-sm text-gray-900 dark:text-white">{{ formatDate(item.date) }}</div>
            </div>

            <div v-if="item.document_type">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Document Type</div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                {{ item.document_type }}
              </span>
            </div>

            <div v-if="item.description">
              <div class="text-xs font-medium text-gray-500 uppercase mb-1">Description</div>
              <div class="text-sm text-gray-900 dark:text-white">{{ item.description }}</div>
            </div>

            <div v-if="item.tags && item.tags.length > 0">
              <div class="text-xs font-medium text-gray-500 uppercase mb-2">Tags</div>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="tag in item.tags"
                  :key="tag"
                  class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </template>

          <!-- Actions -->
          <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
            <Link
              :href="item?.url || '#'"
              class="w-full inline-flex justify-center items-center gap-x-2 px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <ArrowTopRightOnSquareIcon class="size-4" />
              Open Full View
            </Link>
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/Common/Modal.vue';
import ReceiptImage from '@/Components/Domain/ReceiptImage.vue';
import DocumentImage from '@/Components/Domain/DocumentImage.vue';
import {
  XMarkIcon,
  DocumentIcon,
  ReceiptRefundIcon,
  ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  item: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close']);

const close = () => {
  emit('close');
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};
</script>
