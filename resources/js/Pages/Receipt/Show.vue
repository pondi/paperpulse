<template>
  <Head :title="__('receipt_details')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-x-2">
          <ReceiptRefundIcon class="size-6" />
          {{ receipt.merchant?.name || __('unknown_merchant') }}
        </h2>
        <div class="flex items-center gap-x-4">
          <SharingControls
            :file-id="receipt.id"
            file-type="receipt"
            :current-shares="sharingControlShares"
            @shares-updated="handleSharesUpdated"
          />
          <Link
            :href="route('receipts.index')"
            class="inline-flex items-center gap-x-2 px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
          >
            <ArrowLeftIcon class="size-4" />
            {{ __('back_to_overview') }}
          </Link>
        </div>
      </div>
    </template>

    <div class="flex h-[calc(100vh-9rem)] overflow-hidden">
      <!-- Left Panel - Receipt Details -->
      <div class="w-1/2 p-6 overflow-y-auto border-r border-gray-200 dark:border-gray-700">
        <div class="space-y-8">
          <!-- Receipt Status -->
          <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-x-3">
                <div :class="[getStatusClass(receipt), 'flex-none rounded-full p-1']">
                  <div class="size-2 rounded-full bg-current" />
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">{{ __('receipt_status') }}</h3>
              </div>
              <button
                @click="isEditing = !isEditing"
                class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                :class="isEditing ? 'text-gray-900 bg-gray-100 hover:bg-gray-200' : 'text-gray-100 bg-gray-700 hover:bg-gray-600'"
              >
                <PencilIcon v-if="!isEditing" class="size-4" />
                <CheckIcon v-else class="size-4" />
                {{ isEditing ? __('save_changes') : __('edit_receipt') }}
              </button>
            </div>
            
            <dl class="mt-6 space-y-6">
              <div v-for="(field, index) in receiptFields" :key="index" class="flex flex-col">
                <dt class="text-sm font-medium text-gray-500">{{ field.label }}</dt>
                <dd v-if="!isEditing" class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                  {{ formatFieldValue(receipt[field.key], field.type) }}
                </dd>
                <div v-else class="mt-1">
                  <DatePicker
                    v-if="field.type === 'date'"
                    v-model="editedReceipt[field.key]"
                    :placeholder="`Select ${field.label.toLowerCase()}...`"
                  />
                  <input
                    v-else-if="field.type === 'text' || field.type === 'number'"
                    v-model="editedReceipt[field.key]"
                    :type="field.type"
                    class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                  />
                  <select
                    v-else-if="field.type === 'select'"
                    v-model="editedReceipt[field.key]"
                    class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                  >
                    <option v-for="option in field.options" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
              </div>
            </dl>
          </div>

          <!-- Tags -->
          <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-4">Tags</h3>
            <TagManager
              v-model="receiptTags"
              :readonly="!isEditing"
              @tag-added="handleTagAdded"
              @tag-removed="handleTagRemoved"
            />
          </div>

          <!-- Line Items -->
          <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">{{ __('line_items') }}</h3>
              <button
                @click="showAddLineItem = true"
                class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold text-gray-900 bg-gray-100 rounded-md hover:bg-gray-200"
              >
                <PlusIcon class="size-4" />
                {{ __('add_line_item') }}
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                  <tr>
                    <th v-for="header in lineItemHeaders" :key="header.key" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                      {{ header.label }}
                    </th>
                    <th class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                      <span class="sr-only">{{ __('actions') }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="item in receipt.lineItems" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.text }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.sku }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 dark:text-gray-300">{{ item.qty }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(item.price, receipt.currency) }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(item.total, receipt.currency) }}</td>
                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                      <div class="flex items-center justify-end gap-x-2">
                        <button
                          @click="editLineItem(item)"
                          class="text-indigo-400 hover:text-indigo-300"
                        >
                          {{ __('edit') }}
                        </button>
                        <button
                          @click="deleteLineItem(item.id)"
                          class="text-red-400 hover:text-red-300"
                        >
                          {{ __('delete') }}
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel - Receipt Image -->
      <div class="w-1/2 bg-gray-50 dark:bg-gray-900 overflow-auto">
        <ReceiptImage
          :file="receipt.file"
          :alt-text="__('receipt_image')"
          :error-message="__('receipt_image_load_error')"
          :no-image-message="__('no_receipt_image')"
          :show-pdf-button="true"
          pdf-button-position="fixed bottom-6 right-6"
        />
      </div>
    </div>

    <!-- Add/Edit Line Item Modal -->
    <Modal :show="showAddLineItem" @close="closeAddLineItem">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-5">
          {{ editingLineItem ? __('edit_line_item') : __('add_line_item') }}
        </h3>
        
        <form @submit.prevent="saveLineItem" class="space-y-4">
          <div v-for="field in lineItemFields" :key="field.key" class="space-y-1">
            <label :for="field.key" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ field.label }}
            </label>
            <input
              :id="field.key"
              v-model="lineItemForm[field.key]"
              :type="field.type"
              class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
            />
          </div>

          <div class="mt-6 flex items-center justify-end gap-x-4">
            <button
              type="button"
              class="text-sm font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100"
              @click="closeAddLineItem"
            >
              {{ __('cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            >
              {{ editingLineItem ? __('save') : __('add') }}
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Common/Modal.vue';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import ReceiptImage from '@/Components/Domain/ReceiptImage.vue';
import DatePicker from '@/Components/Forms/DatePicker.vue';
import {
  ArrowLeftIcon,
  PencilIcon,
  CheckIcon,
  PlusIcon,
  ReceiptRefundIcon
} from '@heroicons/vue/24/outline';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
  receipt: {
    type: Object,
    required: true
  }
});

const page = usePage();
const __ = (key) => page.props.language?.messages?.[key] || key;
const { formatDate, formatCurrency } = useDateFormatter();

const isEditing = ref(false);
const showAddLineItem = ref(false);
const editingLineItem = ref(null);
const editedReceipt = ref({ ...props.receipt });
const receiptTags = ref(props.receipt.tags || []);

// Format date for HTML date input (YYYY-MM-DD format)
const formatDateForInput = (date) => {
  if (!date) return '';
  const d = new Date(date);
  if (isNaN(d.getTime())) return '';
  return d.toISOString().split('T')[0];
};

// Initialize editedReceipt with properly formatted date
const initializeEditedReceipt = () => {
  editedReceipt.value = {
    ...props.receipt,
    receipt_date: formatDateForInput(props.receipt.receipt_date)
  };
};
const lineItemForm = ref({
  text: '',
  sku: '',
  qty: 1,
  price: 0,
  total: 0
});

// Normalize inbound shares from API to simplified user list for this page
const handleSharesUpdated = (shares) => {
  props.receipt.shared_users = (shares || []).map((s) => ({
    id: s.shared_with_user?.id ?? s.id,
    name: s.shared_with_user?.name ?? s.name,
    email: s.shared_with_user?.email ?? s.email,
    permission: s.permission,
    shared_at: s.shared_at,
  }));
};

// Adapt simplified list to SharingControls expected shape
const sharingControlShares = computed(() => {
  const users = (props.receipt.shared_users || []);
  return users.map((u) => ({
    shared_with_user: { id: u.id, name: u.name, email: u.email },
    permission: u.permission,
    shared_at: u.shared_at,
  }));
});

const handleTagAdded = (tag) => {
  // When in edit mode, just update the local tags
  if (isEditing.value) {
    if (!receiptTags.value.find(t => t.id === tag.id)) {
      receiptTags.value.push(tag);
    }
  } else {
    // When not editing, immediately save to server
    router.post(route('receipts.tags.store', props.receipt.id), {
      name: tag.name
    }, {
      preserveScroll: true,
      onSuccess: () => {
        receiptTags.value = [...receiptTags.value, tag];
      }
    });
  }
};

const handleTagRemoved = (tag) => {
  // When in edit mode, just update the local tags
  if (isEditing.value) {
    receiptTags.value = receiptTags.value.filter(t => t.id !== tag.id);
  } else {
    // When not editing, immediately remove from server
    router.delete(route('receipts.tags.destroy', [props.receipt.id, tag.id]), {
      preserveScroll: true,
      onSuccess: () => {
        receiptTags.value = receiptTags.value.filter(t => t.id !== tag.id);
      }
    });
  }
};

// formatDate and formatCurrency are now imported from useDateFormatter

const formatFieldValue = (value, type) => {
  if (value === null || value === undefined) return '-'
  switch (type) {
    case 'date': return formatDate(value)
    case 'number': return formatCurrency(value, props.receipt.currency)
    default: return value
  }
}

const receiptFields = computed(() => [
  { key: 'receipt_date', label: __('date'), type: 'date' },
  { key: 'total_amount', label: __('total_amount'), type: 'number' },
  { key: 'tax_amount', label: __('tax_amount'), type: 'number' },
  { key: 'currency', label: __('currency'), type: 'text' },
  { key: 'receipt_category', label: __('category'), type: 'select', options: [
    { value: 'mat', label: __('food') },
    { value: 'transport', label: __('transport') },
    { value: null, label: __('uncategorized') }
  ]},
  { key: 'receipt_description', label: __('description'), type: 'text' }
]);

const lineItemFields = computed(() => [
  { key: 'text', label: __('description'), type: 'text' },
  { key: 'sku', label: __('sku'), type: 'text' },
  { key: 'qty', label: __('quantity'), type: 'number' },
  { key: 'price', label: __('unit_price'), type: 'number' },
  { key: 'total', label: __('total'), type: 'number' }
]);

const lineItemHeaders = computed(() => [
  { key: 'text', label: __('description') },
  { key: 'sku', label: __('sku') },
  { key: 'qty', label: __('quantity') },
  { key: 'price', label: __('unit_price') },
  { key: 'total', label: __('total') }
]);

const getStatusClass = (receipt) => {
  if (!receipt?.merchant_id) return 'text-gray-500 bg-gray-100/10'
  if (receipt?.total_amount === null) return 'text-rose-400 bg-rose-400/10'
  return 'text-green-400 bg-green-400/10'
}

const editLineItem = (item) => {
  editingLineItem.value = item;
  lineItemForm.value = { ...item };
  showAddLineItem.value = true;
};

const closeAddLineItem = () => {
  showAddLineItem.value = false;
  editingLineItem.value = null;
  lineItemForm.value = {
    text: '',
    sku: '',
    qty: 1,
    price: 0,
    total: 0
  };
};

const saveLineItem = () => {
  if (editingLineItem.value) {
    router.patch(route('receipts.line-items.update', [props.receipt.id, editingLineItem.value.id]), lineItemForm.value);
  } else {
    router.post(route('receipts.line-items.store', props.receipt.id), lineItemForm.value);
  }
  closeAddLineItem();
};

const deleteLineItem = (id) => {
  if (confirm(__('confirm_delete_line_item'))) {
    router.delete(route('receipts.line-items.destroy', [props.receipt.id, id]));
  }
};

watch(isEditing, (newValue) => {
  if (newValue) {
    // When entering edit mode, reinitialize the edited receipt with proper date formatting
    initializeEditedReceipt();
  } else {
    // When exiting edit mode, save if there are changes
    const originalReceipt = {
      ...props.receipt,
      receipt_date: formatDateForInput(props.receipt.receipt_date)
    };
    
    if (JSON.stringify(originalReceipt) !== JSON.stringify(editedReceipt.value) || 
        JSON.stringify(props.receipt.tags || []) !== JSON.stringify(receiptTags.value)) {
      // Include tags as array of IDs
      const dataToSave = {
        ...editedReceipt.value,
        tags: receiptTags.value.map(t => t.id)
      };
      router.patch(route('receipts.update', props.receipt.id), dataToSave);
    }
  }
});
</script> 
