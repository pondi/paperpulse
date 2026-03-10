<template>
  <div
    class="group relative bg-white dark:bg-zinc-800 rounded-lg border border-amber-200 dark:border-zinc-700 hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-lg transition-all duration-200 overflow-hidden"
    :class="{ 'ring-2 ring-amber-500 dark:ring-amber-400': selected }"
  >
    <!-- Type indicator stripe -->
    <div
      class="absolute top-0 left-0 w-1 h-full"
      :class="typeStripeColor"
    />

    <div class="flex gap-4 p-4 pl-5">
      <!-- Selection checkbox -->
      <div class="flex-shrink-0 flex items-center">
        <input
          type="checkbox"
          :checked="selected"
          @change.stop="emit('toggle-select')"
          class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600 rounded"
        />
      </div>
      <!-- Thumbnail (if available) -->
      <div
        v-if="result.file?.url"
        class="flex-shrink-0 w-16 h-20 bg-amber-100 dark:bg-zinc-700 rounded overflow-hidden border border-amber-200 dark:border-zinc-600"
      >
        <img
          :src="result.file.previewUrl || result.file.url"
          :alt="result.title"
          class="w-full h-full object-cover"
          @error="handleImageError"
        />
      </div>
      <div
        v-else
        class="flex-shrink-0 w-16 h-20 bg-amber-100 dark:bg-zinc-700 rounded flex items-center justify-center border border-amber-200 dark:border-zinc-600"
      >
        <ReceiptRefundIcon v-if="result.type === 'receipt'" class="size-8 text-zinc-400" />
        <BanknotesIcon v-else-if="result.type === 'invoice'" class="size-8 text-zinc-400" />
        <DocumentDuplicateIcon v-else-if="result.type === 'contract'" class="size-8 text-zinc-400" />
        <TicketIcon v-else-if="result.type === 'voucher'" class="size-8 text-zinc-400" />
        <ShieldCheckIcon v-else-if="result.type === 'warranty'" class="size-8 text-zinc-400" />
        <ArrowUturnLeftIcon v-else-if="result.type === 'return_policy'" class="size-8 text-zinc-400" />
        <BuildingLibraryIcon v-else-if="result.type === 'bank_statement'" class="size-8 text-zinc-400" />
        <DocumentIcon v-else class="size-8 text-zinc-400" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 cursor-pointer" @click="handleClick">
        <!-- Header -->
        <div class="flex items-start justify-between gap-2 mb-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                :class="typeBadgeClass"
              >
                <component :is="typeIcon" class="size-3 mr-1" />
                {{ typeLabel }}
              </span>
              <span v-if="result.date" class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ formatDate(result.date) }}
              </span>
            </div>
            <h3
              class="text-base font-semibold text-zinc-900 dark:text-white truncate group-hover:text-amber-600 dark:group-hover:text-amber-400"
              v-html="highlightText(result.title)"
            />
          </div>

          <!-- Total amount -->
          <div v-if="result.total" class="flex-shrink-0">
            <div class="text-right">
              <div class="text-lg font-bold text-zinc-900 dark:text-white">
                {{ result.total }}
              </div>
              <div v-if="result.payment_status" class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ result.payment_status }}
              </div>
            </div>
          </div>
        </div>

        <!-- Description -->
        <p
          v-if="result.description"
          class="text-sm text-zinc-600 dark:text-zinc-300 line-clamp-2 mb-2"
          v-html="highlightText(result.description)"
        />

        <!-- Receipt specific: Line items preview -->
        <div v-if="result.type === 'receipt' && result.items && result.items.length > 0" class="mb-2">
          <div class="flex flex-wrap gap-1">
            <span
              v-for="(item, idx) in result.items.slice(0, 3)"
              :key="idx"
              class="inline-flex items-center text-xs text-zinc-600 dark:text-zinc-400 bg-amber-100 dark:bg-zinc-700 rounded px-2 py-0.5"
            >
              {{ item.description }}
            </span>
            <span
              v-if="result.items.length > 3"
              class="inline-flex items-center text-xs text-zinc-500 dark:text-zinc-500"
            >
              +{{ result.items.length - 3 }} more
            </span>
          </div>
        </div>

        <!-- Meta information -->
        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
          <div v-if="result.category" class="flex items-center gap-1">
            <FolderIcon class="size-3" />
            <span>{{ result.category }}</span>
          </div>
          <div v-if="result.document_type" class="flex items-center gap-1">
            <DocumentTextIcon class="size-3" />
            <span>{{ result.document_type }}</span>
          </div>
          <div v-if="result.invoice_number" class="flex items-center gap-1">
            <DocumentTextIcon class="size-3" />
            <span>#{{ result.invoice_number }}</span>
          </div>
          <div v-if="result.contract_type" class="flex items-center gap-1">
            <DocumentTextIcon class="size-3" />
            <span>{{ result.contract_type }}</span>
          </div>
          <div v-if="result.status" class="flex items-center gap-1">
            <SparklesIcon class="size-3" />
            <span>{{ result.status }}</span>
          </div>
          <div v-if="result.voucher_type" class="flex items-center gap-1">
            <TicketIcon class="size-3" />
            <span>{{ result.voucher_type }}</span>
          </div>
          <div v-if="result.manufacturer" class="flex items-center gap-1">
            <BuildingLibraryIcon class="size-3" />
            <span>{{ result.manufacturer }}</span>
          </div>
          <div v-if="result.transaction_count" class="flex items-center gap-1">
            <DocumentTextIcon class="size-3" />
            <span>{{ result.transaction_count }} transactions</span>
          </div>
          <div v-if="result.tags && result.tags.length > 0" class="flex items-center gap-1">
            <TagIcon class="size-3" />
            <span>{{ result.tags.slice(0, 2).join(', ') }}</span>
            <span v-if="result.tags.length > 2">+{{ result.tags.length - 2 }}</span>
          </div>
          <div v-if="result._rankingScore !== undefined" class="ml-auto flex items-center gap-1">
            <SparklesIcon class="size-3" />
            <span>{{ Math.round(result._rankingScore * 100) }}% match</span>
          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="flex-shrink-0 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          @click.stop="handlePreview"
          class="p-2 text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-100 dark:hover:bg-zinc-700 rounded"
          title="Quick preview"
        >
          <EyeIcon class="size-5" />
        </button>
        <a
          :href="result.url"
          @click.stop
          class="p-2 text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-100 dark:hover:bg-zinc-700 rounded"
          title="Open in new tab"
          target="_blank"
        >
          <ArrowTopRightOnSquareIcon class="size-5" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import {
  DocumentIcon,
  ReceiptRefundIcon,
  FolderIcon,
  TagIcon,
  EyeIcon,
  ArrowTopRightOnSquareIcon,
  DocumentTextIcon,
  SparklesIcon,
  BanknotesIcon,
  DocumentDuplicateIcon,
  TicketIcon,
  ShieldCheckIcon,
  ArrowUturnLeftIcon,
  BuildingLibraryIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  result: {
    type: Object,
    required: true
  },
  searchQuery: {
    type: String,
    default: ''
  },
  selected: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['preview', 'click', 'toggle-select']);

