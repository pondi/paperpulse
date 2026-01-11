<template>
  <div
    class="group relative bg-white dark:bg-zinc-800 rounded-lg border border-amber-200 dark:border-zinc-700 hover:border-amber-300 dark:hover:border-amber-600 hover:shadow-lg transition-all duration-200 overflow-hidden"
    :class="{ 'ring-2 ring-amber-500 dark:ring-amber-400': selected }"
  >
    <!-- Type indicator stripe -->
    <div
      class="absolute top-0 left-0 w-1 h-full"
      :class="result.type === 'receipt' ? 'bg-amber-500' : 'bg-purple-500'"
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
                :class="result.type === 'receipt'
                  ? 'bg-amber-100 text-amber-700 dark:bg-zinc-900/30 dark:text-amber-300'
                  : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'"
              >
                <ReceiptRefundIcon v-if="result.type === 'receipt'" class="size-3 mr-1" />
                <DocumentIcon v-else class="size-3 mr-1" />
                {{ result.type }}
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

          <!-- Total amount for receipts -->
          <div v-if="result.type === 'receipt' && result.total" class="flex-shrink-0">
            <div class="text-right">
              <div class="text-lg font-bold text-zinc-900 dark:text-white">
                {{ result.total }}
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
import { ref } from 'vue';
import {
  DocumentIcon,
  ReceiptRefundIcon,
  FolderIcon,
  TagIcon,
  EyeIcon,
  ArrowTopRightOnSquareIcon,
  DocumentTextIcon,
  SparklesIcon
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

const highlightText = (text) => {
  if (!text || !props.searchQuery) return text;

  const query = props.searchQuery.trim();
  if (!query) return text;

  // Escape special regex characters
  const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`(${escapedQuery})`, 'gi');

  return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 text-zinc-900 dark:text-white px-0.5 rounded">$1</mark>');
};
</script>
