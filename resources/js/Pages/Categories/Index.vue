<template>
  <Head :title="__('categories')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">{{ __('categories') }}</h2>
        <button
          @click="showCreateModal = true"
          type="button"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-bold rounded-md shadow-sm hover:shadow text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200"
        >
          <PlusIcon class="-ml-1 mr-2 h-5 w-5" aria-hidden="true" />
          {{ __('add_category') }}
        </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-amber-600 dark:border-amber-500">
          <div class="p-6">
            <!-- Flash Messages -->
            <div v-if="$page.props.flash.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4 border border-green-200 dark:border-green-800">
              <div class="flex">
                <div class="flex-shrink-0">
                  <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-green-800 dark:text-green-300">{{ $page.props.flash.success }}</p>
                </div>
              </div>
            </div>

            <div v-if="$page.props.flash.error" class="mb-6 rounded-md bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
              <div class="flex">
                <div class="flex-shrink-0">
                  <XCircleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ $page.props.flash.error }}</p>
                </div>
              </div>
            </div>

            <!-- Categories List -->
            <div v-if="categories.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="category in categories"
                :key="category.id"
                class="bg-white dark:bg-zinc-900 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-200 border-l-4 p-6"
                :style="{ borderLeftColor: category.color }"
              >
                <div class="flex items-start justify-between mb-4">
                  <div class="flex items-center space-x-3">
                    <div
                      class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                      :style="{ backgroundColor: category.color }"
                    >
                      <component
                        v-if="getIcon(category.icon)"
                        :is="getIcon(category.icon)"
                        class="h-5 w-5 text-white"
                      />
                      <FolderIcon v-else class="h-5 w-5 text-white" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 truncate">
                        {{ category.name }}
                        <span v-if="!category.is_active" class="ml-2 text-xs font-normal text-zinc-500 dark:text-zinc-400">({{ __('inactive') }})</span>
                      </h3>
                    </div>
                  </div>
                  <div class="flex items-center space-x-2 flex-shrink-0">
                    <button
                      @click="editCategory(category)"
                      type="button"
                      class="text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400 transition-colors duration-200"
                    >
                      <PencilIcon class="h-5 w-5" />
                    </button>
                    <button
                      v-if="category.receipt_count === 0"
                      @click="deleteCategory(category)"
                      type="button"
                      class="text-red-600 hover:text-red-700 dark:text-red-500 dark:hover:text-red-400 transition-colors duration-200"
                    >
                      <TrashIcon class="h-5 w-5" />
                    </button>
                  </div>
                </div>
                <p v-if="category.description" class="text-sm text-zinc-600 dark:text-zinc-400 mb-4 line-clamp-2">
                  {{ category.description }}
                </p>
                <div class="border-t border-amber-200 dark:border-zinc-700 pt-4 space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('receipts') }}</span>
                    <span class="text-sm font-black text-zinc-900 dark:text-zinc-100">{{ category.receipt_count }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('total') }}</span>
                    <span class="text-sm font-black text-zinc-900 dark:text-zinc-100">{{ formatCurrency(category.total_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
              <FolderIcon class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" />
              <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">{{ __('no_categories') }}</h3>
              <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('no_categories_description') }}</p>
              <div class="mt-8 flex justify-center gap-4">
                <button
                  @click="showCreateModal = true"
                  type="button"
                  class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-bold rounded-md shadow-sm hover:shadow text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700 transition-all duration-200"
                >
                  <PlusIcon class="-ml-1 mr-2 h-5 w-5" aria-hidden="true" />
                  {{ __('add_category') }}
                </button>
                <button
                  @click="createDefaults"
                  type="button"
                  class="inline-flex items-center px-6 py-3 border-2 border-zinc-900 dark:border-zinc-600 text-sm font-bold rounded-md text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700 transition-all duration-200"
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
        <div class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" />

        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
              <form @submit.prevent="saveCategory">
                <div>
                  <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                    <FolderIcon class="h-6 w-6 text-amber-600" aria-hidden="true" />
                  </div>
                  <div class="mt-3 text-center sm:mt-5">
                    <DialogTitle as="h3" class="text-lg font-black leading-6 text-zinc-900 dark:text-zinc-100">
                      {{ form.id ? __('edit_category') : __('add_category') }}
                    </DialogTitle>
                    <div class="mt-6 space-y-4">
                      <div>
                        <label for="name" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                          {{ __('name') }}
                        </label>
                        <input
                          v-model="form.name"
                          type="text"
                          id="name"
                          required
                          class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                        />
                      </div>

                      <div>
                        <label for="color" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                          {{ __('color') }}
                        </label>
                        <div class="mt-1 flex items-center space-x-3">
                          <input
                            v-model="form.color"
                            type="color"
                            id="color"
                            class="h-10 w-20 rounded-md border-zinc-300 dark:border-zinc-700 cursor-pointer"
                          />
                          <input
                            v-model="form.color"
                            type="text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                            placeholder="#6B7280"
                            class="block flex-1 rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                          />
                        </div>
                      </div>

                      <div>
                        <label for="description" class="block text-sm font-bold text-zinc-700 dark:text-zinc-300">
                          {{ __('description') }}
                        </label>
                        <textarea
                          v-model="form.description"
                          id="description"
                          rows="3"
                          class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                        ></textarea>
                      </div>

                      <div v-if="form.id" class="flex items-center">
                        <input
                          v-model="form.is_active"
                          type="checkbox"
                          id="is_active"
                          class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-zinc-900 dark:text-zinc-100">
                          {{ __('active') }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                  <button
                    type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-zinc-900 dark:bg-amber-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-all duration-200 sm:col-start-2"
                  >
                    {{ form.id ? __('save') : __('create') }}
                  </button>
                  <button
                    type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-bold text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-amber-50 dark:hover:bg-zinc-700 transition-all duration-200 sm:col-start-1 sm:mt-0"
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