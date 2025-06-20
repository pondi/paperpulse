<template>
  <Head :title="__('receipts')" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('receipts') }}</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto transition-all duration-500 ease-in-out" :style="containerStyle">
        <div class="transition-all duration-500 ease-in-out">
          <!-- Flash Message -->
          <div v-if="$page.props.flash.success" class="mb-8 rounded-md bg-green-50 p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
              </div>
            </div>
          </div>

          <template v-if="receipts.length > 0">
            <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-800">
              <li v-for="receipt in receipts" :key="receipt.id" class="relative flex items-center space-x-4 py-4">
                <div class="min-w-0 flex-auto">
                  <div class="flex items-center gap-x-3">
                    <div :class="[getStatusClass(receipt), 'flex-none rounded-full p-1']">
                      <div class="size-2 rounded-full bg-current" />
                    </div>
                    <h2 class="min-w-0 text-sm font-semibold text-gray-900 dark:text-gray-100">
                      <button @click="openReceipt(receipt)" class="flex gap-x-2">
                        <span class="truncate">{{ receipt.merchant?.name || __('unknown_merchant') }}</span>
                        <span class="text-gray-400">/</span>
                        <span class="whitespace-nowrap">{{ formatCurrency(receipt.total_amount, receipt.currency) }}</span>
                        <span class="absolute inset-0" />
                      </button>
                    </h2>
                  </div>
                  <div class="mt-3 flex items-center gap-x-2.5 text-xs text-gray-500 dark:text-gray-400">
                    <p class="truncate">{{ receipt.receipt_description || __('no_description') }}</p>
                    <svg viewBox="0 0 2 2" class="size-0.5 flex-none fill-gray-300">
                      <circle cx="1" cy="1" r="1" />
                    </svg>
                    <p class="whitespace-nowrap">{{ formatDate(receipt.receipt_date) }}</p>
                  </div>
                </div>
                <div :class="[getCategoryClass(receipt.receipt_category), 'flex-none rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset']">
                  {{ receipt.receipt_category || __('uncategorized') }}
                </div>
                <ChevronRightIcon class="size-5 flex-none text-gray-400" aria-hidden="true" />
              </li>
            </ul>
          </template>
          <template v-else>
            <div class="bg-gray-900 px-6 py-24 sm:py-32 lg:px-8">
              <div class="mx-auto max-w-2xl text-center">
                <p class="text-base font-semibold text-indigo-600">{{ __('no_receipts_found') }}</p>
                <h2 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">{{ __('upload_first_receipts') }}</h2>
                <p class="mt-6 text-lg leading-8 text-gray-300">{{ __('no_receipts_description') }}</p>
                <div class="mt-10 flex items-center justify-center gap-x-6">
                  <Link :href="route('documents.upload')" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ __('upload_receipts') }}
                  </Link>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Receipt Drawer -->
    <TransitionRoot as="template" :show="isDrawerOpen">
      <div class="fixed right-0 flex max-w-full z-10" style="top: 65px; bottom: 0;">
        <TransitionChild
          as="template"
          enter="transform transition ease-in-out duration-500"
          enter-from="translate-x-full"
          enter-to="translate-x-0"
          leave="transform transition ease-in-out duration-500"
          leave-from="translate-x-0"
          leave-to="translate-x-full"
        >
          <div ref="drawerRef" class="w-screen sm:w-[500px] bg-white dark:bg-gray-800 shadow-xl border-l border-gray-200 dark:border-gray-700 h-full">
            <div class="flex h-full flex-col overflow-y-scroll">
              <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-4 py-6 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between">
                  <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ selectedReceipt?.merchant?.name || __('unknown_merchant') }}
                  </h2>
                  <div class="ml-3 flex h-7 items-center">
                    <button type="button" class="relative rounded-md bg-white dark:bg-gray-800 text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500" @click="closeDrawer">
                      <span class="absolute -inset-2.5" />
                      <span class="sr-only">{{ __('close') }}</span>
                      <XMarkIcon class="size-6" aria-hidden="true" />
                    </button>
                  </div>
                </div>
              </div>

              <div class="relative flex-1 px-4 sm:px-6">
                <!-- Main -->
                <div>
                  <div class="pb-1 sm:pb-6">
                    <div>
                      <div class="relative h-[600px] overflow-y-auto">
                        <div class="flex justify-center bg-white">
                          <div class="w-[400px]">
                            <template v-if="selectedReceipt?.file?.url">
                              <img 
                                :src="selectedReceipt.file.url"
                                class="w-full h-auto"
                                :alt="__('receipt_image')"
                                @error="handleImageError"
                                :class="{ 'hidden': imageError }"
                              />
                              <div v-if="imageError" class="flex flex-col items-center justify-center h-[600px] bg-white border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                                <ExclamationCircleIcon class="size-16 text-red-400 mb-4" />
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('receipt_image_load_error') }}</span>
                              </div>
                            </template>
                            <div v-else class="flex flex-col items-center justify-center h-[600px] bg-white border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg">
                              <ReceiptPercentIcon class="size-16 text-gray-400 mb-4" />
                              <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('no_receipt_image') }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="mt-6 px-4 sm:mt-8 sm:flex sm:items-end sm:px-6">
                        <div class="sm:flex-1">
                          <div>
                            <div class="flex items-center">
                              <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 sm:text-2xl">
                                {{ formatCurrency(selectedReceipt?.total_amount, selectedReceipt?.currency) }}
                              </h3>
                              <span :class="[getStatusClass(selectedReceipt), 'ml-2.5 inline-block size-2 shrink-0 rounded-full']">
                                <span class="sr-only">Status</span>
                              </span>
                            </div>
                            <p class="text-sm text-gray-500">{{ formatDate(selectedReceipt?.receipt_date) }}</p>
                          </div>
                          <div class="mt-5 flex flex-wrap space-y-3 sm:space-x-3 sm:space-y-0">
                            <button
                              v-if="selectedReceipt?.file?.pdfUrl"
                              @click="openPdf(selectedReceipt.file.pdfUrl)"
                              type="button"
                              class="inline-flex w-full flex-1 items-center justify-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                              <span>{{ __('view_pdf') }}</span>
                              <ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" />
                            </button>
                            <button
                              type="button"
                              class="inline-flex w-full flex-1 items-center justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                              {{ __('share') }}
                            </button>
                            <Menu as="div" class="relative inline-block text-left">
                              <MenuButton class="relative inline-flex items-center rounded-md bg-white dark:bg-gray-800 p-2 text-gray-400 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <span class="absolute -inset-1" />
                                <span class="sr-only">{{ __('open_options') }}</span>
                                <EllipsisVerticalIcon class="size-5" aria-hidden="true" />
                              </MenuButton>
                              <transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="transform opacity-0 scale-95"
                                enter-to-class="transform opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="transform opacity-100 scale-100"
                                leave-to-class="transform opacity-0 scale-95"
                              >
                                <MenuItems class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 focus:outline-none">
                                  <div class="py-1">
                                    <MenuItem v-slot="{ active }">
                                      <button
                                        @click="router.visit(route('receipts.show', selectedReceipt?.id))"
                                        :class="[active ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300', 'block w-full text-left px-4 py-2 text-sm']"
                                      >
                                        {{ __('edit') }}
                                      </button>
                                    </MenuItem>
                                    <MenuItem v-slot="{ active }">
                                      <button
                                        @click="showDeleteConfirm = true"
                                        :class="[active ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300', 'block w-full text-left px-4 py-2 text-sm']"
                                      >
                                        {{ __('delete') }}
                                      </button>
                                    </MenuItem>
                                  </div>
                                </MenuItems>
                              </transition>
                            </Menu>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="px-4 pb-5 pt-5 sm:px-0 sm:pt-0">
                      <dl class="space-y-8 px-4 sm:space-y-6 sm:px-6">
                        <div>
                          <dt class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">{{ __('category') }}</dt>
                          <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2">
                            {{ selectedReceipt?.receipt_category || __('uncategorized') }}
                          </dd>
                        </div>
                        <div>
                          <dt class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">{{ __('tax_amount') }}</dt>
                          <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2">
                            {{ formatCurrency(selectedReceipt?.tax_amount, selectedReceipt?.currency) }}
                          </dd>
                        </div>
                        <div>
                          <dt class="text-sm font-medium text-gray-500 sm:w-40 sm:shrink-0">{{ __('description') }}</dt>
                          <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2">
                            {{ selectedReceipt?.receipt_description || __('no_description') }}
                          </dd>
                        </div>
                      </dl>

                      <!-- Line Items -->
                      <div v-if="selectedReceipt?.lineItems?.length > 0" class="mt-8 px-4 sm:px-6">
                        <h3 class="text-sm font-medium text-gray-500">{{ __('line_items') }}</h3>
                        <div class="mt-4">
                          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                              <tr>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('description') }}</th>
                                <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('quantity') }}</th>
                                <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('unit_price') }}</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                              <tr v-for="item in selectedReceipt.lineItems" :key="item.id">
                                <td class="whitespace-nowrap py-4 pl-3 pr-4 text-sm text-gray-500 dark:text-gray-400">
                                  {{ item.description }}
                                  <span v-if="item.sku" class="text-gray-400">({{ item.sku }})</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 text-right">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 text-right">
                                  {{ formatCurrency(item.unit_price, selectedReceipt.currency) }}
                                </td>
                              </tr>
                            </tbody>
                            <tfoot>
                              <tr>
                                <th colspan="2" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('total') }}</th>
                                <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                  {{ formatCurrency(selectedReceipt?.total_amount, selectedReceipt?.currency) }}
                                </th>
                              </tr>
                            </tfoot>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </TransitionChild>
      </div>
    </TransitionRoot>

    <!-- Delete Confirmation Dialog -->
    <TransitionRoot as="template" :show="showDeleteConfirm">
      <Dialog as="div" class="relative z-50" @close="showDeleteConfirm = false">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />

        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                  <ExclamationTriangleIcon class="h-6 w-6 text-red-600" aria-hidden="true" />
                </div>
                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                  <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">
                    {{ __('delete_receipt') }}
                  </DialogTitle>
                  <div class="mt-2">
                    <p class="text-sm text-gray-500">
                      {{ __('delete_receipt_confirm') }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button
                  type="button"
                  class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                  @click="deleteReceipt"
                >
                  {{ __('delete') }}
                </button>
                <button
                  type="button"
                  class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto"
                  @click="showDeleteConfirm = false"
                >
                  {{ __('cancel') }}
                </button>
              </div>
            </DialogPanel>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ChevronRightIcon, EllipsisVerticalIcon } from '@heroicons/vue/20/solid';
