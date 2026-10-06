<template>
  <Head :title="__('receipt_details')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap justify-between items-center gap-3">
        <h2 class="min-w-0 break-words font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-200 leading-tight flex items-center gap-x-2">
          <ReceiptRefundIcon class="size-6" />
          {{ receipt.merchant?.name || __('unknown_merchant') }}
        </h2>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" @click="showPreview = !showPreview" :aria-expanded="showPreview"
            class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-600 dark:text-zinc-200">
            {{ showPreview ? 'Hide source preview' : 'Show source preview' }}
          </button>
          <SharingControls
            :file-id="receipt.id"
            file-type="receipt"
            :current-shares="sharingControlShares"
            @shares-updated="handleSharesUpdated"
          />
          <Link
            :href="route('receipts.index')"
            class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700 focus:bg-zinc-700 active:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
          >
            <ArrowLeftIcon class="size-4" />
            {{ __('back_to_overview') }}
          </Link>
        </div>
      </div>
    </template>

    <Breadcrumbs v-if="breadcrumbs.length" :crumbs="breadcrumbs" class="px-6 pt-4" />

    <div class="flex flex-col xl:flex-row items-start">
      <!-- Receipt details -->
      <div class="w-full min-w-0 break-words p-3 sm:p-6" :class="showPreview ? 'xl:w-2/3' : 'xl:w-full'">
        <div class="space-y-8">
          <!-- Receipt Status -->
          <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-x-3">
                <div :class="[getStatusClass(receipt), 'flex-none rounded-full p-1']" aria-hidden="true">
                  <div class="size-2 rounded-full bg-current" />
                </div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">{{ __('receipt_status') }}: {{ getStatusLabel(receipt) }}</h3>
              </div>
              <div class="flex flex-wrap items-center gap-3">
                <button v-if="isEditing" type="button" @click="cancelEditing"
                  class="text-sm font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                  {{ __('cancel') }}
                </button>
                <button
                  @click="isEditing ? saveReceipt() : startEditing()"
                  class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                  :class="isEditing ? 'text-zinc-900 bg-amber-100 hover:bg-amber-200' : 'text-zinc-100 bg-zinc-700 hover:bg-amber-600'"
                >
                  <PencilIcon v-if="!isEditing" class="size-4" />
                  <CheckIcon v-else class="size-4" />
                  {{ isEditing ? __('save_changes') : __('edit_receipt') }}
                </button>
              </div>
            </div>
            <p v-if="isEditing" class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
              Save Changes saves your edits. Cancel or leaving this page discards unsaved edits.
            </p>

            <p v-if="receipt.file?.needs_review" class="mt-4 text-sm text-amber-800 dark:text-amber-200">
              Editing saves corrected values. Complete the review by checking and confirming them in the
              <Link :href="route('files.extraction-report', receipt.file.id)" class="font-semibold underline">extraction report</Link>.
            </p>
            <dl class="mt-6 space-y-6">
              <div v-for="(field, index) in receiptFields" :key="index" class="flex flex-col">
                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ field.label }}</dt>
                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200" :class="{ 'whitespace-pre-wrap break-words': field.type === 'textarea' }">
                  {{ formatFieldValue(field.key === 'category_id' ? receipt.receipt_category : receipt[field.key], field.type) }}
                </dd>
                <div v-else class="mt-1">
                  <DatePicker
                    v-if="field.type === 'date'"
                    v-model="editedReceipt[field.key]"
                    :placeholder="`Select ${field.label.toLowerCase()}...`"
                  />
                  <textarea
                    v-else-if="field.type === 'textarea'"
                    v-model="editedReceipt[field.key]"
                    :aria-label="field.label"
                    rows="4"
                    class="block w-full resize-y rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600 sm:text-sm"
                  />
                  <input
                    v-else-if="field.type === 'text' || field.type === 'number'"
                    v-model="editedReceipt[field.key]"
                    :type="field.type"
                    class="block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600 sm:text-sm"
                  />
                  <select
                    v-else-if="field.type === 'select'"
                    v-model="editedReceipt[field.key]"
                    class="block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600 sm:text-sm"
                  >
                    <option v-for="option in field.options" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </div>
              </div>
            </dl>
            <dl v-if="receipt.reconciliation" class="mt-6 flex flex-col gap-3 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
              <div class="flex justify-between gap-3"><dt>Line item total</dt><dd>{{ formatCurrency(receipt.reconciliation.calculated_total, receipt.currency) }}</dd></div>
              <div v-if="Number(receipt.reconciliation.discount_amount)" class="flex justify-between gap-3"><dt>Source discount</dt><dd>− {{ formatCurrency(receipt.reconciliation.discount_amount, receipt.currency) }}</dd></div>
              <div v-if="Number(receipt.reconciliation.tip_amount)" class="flex justify-between gap-3"><dt>Source tip</dt><dd>{{ formatCurrency(receipt.reconciliation.tip_amount, receipt.currency) }}</dd></div>
              <div class="flex justify-between gap-3 font-semibold"><dt>Final amount</dt><dd>{{ formatCurrency(receipt.total_amount, receipt.currency) }}</dd></div>
              <p class="text-zinc-500 dark:text-zinc-400">Tax may already be included in the line item prices. Source adjustments are included when checking the final amount.</p>
            </dl>
          </div>

          <!-- Tags -->
          <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Tags</h3>
            <TagManager
              v-if="isEditing || receiptTags.length"
              v-model="receiptTags"
              :readonly="!isEditing"
              @tag-added="handleTagAdded"
              @tag-removed="handleTagRemoved"
            />
            <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">No tags added.</p>
          </div>

          <!-- Collections -->
          <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4 flex items-center gap-2">
              <RectangleStackIcon class="size-5" />
              {{ __('collections') || 'Collections' }}
            </h3>
            <CollectionSelector
              v-model="receiptCollections"
              placeholder="Add to collections..."
              :allow-create="true"
              @update:model-value="handleCollectionsChanged"
            />
            <p v-if="!isEditing" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Folder changes save immediately.</p>
            <p v-if="!receiptCollections.length" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
              Not assigned to any collections
            </p>
          </div>

          <!-- Line Items -->
          <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
              <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">{{ __('line_items') }}</h3>
              <button
                @click="showAddLineItem = true"
                class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold text-zinc-900 bg-amber-100 rounded-md hover:bg-amber-200"
              >
                <PlusIcon class="size-4" />
                {{ __('add_line_item') }}
              </button>
            </div>

            <div class="overflow-x-auto" tabindex="0" aria-label="Line items, scroll horizontally to see all columns">
              <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                <thead>
                  <tr>
                    <th v-for="header in lineItemHeaders" :key="header.key" class="px-3 py-3.5 text-left text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                      {{ header.label }}
                    </th>
                    <th class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                      <span class="sr-only">{{ __('actions') }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-amber-200 dark:divide-zinc-700">
                  <tr v-for="item in receipt.lineItems" :key="item.id" class="hover:bg-amber-50 dark:hover:bg-zinc-700/50">
                    <td class="min-w-48 break-words px-3 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ item.text }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ item.sku }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ item.qty }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ formatCurrency(item.price, receipt.currency) }}</td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ formatCurrency(item.total, receipt.currency) }}</td>
                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                      <div class="flex items-center justify-end gap-x-2">
                        <button
                          @click="editLineItem(item)"
                          class="text-amber-400 hover:text-amber-300"
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

      <!-- Source preview -->
      <div v-if="showPreview" class="w-full min-w-0 min-h-64 xl:w-1/3 xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] bg-amber-50 dark:bg-zinc-900 overflow-auto">
        <ReceiptImage
          :file="receipt.file"
          :alt-text="__('receipt_image')"
          :error-message="__('receipt_image_load_error')"
          :no-image-message="__('no_receipt_image')"
          :show-pdf-button="true"
          pdf-button-position="absolute top-4 right-4"
        />
      </div>
    </div>

    <!-- Add/Edit Line Item Modal -->
    <Modal :show="showAddLineItem" @close="closeAddLineItem" v-slot="{ titleId }">
      <div class="p-6">
        <h3 :id="titleId" class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-5">
          {{ editingLineItem ? __('edit_line_item') : __('add_line_item') }}
        </h3>
        
        <form @submit.prevent="saveLineItem" class="space-y-4">
          <div v-for="field in lineItemFields" :key="field.key" class="space-y-1">
            <label :for="field.key" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
              {{ field.label }}
            </label>
            <input
              :id="field.key"
              v-model="lineItemForm[field.key]"
              :type="field.type"
              class="block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600 sm:text-sm"
            />
          </div>

          <div class="mt-6 flex items-center justify-end gap-x-4">
            <button
              type="button"
              class="text-sm font-semibold text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100"
              @click="closeAddLineItem"
            >
              {{ __('cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex justify-center rounded-md bg-zinc-900 dark:bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 dark:hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
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
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Common/Breadcrumbs.vue';
import Modal from '@/Components/Common/Modal.vue';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import ReceiptImage from '@/Components/Domain/ReceiptImage.vue';
import DatePicker from '@/Components/Forms/DatePicker.vue';
import CollectionSelector from '@/Components/Domain/CollectionSelector.vue';
import {
  ArrowLeftIcon,
  PencilIcon,
  CheckIcon,
  PlusIcon,
  ReceiptRefundIcon,
  RectangleStackIcon
} from '@heroicons/vue/24/outline';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({
  receipt: {
    type: Object,
    required: true
  },
  categories: {
    type: Array,
    default: () => []
  },
  breadcrumbs: {
    type: Array,
    default: () => []
  }
});

const page = usePage();
const __ = (key) => page.props.language?.messages?.[key] || key;
const { formatDate, formatCurrency } = useDateFormatter();

const showPreview = ref(true);
const isEditing = ref(false);
const showAddLineItem = ref(false);
const editingLineItem = ref(null);
const editedReceipt = ref({ ...props.receipt });
const receiptTags = ref(props.receipt.tags || []);
const receiptCollections = ref(props.receipt.collections?.map(c => c.id) || []);

// Format date for HTML date input (YYYY-MM-DD format)
const formatDateForInput = (date) => {
  if (!date) return '';
  const d = new Date(date);
  if (isNaN(d.getTime())) return '';
  return d.toISOString().split('T')[0];
};

const initializeEditedReceipt = () => {
  editedReceipt.value = {
    ...props.receipt,
    receipt_date: formatDateForInput(props.receipt.receipt_date)
  };
  receiptTags.value = [...(props.receipt.tags || [])];
  receiptCollections.value = props.receipt.collections?.map(collection => collection.id) || [];
};

const startEditing = () => {
  initializeEditedReceipt();
  isEditing.value = true;
};

const cancelEditing = () => {
  initializeEditedReceipt();
  isEditing.value = false;
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

const handleCollectionsChanged = (collectionIds) => {
  receiptCollections.value = collectionIds;

  // If not in edit mode, save immediately
  if (!isEditing.value && props.receipt.file_id) {
    // Find collections to add and remove
    const currentIds = props.receipt.collections?.map(c => c.id) || [];
    const toAdd = collectionIds.filter(id => !currentIds.includes(id));
    const toRemove = currentIds.filter(id => !collectionIds.includes(id));

    // Add to new collections
    toAdd.forEach(collectionId => {
      router.post(route('collections.files.add', collectionId), {
        file_ids: [props.receipt.file_id]
      }, {
        preserveScroll: true
      });
    });

    // Remove from old collections
    toRemove.forEach(collectionId => {
      router.delete(route('collections.files.remove', collectionId), {
        data: { file_ids: [props.receipt.file_id] },
        preserveScroll: true
      });
    });
  }
};

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
  { key: 'category_id', label: __('category'), type: 'select', options: [
    { value: null, label: __('uncategorized') },
    ...props.categories.map(category => ({ value: category.id, label: category.name }))
  ]},
  { key: 'receipt_description', label: __('description'), type: 'textarea' },
  { key: 'note', label: __('note'), type: 'textarea' }
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
  if (!receipt?.merchant_id) return 'text-zinc-500 bg-amber-100/10'
  if (receipt?.total_amount === null) return 'text-rose-400 bg-rose-400/10'
  return 'text-green-400 bg-green-400/10'
}

const getStatusLabel = (receipt) => {
  if (!receipt?.merchant_id) return 'Pending'
  if (receipt?.total_amount === null) return 'Incomplete'
  return 'Complete'
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

const saveReceipt = () => {
  const originalReceipt = {
    ...props.receipt,
    receipt_date: formatDateForInput(props.receipt.receipt_date)
  };

  const collectionsChanged = JSON.stringify(props.receipt.collections?.map(collection => collection.id) || []) !==
    JSON.stringify(receiptCollections.value);

  if (collectionsChanged || JSON.stringify(originalReceipt) !== JSON.stringify(editedReceipt.value) ||
      JSON.stringify(props.receipt.tags || []) !== JSON.stringify(receiptTags.value)) {
    router.patch(route('receipts.update', props.receipt.id), {
      ...Object.fromEntries(receiptFields.value.map(field => [field.key, editedReceipt.value[field.key]])),
      tags: receiptTags.value.map(tag => tag.id),
      ...(collectionsChanged ? { collection_ids: receiptCollections.value } : {})
    }, {
      onSuccess: () => { isEditing.value = false; }
    });
  } else {
    isEditing.value = false;
  }
};
</script>
