<template>
    <div class="relative flex flex-1 gap-x-4 lg:gap-x-6">
        <form class="grid flex-1 grid-cols-1" action="#" method="GET" @submit.prevent="goToFullSearch">
            <input
                type="search"
                v-model="searchQuery"
                @input="handleSearch"
                @keydown.enter="goToFullSearch"
                placeholder="Search receipts and documents..."
                class="col-start-1 row-start-1 block size-full bg-transparent pl-8 text-base text-zinc-900 dark:text-white outline-none border-0 focus:outline-none focus:ring-0 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 sm:text-sm/6"
            />
            <MagnifyingGlassIcon
                class="pointer-events-none col-start-1 row-start-1 size-5 self-center text-zinc-500 ml-1"
                aria-hidden="true"
            />
            <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-amber-500"></div>
            </div>

            <!-- Search Results Dropdown -->
            <div v-if="showResults && results.length > 0" class="absolute left-0 right-0 top-full mt-2 w-[700px] max-w-[90vw] bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm rounded-lg shadow-xl max-h-96 overflow-y-auto ring-1 ring-zinc-200 dark:ring-white/20 z-50">
                <ul class="py-2">
                    <li v-for="result in results.slice(0, 5)" :key="`${result.type}-${result.id}`" class="group">
                        <div class="block px-4 py-3 hover:bg-amber-100 dark:hover:bg-zinc-700/90 transition-colors cursor-pointer" @click="handleResultClick(result)">
                            <div class="flex items-start gap-x-3">
                                <!-- Type indicator -->
                                <div class="flex-shrink-0 mt-1">
                                    <span
                                        class="inline-flex items-center justify-center w-8 h-8 rounded"
                                        :class="result.type === 'receipt'
                                            ? 'bg-amber-100 dark:bg-zinc-900/30 text-amber-600 dark:text-amber-400'
                                            : 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'"
                                    >
                                        <ReceiptRefundIcon v-if="result.type === 'receipt'" class="w-5 h-5" />
                                        <DocumentIcon v-else class="w-5 h-5" />
                                    </span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-x-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                                                    {{ result.type }}
                                                </span>
                                                <span v-if="result.date" class="text-xs text-zinc-400 dark:text-zinc-500">
                                                    {{ result.date }}
                                                </span>
                                            </div>
                                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate group-hover:text-amber-600 dark:group-hover:text-amber-400">
                                                {{ result.title }}
                                            </div>
                                        </div>
                                        <div v-if="result.total" class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 shrink-0">
                                            {{ result.total }}
                                        </div>
                                    </div>
                                    <div v-if="result.description" class="text-xs text-zinc-600 dark:text-zinc-300 mt-1 line-clamp-1">
                                        {{ result.description }}
                                    </div>
                                    <div class="flex items-center gap-x-2 mt-2 text-xs">
                                        <span v-if="result.category" class="inline-flex items-center bg-amber-200 dark:bg-zinc-600/70 px-2 py-0.5 rounded text-zinc-700 dark:text-zinc-300">
                                            {{ result.category }}
                                        </span>
                                        <span v-if="result.document_type" class="inline-flex items-center bg-purple-100 dark:bg-purple-900/30 px-2 py-0.5 rounded text-purple-700 dark:text-purple-300">
                                            {{ result.document_type }}
                                        </span>
                                        <span v-if="result.tags && result.tags.length > 0" class="text-zinc-500 dark:text-zinc-400">
                                            {{ result.tags.slice(0, 2).join(', ') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- View all results footer -->
                <div class="border-t border-amber-200 dark:border-zinc-700">
                    <Link
                        :href="`/search?query=${encodeURIComponent(searchQuery)}`"
                        class="block px-4 py-3 text-center text-sm font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-zinc-700/50 transition-colors"
                        @click="showResults = false"
                    >
                        View all {{ results.length }} results
                        <ArrowRightIcon class="inline-block w-4 h-4 ml-1" />
                    </Link>
                </div>
            </div>

            <!-- No Results Message -->
            <div v-if="showResults && results.length === 0 && searchQuery" class="absolute left-0 right-0 top-full mt-2 w-[600px] max-w-[90vw] bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm rounded-lg shadow-xl p-4 ring-1 ring-zinc-200 dark:ring-white/20 z-50">
                <div class="text-center">
                    <MagnifyingGlassIcon class="mx-auto h-8 w-8 text-zinc-400 mb-2" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">No results found</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Try different keywords or filters</p>
                </div>
            </div>
        </form>
    </div>
</template>

<style>
input[type="search"]::-webkit-search-decoration,
input[type="search"]::-webkit-search-cancel-button,
input[type="search"]::-webkit-search-results-button,
input[type="search"]::-webkit-search-results-decoration {
    -webkit-appearance: none;
}
</style>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, DocumentIcon, ReceiptRefundIcon, ArrowRightIcon } from '@heroicons/vue/20/solid';
import _ from 'lodash';

const emit = defineEmits(['preview']);

const searchQuery = ref('');
const results = ref([]);
const isLoading = ref(false);
const showResults = ref(false);

const handleSearch = _.debounce(async () => {
    if (!searchQuery.value) {
        results.value = [];
        showResults.value = false;
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch(`/search?query=${encodeURIComponent(searchQuery.value)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        results.value = data.results || [];
        showResults.value = true;
    } catch (error) {
        console.error('Search error:', error);
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

const goToFullSearch = () => {
    if (searchQuery.value.trim()) {
        showResults.value = false;
        router.visit(`/search?query=${encodeURIComponent(searchQuery.value)}`);
    }
};

const handleResultClick = (result) => {
    showResults.value = false;
    emit('preview', result);
};

// Close results when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest('.grid')) {
        showResults.value = false;
    }
};

// Close results when pressing Escape
const handleEscape = (event) => {
    if (event.key === 'Escape') {
        showResults.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
});
</script> 