import { 
  XMarkIcon, 
  ReceiptRefundIcon, 
  ArrowTopRightOnSquareIcon, 
  ExclamationTriangleIcon,
  ExclamationCircleIcon,
  ReceiptPercentIcon,
  CheckCircleIcon 
} from '@heroicons/vue/24/outline';
import {
  Dialog,
  DialogPanel,
  Menu,
  MenuButton,
  MenuItem,
  MenuItems,
  TransitionChild,
  TransitionRoot,
  DialogTitle,
} from '@headlessui/vue';

const page = usePage();
const __ = (key) => {
  const messages = page.props.language?.messages || {};
  const parts = key.split('.');
  let value = messages;
  
  for (const part of parts) {
    value = value?.[part];
    if (value === undefined) break;
  }
  
  return value || key.split('.').pop();
};

const props = defineProps({
  receipts: {
    type: Array,
    required: true
  }
});

const isDrawerOpen = ref(false);
const selectedReceipt = ref(null);
const showDeleteConfirm = ref(false);
const imageError = ref(false);

const drawerRef = ref(null);
const DRAWER_GAP = 8;

const containerStyle = computed(() => ({
  paddingLeft: '1.5rem',
  paddingRight: isDrawerOpen.value ? (window.innerWidth < 640 ? '0' : '608px') : '1.5rem',
  maxWidth: '100vw',
  transition: 'padding-right 500ms ease-in-out'
}));