const typeConfig = {
  receipt:        { label: 'Receipt',        badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',     stripe: 'bg-amber-500',  icon: ReceiptRefundIcon },
  document:       { label: 'Document',       badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',  stripe: 'bg-purple-500', icon: DocumentIcon },
  invoice:        { label: 'Invoice',        badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',          stripe: 'bg-blue-500',   icon: BanknotesIcon },
  contract:       { label: 'Contract',       badge: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',  stripe: 'bg-violet-500', icon: DocumentDuplicateIcon },
  voucher:        { label: 'Voucher',        badge: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',      stripe: 'bg-green-500',  icon: TicketIcon },
  warranty:       { label: 'Warranty',       badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',  stripe: 'bg-orange-500', icon: ShieldCheckIcon },
  return_policy:  { label: 'Return Policy',  badge: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',          stripe: 'bg-pink-500',   icon: ArrowUturnLeftIcon },
  bank_statement: { label: 'Bank Statement', badge: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',          stripe: 'bg-teal-500',   icon: BuildingLibraryIcon },
};

const currentTypeConfig = computed(() => typeConfig[props.result.type] || typeConfig.document);
const typeStripeColor = computed(() => currentTypeConfig.value.stripe);
const typeBadgeClass = computed(() => currentTypeConfig.value.badge);
const typeLabel = computed(() => currentTypeConfig.value.label);
const typeIcon = computed(() => currentTypeConfig.value.icon);

const handleImageError = (e) => {
  e.target.style.display = 'none';
};

const handleClick = () => {
  // Show preview instead of navigate
  emit('preview', props.result);
};

const handlePreview = () => {
  emit('preview', props.result);
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const escapeHtml = (str) => {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return str.replace(/[&<>"']/g, (c) => map[c]);
};

const highlightText = (text) => {
  if (!text || !props.searchQuery) return escapeHtml(text || '');

  const query = props.searchQuery.trim();
  if (!query) return escapeHtml(text);

  // Escape HTML entities first to neutralize any malicious markup
  const safeText = escapeHtml(text);

  // Escape special regex characters in the query, then escape query for HTML matching
  const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const safeQuery = escapeHtml(escapedQuery);
  const regex = new RegExp(`(${safeQuery})`, 'gi');

  return safeText.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 text-zinc-900 dark:text-white px-0.5 rounded">$1</mark>');
};
</script>
