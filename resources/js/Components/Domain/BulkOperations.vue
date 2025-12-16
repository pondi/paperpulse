<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="transform opacity-0 scale-95"
    enter-to-class="transform opacity-100 scale-100"
    leave-active-class="transition ease-in duration-75"
    leave-from-class="transform opacity-100 scale-100"
    leave-to-class="transform opacity-0 scale-95"
  >
    <div v-if="selectedCount > 0" class="fixed bottom-0 inset-x-0 pb-2 sm:pb-5">
      <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
        <div class="p-2 rounded-lg bg-zinc-900 shadow-lg sm:p-3">
          <div class="flex items-center justify-between flex-wrap">
            <div class="w-0 flex-1 flex items-center">
              <span class="flex p-2 rounded-lg bg-zinc-800">
                <CheckCircleIcon class="h-6 w-6 text-white" aria-hidden="true" />
              </span>
              <p class="ml-3 font-medium text-white truncate">
                <span class="md:hidden">{{ selectedCount }} selected</span>
                <span class="hidden md:inline">{{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }} selected</span>
              </p>
            </div>
            <div class="order-3 mt-2 flex-shrink-0 w-full sm:order-2 sm:mt-0 sm:w-auto">
              <div class="flex space-x-2">
                <button
                  @click="showCategorizeModal = true"
                  type="button"
                  class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-amber-600 bg-white hover:bg-amber-50"
                >
                  <FolderIcon class="-ml-1 mr-2 h-4 w-4" aria-hidden="true" />
                  Categorize
                </button>
                <Menu as="div" class="relative inline-block text-left">
                  <MenuButton class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-amber-600 bg-white hover:bg-amber-50">
                    <ArrowDownTrayIcon class="-ml-1 mr-2 h-4 w-4" aria-hidden="true" />
                    Export
                    <ChevronDownIcon class="ml-2 -mr-1 h-4 w-4" aria-hidden="true" />
                  </MenuButton>
                  <transition
                    enter-active-class="transition ease-out duration-100"
                    enter-from-class="transform opacity-0 scale-95"
                    enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="transform opacity-100 scale-100"
                    leave-to-class="transform opacity-0 scale-95"
                  >
                    <MenuItems class="absolute bottom-full mb-2 right-0 w-48 origin-bottom-right rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-none">
                      <div class="py-1">
                        <MenuItem v-slot="{ active }">
                          <button
                            @click="exportSelected('csv')"
                            :class="[active ? 'bg-amber-100 text-zinc-900' : 'text-zinc-700', 'block w-full text-left px-4 py-2 text-sm']"
                          >
                            Export as CSV
                          </button>
                        </MenuItem>
                        <MenuItem v-slot="{ active }">
                          <button
                            @click="exportSelected('pdf')"
                            :class="[active ? 'bg-amber-100 text-zinc-900' : 'text-zinc-700', 'block w-full text-left px-4 py-2 text-sm']"
                          >
                            Export as PDF
                          </button>
                        </MenuItem>
                      </div>
                    </MenuItems>
                  </transition>
                </Menu>
                <button
                  @click="showDeleteModal = true"
                  type="button"
                  class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                >
                  <TrashIcon class="-ml-1 mr-2 h-4 w-4" aria-hidden="true" />
                  Delete
                </button>
              </div>
            </div>
            <div class="order-2 flex-shrink-0 sm:order-3 sm:ml-2">
              <button
                @click="$emit('clear-selection')"
                type="button"
                class="-mr-1 flex p-2 rounded-md hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-white"
              >
                <span class="sr-only">Clear selection</span>
                <XMarkIcon class="h-6 w-6 text-white" aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- Delete Confirmation Modal -->
  <TransitionRoot as="template" :show="showDeleteModal">
    <Dialog as="div" class="relative z-50" @close="showDeleteModal = false">
      <div class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" />

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <ExclamationTriangleIcon class="h-6 w-6 text-red-600" aria-hidden="true" />
              </div>
              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-zinc-900 dark:text-zinc-100">
                  Delete {{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }}
                </DialogTitle>
                <div class="mt-2">
                  <p class="text-sm text-zinc-500">
                    Are you sure you want to delete {{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }}? This action cannot be undone.
                  </p>
                </div>
              </div>
            </div>
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
              <button
                type="button"
                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto"
                @click="deleteSelected"
              >
                Delete
              </button>
              <button
                type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-amber-50 dark:hover:bg-zinc-700 sm:mt-0 sm:w-auto"
                @click="showDeleteModal = false"
              >
                Cancel
              </button>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>

  <!-- Categorize Modal -->
  <TransitionRoot as="template" :show="showCategorizeModal">
    <Dialog as="div" class="relative z-50" @close="showCategorizeModal = false">
      <div class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" />

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <div>
              <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <FolderIcon class="h-6 w-6 text-green-600" aria-hidden="true" />
              </div>
              <div class="mt-3 text-center sm:mt-5">
                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-zinc-900 dark:text-zinc-100">
                  Categorize {{ selectedCount }} {{ selectedCount === 1 ? 'receipt' : 'receipts' }}
                </DialogTitle>
                <div class="mt-2">
                  <p class="text-sm text-zinc-500">
                    Select a category to apply to all selected receipts.
                  </p>
                  <div class="mt-4">
                    <select
                      v-model="selectedCategoryId"
                      class="block w-full rounded-md border-0 py-1.5 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 focus:ring-2 focus:ring-inset focus:ring-amber-600 sm:text-sm sm:leading-6"
                    >
                      <option value="">Select a category</option>
                      <optgroup v-if="categories.length > 0" label="Your Categories">
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                          {{ category.name }}
                        </option>
                      </optgroup>
                      <optgroup label="Default Categories">
                        <option value="food">Food</option>
                        <option value="transport">Transport</option>
                        <option value="shopping">Shopping</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="utilities">Utilities</option>
                        <option value="healthcare">Healthcare</option>
                        <option value="other">Other</option>
                      </optgroup>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
              <button
                type="button"
                :disabled="!selectedCategoryId"
                class="inline-flex w-full justify-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 sm:col-start-2 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="categorizeSelected"
              >
                Apply Category
              </button>
              <button
                type="button"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-amber-50 dark:hover:bg-zinc-700 sm:col-start-1 sm:mt-0"
                @click="showCategorizeModal = false"
              >
                Cancel
              </button>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  Menu,
  MenuButton,
  MenuItem,
  MenuItems,
  TransitionRoot,
} from '@headlessui/vue';
import {
  CheckCircleIcon,
  XMarkIcon,
  TrashIcon,
  FolderIcon,
  ArrowDownTrayIcon,
  ChevronDownIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  selectedCount: {
    type: Number,
    required: true,
  },
  selectedIds: {
    type: Array,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['clear-selection']);

const showDeleteModal = ref(false);
const showCategorizeModal = ref(false);
const selectedCategoryId = ref('');

const deleteSelected = () => {
  router.post(route('bulk.receipts.delete'), {
    receipt_ids: props.selectedIds,
  }, {
    onSuccess: () => {
      showDeleteModal.value = false;
      emit('clear-selection');
    },
  });
};

const categorizeSelected = () => {
  if (!selectedCategoryId.value) return;
  
  const data = {
    receipt_ids: props.selectedIds,
  };
  
  // Check if it's a custom category (numeric) or default category (string)
  if (isNaN(selectedCategoryId.value)) {
    data.category = selectedCategoryId.value;
  } else {
    data.category_id = selectedCategoryId.value;
  }
  
  router.post(route('bulk.receipts.categorize'), data, {
    onSuccess: () => {
      showCategorizeModal.value = false;
      selectedCategoryId.value = '';
      emit('clear-selection');
    },
  });
};

const exportSelected = (format) => {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = route(`bulk.receipts.export.${format}`);
  
  // Add CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrfToken) {
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
  }
  
  // Add receipt IDs
  props.selectedIds.forEach(id => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'receipt_ids[]';
    input.value = id;
    form.appendChild(input);
  });
  
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
};
</script>