<template>
  <div class="relative h-full w-full">
    <!-- Image display -->
    <template v-if="file?.url">
      <img
        :src="file.url"
        :alt="altText"
        class="w-full h-auto object-contain"
        @error="handleImageError"
        :class="{ 'hidden': imageError }"
      />

      <!-- Error state -->
      <div v-if="imageError" class="flex flex-col items-center justify-center h-full bg-gray-50 dark:bg-gray-900">
        <ExclamationCircleIcon class="size-16 text-red-400 mb-4" />
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ errorMessage }}</span>
        <button
          v-if="file.pdfUrl"
          @click="openPdf"
          class="mt-4 text-sm text-indigo-600 hover:text-indigo-500"
        >
          {{ __('try_viewing_pdf_instead') }}
        </button>
      </div>
    </template>

    <!-- No file state -->
    <div v-else class="flex flex-col items-center justify-center h-full bg-gray-50 dark:bg-gray-900">
      <ReceiptRefundIcon class="size-16 text-gray-400 mb-4" />
      <span class="text-sm text-gray-500 dark:text-gray-400">{{ noImageMessage }}</span>
    </div>

    <!-- PDF button overlay (only shows when PDF is available and image loads successfully) -->
    <div v-if="showPdfButton && file?.pdfUrl && !imageError" :class="pdfButtonPosition">
      <button
        @click="openPdf"
        class="inline-flex items-center gap-x-2 px-3 py-2 bg-white dark:bg-gray-800 rounded-md text-sm font-semibold text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 shadow-lg border border-gray-200 dark:border-gray-700"
      >
        <DocumentIcon class="size-4" />
        {{ __('view_pdf') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ExclamationCircleIcon, ReceiptRefundIcon, DocumentIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const __ = (key) => page.props.language?.messages?.[key] || key;

const props = defineProps({
  file: {
    type: Object,
    default: null
  },
  altText: {
    type: String,
    default: 'Receipt image'
  },
  errorMessage: {
    type: String,
    default: 'Failed to load image'
  },
  noImageMessage: {
    type: String,
    default: 'No receipt image available'
  },
  showPdfButton: {
    type: Boolean,
    default: true
  },
  pdfButtonPosition: {
    type: String,
    default: 'absolute bottom-4 right-4'
  }
});

const imageError = ref(false);

const handleImageError = () => {
  imageError.value = true;
};

const openPdf = () => {
  if (props.file?.pdfUrl) {
    window.open(props.file.pdfUrl, '_blank', 'noopener,noreferrer');
  }
};
</script>