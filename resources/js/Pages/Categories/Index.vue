<template>
  <Head :title="__('categories')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('categories') }}</h2>
        <button
          @click="showCreateModal = true"
          type="button"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          <PlusIcon class="-ml-1 mr-2 h-5 w-5" aria-hidden="true" />
          {{ __('add_category') }}
        </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- Flash Messages -->
            <div v-if="$page.props.flash.success" class="mb-6 rounded-md bg-green-50 p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-green-800">{{ $page.props.flash.success }}</p>
                </div>
              </div>
            </div>

            <div v-if="$page.props.flash.error" class="mb-6 rounded-md bg-red-50 p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <XCircleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-red-800">{{ $page.props.flash.error }}</p>
                </div>
              </div>
            </div>

            <!-- Categories List -->
            <div v-if="categories.length > 0" class="space-y-4">
              <div
                v-for="category in categories"
                :key="category.id"
                class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 flex items-center justify-between"
              >
                <div class="flex items-center space-x-4">
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    :style="{ backgroundColor: category.color }"
                  >
                    <component
                      v-if="getIcon(category.icon)"
                      :is="getIcon(category.icon)"
                      class="h-5 w-5 text-white"
                    />
                    <FolderIcon v-else class="h-5 w-5 text-white" />
                  </div>
                  <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ category.name }}
                      <span v-if="!category.is_active" class="ml-2 text-xs text-gray-500">({{ __('inactive') }})</span>
                    </h3>
                    <p v-if="category.description" class="text-sm text-gray-500 dark:text-gray-400">
                      {{ category.description }}
                    </p>
                    <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                      <span>{{ category.receipt_count }} {{ __('receipts') }}</span>
                      <span>{{ formatCurrency(category.total_amount) }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <button
                    @click="editCategory(category)"
                    type="button"
                    class="text-indigo-600 hover:text-indigo-900"
                  >
                    <PencilIcon class="h-5 w-5" />
                  </button>
                  <button
                    v-if="category.receipt_count === 0"
                    @click="deleteCategory(category)"
                    type="button"
                    class="text-red-600 hover:text-red-900"
                  >
                    <TrashIcon class="h-5 w-5" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center">
              <FolderIcon class="mx-auto h-12 w-12 text-gray-400" />
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('no_categories') }}</h3>
              <p class="mt-1 text-sm text-gray-500">{{ __('no_categories_description') }}</p>
              <div class="mt-6 flex justify-center space-x-4">
                <button
                  @click="showCreateModal = true"
                  type="button"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
                >
                  <PlusIcon class="-ml-1 mr-2 h-5 w-5" aria-hidden="true" />
                  {{ __('add_category') }}
                </button>
                <button
                  @click="createDefaults"
                  type="button"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  {{ __('create_default_categories') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <TransitionRoot as="template" :show="showModal">
      <Dialog as="div" class="relative z-50" @close="closeModal">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />

        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
              <form @submit.prevent="saveCategory">
                <div>
                  <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                    <FolderIcon class="h-6 w-6 text-indigo-600" aria-hidden="true" />
                  </div>
                  <div class="mt-3 text-center sm:mt-5">
                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">
                      {{ form.id ? __('edit_category') : __('add_category') }}
                    </DialogTitle>
                    <div class="mt-6 space-y-4">
                      <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                          {{ __('name') }}
                        </label>
                        <input
                          v-model="form.name"
                          type="text"
                          id="name"
                          required
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                      </div>

                      <div>
                        <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                          {{ __('color') }}
                        </label>
                        <div class="mt-1 flex items-center space-x-3">
                          <input
                            v-model="form.color"
                            type="color"
                            id="color"
                            class="h-10 w-20 rounded-md border-gray-300 cursor-pointer"
                          />
                          <input
                            v-model="form.color"
                            type="text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                            placeholder="#6B7280"
                            class="block flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                          />
                        </div>
                      </div>

                      <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                          {{ __('description') }}
                        </label>
                        <textarea
                          v-model="form.description"
                          id="description"
                          rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        ></textarea>
                      </div>

                      <div v-if="form.id" class="flex items-center">
                        <input
                          v-model="form.is_active"
                          type="checkbox"
                          id="is_active"
                          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                          {{ __('active') }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                  <button
                    type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                  >
                    {{ form.id ? __('save') : __('create') }}
                  </button>
                  <button
                    type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 sm:col-start-1 sm:mt-0"
                    @click="closeModal"
                  >
                    {{ __('cancel') }}
                  </button>
                </div>
              </form>
            </DialogPanel>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
} from '@headlessui/vue';
import {
  FolderIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  CheckCircleIcon,
  XCircleIcon,
  HomeIcon,
  ShoppingBagIcon,
  TruckIcon,
  FilmIcon,
  BoltIcon,
  HeartIcon,
} from '@heroicons/vue/24/outline';

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
  categories: {
    type: Array,
    required: true,
  },
});

const showModal = ref(false);
const showCreateModal = ref(false);

const form = ref({
  id: null,
  name: '',
  color: '#6B7280',
  description: '',
  is_active: true,
});

const iconMap = {
  'home': HomeIcon,
  'shopping-bag': ShoppingBagIcon,
  'car': TruckIcon,
  'film': FilmIcon,
  'bolt': BoltIcon,
  'heart': HeartIcon,
};

const getIcon = (iconName) => {
  return iconMap[iconName] || null;
};

const { formatCurrency } = useDateFormatter();

const closeModal = () => {
  showModal.value = false;
  showCreateModal.value = false;
  resetForm();
};

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    color: '#6B7280',
    description: '',
    is_active: true,
  };
};

const editCategory = (category) => {
  form.value = {
    id: category.id,
    name: category.name,
    color: category.color,
    description: category.description || '',
    is_active: category.is_active,
  };
  showModal.value = true;
};

const saveCategory = () => {
  if (form.value.id) {
    router.patch(route('categories.update', form.value.id), form.value, {
      onSuccess: () => closeModal(),
    });
  } else {
    router.post(route('categories.store'), form.value, {
      onSuccess: () => closeModal(),
    });
  }
};

const deleteCategory = (category) => {
  if (confirm(__('delete_category_confirm'))) {
    router.delete(route('categories.destroy', category.id));
  }
};

const createDefaults = () => {
  router.post(route('categories.defaults'));
};

// Handle create modal
watch(showCreateModal, (value) => {
  if (value) {
    resetForm();
    showModal.value = true;
    showCreateModal.value = false;
  }
});
</script>