const updateContainerStyle = () => {
  if (isDrawerOpen.value) {
    containerStyle.value;  // Trigger recompute
  }
};

const openReceipt = async (receipt) => {
  selectedReceipt.value = receipt;
  imageError.value = false;
  isDrawerOpen.value = true;
};

const closeDrawer = () => {
  isDrawerOpen.value = false;
  setTimeout(() => {
    selectedReceipt.value = null;
    showDeleteConfirm.value = false;
  }, 500);
};

const deleteReceipt = () => {
  if (!selectedReceipt.value) return;
  
  router.delete(route('receipts.destroy', selectedReceipt.value.id), {
    onSuccess: () => {
      closeDrawer();
    },
  });
};

const getStatusClass = (receipt) => {
  if (!receipt?.merchant_id) return 'text-gray-500 bg-gray-100/10'
  if (receipt?.total_amount === null) return 'text-rose-400 bg-rose-400/10'
  return 'text-green-400 bg-green-400/10'
}

const getCategoryClass = (category) => {
  const classes = {
    'mat': 'text-green-400 bg-green-400/10 ring-green-400/30',
    'transport': 'text-blue-400 bg-blue-400/10 ring-blue-400/30',
    'default': 'text-gray-400 bg-gray-400/10 ring-gray-400/20'
  }
  return classes[category] || classes.default
}

const formatDate = (date) => {
  if (!date) return __('no_date')
  return new Date(date).toLocaleDateString('nb-NO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatCurrency = (amount, currency) => {
  if (!amount) return '0,00'
  return new Intl.NumberFormat('nb-NO', {
    style: 'currency',
    currency: currency || 'NOK'
  }).format(amount)
}

const openPdf = (url) => {
  window.open(url, '_blank', 'noopener,noreferrer')
}

const handleImageError = () => {
  imageError.value = true;
};

const handleKeyDown = (event) => {
  if (event.key === 'Escape' && isDrawerOpen.value) {
    closeDrawer();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleKeyDown);
  window.addEventListener('resize', updateContainerStyle);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown);
  window.removeEventListener('resize', updateContainerStyle);
});
</script> 