<template>
  <AuthenticatedLayout>
    <Head title="Search library" />
    <template #header>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p v-if="activeView" class="mb-1 text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Saved view</p>
          <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ activeView?.name || 'Search library' }}</h1>
          <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Search extracted text and narrow your results by date, amount or collection.</p>
        </div>
        <SaveViewButton scope="search" :filters="{ query: searchQuery, ...filters }" :active-view="activeView" />
      </div>
    </template>

    <div>
      <div class="mx-auto">
        <!-- Search Input -->
        <div class="mb-6">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <MagnifyingGlassIcon class="h-5 w-5 text-zinc-400" />
            </div>
            <input
              v-model="searchQuery"
              type="text"
              aria-label="Search document contents"
              placeholder="Search receipts, documents, invoices, and more..."
              class="block w-full pl-10 pr-3 py-3 border border-zinc-300 dark:border-zinc-600 rounded-lg leading-5 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
              @keyup.enter="performSearch"
            />
            <div v-if="searching" class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <svg class="animate-spin h-5 w-5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
          </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
          <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="search-filters" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 lg:hidden dark:border-zinc-700 dark:text-zinc-300">{{ filtersOpen ? 'Hide filters' : 'Show filters' }}</button>
          <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="text-sm text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300">Clear filters (keep search)</button>
        </div>
        <div class="flex flex-col gap-6 lg:flex-row">
          <!-- Filters Sidebar -->
          <div id="search-filters" :class="[filtersOpen ? 'block' : 'hidden lg:block', 'w-full shrink-0 lg:w-60']">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-4 lg:sticky lg:top-20">
              <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-zinc-900 dark:text-white">Filters</h3>
              </div>

              <div class="space-y-4">
                <!-- Type Filter -->
                <fieldset>
                  <legend class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                    Type
                  </legend>
                  <div class="space-y-2">
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="all"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        All
                        <span v-if="facets.total > 0" class="text-zinc-500">({{ facets.total }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="receipt"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Receipts
                        <span v-if="facets.receipts > 0" class="text-zinc-500">({{ facets.receipts }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="document"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Documents
                        <span v-if="facets.documents > 0" class="text-zinc-500">({{ facets.documents }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="invoice"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Invoices
                        <span v-if="facets.invoices > 0" class="text-zinc-500">({{ facets.invoices }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="contract"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Contracts
                        <span v-if="facets.contracts > 0" class="text-zinc-500">({{ facets.contracts }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="voucher"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Vouchers
                        <span v-if="facets.vouchers > 0" class="text-zinc-500">({{ facets.vouchers }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="warranty"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Warranties
                        <span v-if="facets.warranties > 0" class="text-zinc-500">({{ facets.warranties }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="return_policy"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Return Policies
                        <span v-if="facets.return_policies > 0" class="text-zinc-500">({{ facets.return_policies }})</span>
                      </span>
                    </label>
                    <label class="flex items-center">
                      <input
                        v-model="filters.type"
                        type="radio"
                        value="bank_statement"
                        class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600"
                      />
                      <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">
                        Bank Statements
                        <span v-if="facets.bank_statements > 0" class="text-zinc-500">({{ facets.bank_statements }})</span>
                      </span>
                    </label>
                  </div>
                </fieldset>

                <!-- Date Range Filter -->
                <fieldset class="border-t border-amber-200 dark:border-zinc-700 pt-4">
                  <legend class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                    Date Range
                  </legend>
                  <div class="space-y-2">
                    <label for="search-date-from" class="block text-sm text-zinc-700 dark:text-zinc-300">From date</label>
                    <input
                      id="search-date-from"
                      v-model="filters.date_from"
                      type="date"
                      class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                      placeholder="From"
                    />
                    <label for="search-date-to" class="block text-sm text-zinc-700 dark:text-zinc-300">To date</label>
                    <input
                      id="search-date-to"
                      v-model="filters.date_to"
                      type="date"
                      class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                      placeholder="To"
                    />
                  </div>
                </fieldset>

                <!-- Source amount filter -->
                <fieldset v-if="['all', 'receipt', 'invoice', 'voucher', 'contract', 'bank_statement'].includes(filters.type)" class="border-t border-amber-200 dark:border-zinc-700 pt-4">
                  <legend class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                    Amount Range · Source currency
                  </legend>
                  <p id="search-amount-basis" class="mb-3 text-xs text-zinc-600 dark:text-zinc-400">Amounts use each document's source currency, with no currency conversion. A minimum of 100 includes 100 NOK, 100 EUR and 100 USD. Ranges compare receipt/invoice totals, contract or voucher values, and statement closing balances.</p>
                  <div class="space-y-2">
                    <label for="search-amount-min" class="block text-sm text-zinc-700 dark:text-zinc-300">Minimum amount</label>
                    <input
                      id="search-amount-min" aria-describedby="search-amount-basis"
                      v-model.number="filters.amount_min"
                      type="number"
                      step="0.01"
                      class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                      placeholder="Min amount"
                    />
                    <label for="search-amount-max" class="block text-sm text-zinc-700 dark:text-zinc-300">Maximum amount</label>
                    <input
                      id="search-amount-max" aria-describedby="search-amount-basis"
                      v-model.number="filters.amount_max"
                      type="number"
                      step="0.01"
                      class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                      placeholder="Max amount"
                    />
                  </div>
                </fieldset>

                <!-- Category Filter -->
                <div v-if="filters.type === 'receipt'" class="border-t border-amber-200 dark:border-zinc-700 pt-4">
                  <label for="search-category" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                    Category
                  </label>
                  <select
                    id="search-category"
                    v-model="filters.category"
                    class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                  >
                    <option value="">All categories</option>
                    <option value="mat">Food</option>
                    <option value="transport">Transport</option>
                    <option value="entertainment">Entertainment</option>
                    <option value="utilities">Utilities</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <!-- Collection Filter -->
                <div class="border-t border-amber-200 dark:border-zinc-700 pt-4">
                  <label for="search-collection" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                    Collection
                  </label>
                  <select
                    id="search-collection"
                    v-model="filters.collection_id"
                    class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                  >
                    <option value="">All collections</option>
                    <option
                      v-for="collection in collections"
                      :key="collection.id"
                      :value="collection.id"
                    >
                      {{ collection.path }}
                    </option>
                  </select>
                </div>

                <!-- Apply Filters Button -->
                <div class="border-t border-amber-200 dark:border-zinc-700 pt-4">
                  <button
                    @click="performSearch"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                  >
                    Apply Filters
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Results Area -->
          <div class="min-w-0 flex-1">
            <!-- Bulk actions toolbar -->
            <div v-if="selectedCount > 0" class="mb-4 bg-amber-50 dark:bg-zinc-800 border border-amber-200 dark:border-amber-600 rounded-lg p-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <span class="text-sm font-medium text-zinc-900 dark:text-white">
                    {{ selectedCount }} item{{ selectedCount !== 1 ? 's' : '' }} selected
                  </span>
                  <button
                    @click="clearSelection"
                    class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white"
                  >
                    Clear selection
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    @click="openBulkActions('add')"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                  >
                    <svg class="size-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add to Collection
                  </button>
                  <button
                    @click="openBulkActions('remove')"
                    class="inline-flex items-center px-4 py-2 border border-zinc-300 dark:border-zinc-600 text-sm font-medium rounded-md text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
                  >
                    <svg class="size-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                    Remove from Collection
                  </button>
                </div>
              </div>
            </div>

            <div v-if="pagination.last_page > 1" class="flex items-center gap-3 mb-4">
              <button :disabled="searching || currentPage <= 1" @click="changePage(currentPage - 1)" class="text-amber-600 dark:text-amber-400 disabled:opacity-50">Previous</button>
              <span class="text-sm text-zinc-700 dark:text-zinc-300">Page {{ pagination.page }} of {{ pagination.last_page }}</span>
              <button :disabled="searching || currentPage >= pagination.last_page" @click="changePage(currentPage + 1)" class="text-amber-600 dark:text-amber-400 disabled:opacity-50">Next</button>
            </div>

            <!-- Results header -->
            <div v-if="results.length > 0 || searching" class="mb-4 flex flex-wrap items-center justify-between gap-4">
              <div class="flex flex-wrap items-center gap-4">
                <!-- Select all checkbox -->
                <label v-if="results.length > 0" class="flex items-center">
                  <input
                    type="checkbox"
                    aria-label="Select all search results on this page"
                    :checked="allSelected"
                    :indeterminate="someSelected"
                    @change="toggleSelectAll"
                    class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-zinc-300 dark:border-zinc-600 rounded"
                  />
                  <span class="ml-2 text-sm text-zinc-700 dark:text-zinc-300">Select all</span>
                </label>

                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                  <span v-if="!searching">
                    Found <span class="font-semibold">{{ pagination.total }}</span> result{{ pagination.total !== 1 ? 's' : '' }}
                    <span v-if="searchQuery"> for "<span class="font-semibold">{{ searchQuery }}</span>"</span>
                  </span>
                  <span v-else>Searching...</span>
                </div>
              </div>

              <!-- Sort options -->
              <div v-if="results.length > 0" class="flex items-center gap-2">
                <label for="search-sort" class="text-sm text-zinc-700 dark:text-zinc-300">Sort by:</label>
                <select
                  id="search-sort"
                  v-model="sortBy"
                  class="rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                >
                  <option value="relevance">Relevance</option>
                  <option value="date_desc">Date (newest)</option>
                  <option value="date_asc">Date (oldest)</option>
                  <option value="amount_desc" v-if="filters.type === 'receipt'">Amount (highest)</option>
                  <option value="amount_asc" v-if="filters.type === 'receipt'">Amount (lowest)</option>
                </select>
              </div>
            </div>

            <div v-if="searchError" role="alert" class="flex items-center gap-3 mb-4 rounded-lg border border-amber-300 dark:border-amber-700 p-4 text-zinc-900 dark:text-zinc-200">
              <p>{{ searchError }}</p>
              <button @click="performSearch" :disabled="searching" class="text-amber-600 dark:text-amber-400 disabled:opacity-50">Retry</button>
            </div>

            <!-- Results grid -->
            <div v-if="results.length > 0" class="space-y-3">
              <SearchResultCard
                v-for="result in sortedResults"
                :key="`${result.type}-${result.id}`"
                :result="result"
                :search-query="searchQuery"
                :selected="isSelected(result)"
                @preview="openPreview"
                @click="navigateToResult"
                @toggle-select="toggleSelect(result)"
              />
            </div>

            <!-- Empty state -->
            <div v-else-if="!searching && !searchError && (searchQuery || hasActiveFilters)" class="text-center py-12">
              <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-zinc-400" />
              <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">No results found</h3>
              <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Try adjusting your search or filters to find what you're looking for.
              </p>
              <button v-if="hasActiveFilters" type="button" @click="clearFilters" class="mt-3 text-sm text-amber-600 dark:text-amber-400">Clear filters (keep search)</button>
              <button v-else type="button" @click="searchQuery = ''" class="mt-3 text-sm text-amber-600 dark:text-amber-400">Clear search</button>
            </div>

            <!-- Initial state -->
            <div v-else-if="!searching && !searchError && !searchQuery" class="text-center py-12">
              <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-zinc-400" />
              <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">Start searching</h3>
              <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Search through your receipts, documents, invoices, contracts, and more using the search bar above.
              </p>
            </div>

            <!-- Loading state -->
            <div v-else-if="searching" class="space-y-3">
              <div v-for="i in 5" :key="i" class="animate-pulse bg-white dark:bg-zinc-800 rounded-lg border border-amber-200 dark:border-zinc-700 p-4">
                <div class="flex gap-4">
                  <div class="w-16 h-20 bg-amber-200 dark:bg-zinc-700 rounded"></div>
                  <div class="flex-1 space-y-3">
                    <div class="h-4 bg-amber-200 dark:bg-zinc-700 rounded w-3/4"></div>
                    <div class="h-3 bg-amber-200 dark:bg-zinc-700 rounded w-1/2"></div>
                    <div class="h-3 bg-amber-200 dark:bg-zinc-700 rounded w-full"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <FilePreviewModal
      :show="showPreview"
      :item="previewItem"
      @close="closePreview"
    />

    <!-- Bulk Actions Modal -->
    <Modal :show="showBulkActions" @close="closeBulkActions" v-slot="{ titleId }">
      <div class="p-6">
        <h3 :id="titleId" class="text-lg font-medium text-zinc-900 dark:text-white mb-4">
          {{ bulkActionType === 'add' ? 'Add to Collection' : 'Remove from Collection' }}
        </h3>

        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
          {{ bulkActionType === 'add'
            ? `Select a collection to add ${selectedCount} item${selectedCount !== 1 ? 's' : ''} to:`
            : `Select a collection to remove ${selectedCount} item${selectedCount !== 1 ? 's' : ''} from:` }}
        </p>

        <div class="mb-6">
          <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
            Collection
          </label>
          <select
            v-model="bulkActionCollection"
            class="block w-full rounded-md border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
          >
            <option value="">Select a collection</option>
            <option
              v-for="collection in collections"
              :key="collection.id"
              :value="collection.id"
            >
              {{ collection.path }}
            </option>
          </select>
        </div>

        <div class="flex justify-end gap-3">
          <button
            @click="closeBulkActions"
            type="button"
            class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500"
          >
            Cancel
          </button>
          <button
            @click="executeBulkAction"
            :disabled="!bulkActionCollection"
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ bulkActionType === 'add' ? 'Add to Collection' : 'Remove from Collection' }}
          </button>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SaveViewButton from '@/Components/Search/SaveViewButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchResultCard from '@/Components/Search/SearchResultCard.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
import Modal from '@/Components/Common/Modal.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

// Props from controller
const props = defineProps({
  initialFilters: { type: Object, default: () => ({}) },
  activeView: { type: Object, default: null },
  query: {
    type: String,
    default: ''
  },
  initialResults: {
    type: Array,
    default: () => []
  },
  initialSearchStatus: {
    type: String,
    default: 'available'
  },
  initialPagination: {
    type: Object,
    default: () => ({ page: 1, last_page: 0, total: 0 })
  },
  initialFacets: {
    type: Object,
    default: () => ({})
  }
});

// Search state
const searchQuery = ref(props.query || '');
const searching = ref(false);
const searchMessages = {
  partial: 'Some search results are unavailable. Retry to search all your documents.',
  unavailable: 'Search is unavailable. Please retry.'
};
const searchError = ref(searchMessages[props.initialSearchStatus] || '');
let requestSequence = 0;
let searchController = null;
const results = ref(props.initialResults || []);
const facets = ref(props.initialFacets || { total: 0, receipts: 0, documents: 0, invoices: 0, contracts: 0, vouchers: 0, warranties: 0, return_policies: 0, bank_statements: 0 });
const collections = ref([]);
const pagination = ref(props.initialPagination);
const currentPage = ref(props.initialPagination.page);
const filtersOpen = ref(false);

// Filter state
const filters = ref({
  type: 'all',
  date_from: '',
  date_to: '',
  amount_min: null,
  amount_max: null,
  category: '',
  collection_id: '',
  ...props.initialFilters
});

// Sort state
const sortBy = ref('relevance');

// Preview state
const showPreview = ref(false);
const previewItem = ref(null);

// Bulk selection state
const selectedResults = ref(new Set());
const showBulkActions = ref(false);
const bulkActionCollection = ref('');
const bulkActionType = ref(''); // 'add' or 'remove'

// Computed
const hasActiveFilters = computed(() => {
  return filters.value.type !== 'all' ||
    filters.value.date_from ||
    filters.value.date_to ||
    filters.value.amount_min !== null ||
    filters.value.amount_max !== null ||
    filters.value.category ||
    filters.value.collection_id ||
    filters.value.tags?.length ||
    filters.value.document_type ||
    filters.value.vendor ||
    filters.value.vendors?.length;
});

const sortedResults = computed(() => {
  const sorted = [...results.value];

  switch (sortBy.value) {
    case 'date_desc':
      return sorted.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
    case 'date_asc':
      return sorted.sort((a, b) => new Date(a.date || 0) - new Date(b.date || 0));
    case 'amount_desc':
      return sorted.sort((a, b) => {
        const aAmount = parseFloat(a.total?.replace(/[^0-9.-]+/g, '') || 0);
        const bAmount = parseFloat(b.total?.replace(/[^0-9.-]+/g, '') || 0);
        return bAmount - aAmount;
      });
    case 'amount_asc':
      return sorted.sort((a, b) => {
        const aAmount = parseFloat(a.total?.replace(/[^0-9.-]+/g, '') || 0);
        const bAmount = parseFloat(b.total?.replace(/[^0-9.-]+/g, '') || 0);
        return aAmount - bAmount;
      });
    case 'relevance':
    default:
      return sorted.sort((a, b) => (b._rankingScore || 0) - (a._rankingScore || 0));
  }
});

const selectedCount = computed(() => selectedResults.value.size);

const allSelected = computed(() => {
  return results.value.length > 0 && selectedResults.value.size === results.value.length;
});

const someSelected = computed(() => {
  return selectedResults.value.size > 0 && selectedResults.value.size < results.value.length;
});

// Methods
const emptyFacets = { total: 0, receipts: 0, documents: 0, invoices: 0, contracts: 0, vouchers: 0, warranties: 0, return_policies: 0, bank_statements: 0 };

const invalidateSearch = () => {
  requestSequence++;
  searchController?.abort();
  results.value = [];
  facets.value = emptyFacets;
  pagination.value = { page: currentPage.value, last_page: 0, total: 0 };
  searchError.value = '';
  searching.value = Boolean(searchQuery.value.trim() || hasActiveFilters.value);
  clearSelection();
};

const performSearch = async () => {
  clearTimeout(searchTimeout);
  invalidateSearch();
  const requestId = requestSequence;
  if (!searching.value) {
    router.replace({
      url: route('search', props.activeView ? { saved_search: props.activeView.id } : {}),
      preserveState: true,
      preserveScroll: true,
      props: current => ({ ...current, query: '', initialFilters: { ...filters.value },
        initialResults: [], initialPagination: pagination.value, initialFacets: emptyFacets,
        initialSearchStatus: 'available' }),
    });
    return;
  }
  searchController = new AbortController();

  try {
    const params = {
      query: searchQuery.value,
      page: currentPage.value,
      ...filters.value
    };
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key];
      }
    });

    const response = await axios.get('/search', { params, signal: searchController.signal });
    if (requestId !== requestSequence) {
      return;
    }
    results.value = response.data.results;
    pagination.value = response.data.pagination;
    facets.value = response.data.facets || emptyFacets;
    searchError.value = searchMessages[response.data.search_status] || '';
    router.replace({
      url: route('search', { ...params, ...(props.activeView ? { saved_search: props.activeView.id } : {}) }),
      preserveState: true,
      preserveScroll: true,
      props: current => ({ ...current, query: searchQuery.value, initialFilters: { ...filters.value },
        initialResults: results.value, initialPagination: pagination.value, initialFacets: facets.value,
        initialSearchStatus: response.data.search_status }),
    });
  } catch (error) {
    if (requestId !== requestSequence) {
      return;
    }
    searchError.value = error.response?.status === 422
      ? 'Check your search filters and try again.'
      : searchMessages.unavailable;
  } finally {
    if (requestId === requestSequence) {
      searching.value = false;
    }
  }
};

const changePage = (page) => {
  currentPage.value = page;
  clearSelection();
  performSearch();
};

const clearFilters = () => {
  sortBy.value = 'relevance';
  filters.value = {
    type: 'all',
    date_from: '',
    date_to: '',
    amount_min: null,
    amount_max: null,
    category: '',
    collection_id: ''
  };
};

const openPreview = (item) => {
  previewItem.value = item;
  showPreview.value = true;
};

const closePreview = () => {
  showPreview.value = false;
  previewItem.value = null;
};

const navigateToResult = (result) => {
  // Open preview instead of navigating
  openPreview(result);
};

// Bulk selection methods
const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedResults.value.clear();
  } else {
    results.value.forEach(result => {
      selectedResults.value.add(`${result.type}-${result.id}`);
    });
  }
};

const toggleSelect = (result) => {
  const key = `${result.type}-${result.id}`;
  if (selectedResults.value.has(key)) {
    selectedResults.value.delete(key);
  } else {
    selectedResults.value.add(key);
  }
};

const isSelected = (result) => {
  return selectedResults.value.has(`${result.type}-${result.id}`);
};

const clearSelection = () => {
  selectedResults.value.clear();
  showBulkActions.value = false;
  bulkActionCollection.value = '';
  bulkActionType.value = '';
};

const openBulkActions = (type) => {
  bulkActionType.value = type;
  showBulkActions.value = true;
};

const closeBulkActions = () => {
  showBulkActions.value = false;
  bulkActionCollection.value = '';
  bulkActionType.value = '';
};

const executeBulkAction = async () => {
  if (!bulkActionCollection.value) {
    return;
  }

  const fileIds = [];

  // Extract file IDs from selected results
  selectedResults.value.forEach(key => {
    const [type, id] = key.split('-');
    const result = results.value.find(r => r.type === type && r.id === parseInt(id));
    if (result?.file_id) {
      fileIds.push(result.file_id);
    }
  });

  if (fileIds.length === 0) {
    return;
  }

  try {
    const endpoint = bulkActionType.value === 'add'
      ? route('collections.files.add', bulkActionCollection.value)
      : route('collections.files.remove', bulkActionCollection.value);

    await axios({
      method: bulkActionType.value === 'add' ? 'post' : 'delete',
      url: endpoint,
      data: { file_ids: fileIds }
    });

    // Show success message or notification
    clearSelection();
    closeBulkActions();
  } catch (error) {
    console.error('Bulk action error:', error);
  }
};

// Watch for filter changes
watch(filters, () => {
  currentPage.value = 1;
  performSearch();
}, { deep: true });

// Debounced search on query change
let searchTimeout = null;
watch(searchQuery, () => {
  currentPage.value = 1;
  invalidateSearch();
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    performSearch();
  }, 300);
});

onBeforeUnmount(() => {
  clearTimeout(searchTimeout);
  requestSequence++;
  searchController?.abort();
});

// Load collections on mount
onMounted(async () => {
  try {
    const response = await axios.get(route('collections.all'));
    collections.value = response.data.data || response.data || [];
  } catch (error) {
    console.error('Failed to load collections:', error);
  }
});
</script>
