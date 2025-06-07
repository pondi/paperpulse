<template>
    <div class="relative flex flex-1 gap-x-4 lg:gap-x-6">
        <form class="grid flex-1 grid-cols-1" action="#" method="GET">
            <input
                type="search"
                v-model="searchQuery"
                @input="handleSearch"
                placeholder="Search receipts..."
                class="col-start-1 row-start-1 block size-full bg-transparent pl-8 text-base text-white outline-none border-0 focus:outline-none focus:ring-0 placeholder:text-gray-500 sm:text-sm/6"
            />
            <MagnifyingGlassIcon 
                class="pointer-events-none col-start-1 row-start-1 size-5 self-center text-gray-500 ml-1" 
                aria-hidden="true" 
            />
            <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-gray-500"></div>
            </div>

            <!-- Search Results Dropdown -->
            <div v-if="showResults && results.length > 0" class="absolute left-0 right-0 top-full mt-2 w-[600px] max-w-[90vw] bg-gray-800/95 backdrop-blur-sm rounded-md shadow-lg max-h-96 overflow-y-auto ring-1 ring-white/20">
                <ul class="py-1">
                    <li v-for="result in results" :key="result.id" class="px-4 py-3 hover:bg-gray-700/90 cursor-pointer border-b border-white/20 last:border-b-0">
                        <Link :href="result.url" class="block" @click="showResults = false">
                            <div class="flex items-start gap-x-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-x-3">
                                        <div class="text-sm font-medium text-gray-100 truncate">{{ result.title }}</div>
                                        <div class="text-sm font-medium text-gray-100 shrink-0">{{ result.total }}</div>
                                    </div>
                                    <div class="text-sm text-gray-300 mt-1">{{ result.description }}</div>
                                    <div class="flex items-center gap-x-3 mt-1">
                                        <div v-if="result.items" class="text-xs text-gray-400 truncate flex-1">
                                            {{ result.items }}
                                        </div>
                                        <div class="flex items-center gap-x-2 shrink-0 text-xs text-gray-300">
                                            <span v-if="result.date">{{ result.date }}</span>
                                            <span v-if="result.category" class="bg-gray-600/70 px-2 py-0.5 rounded">{{ result.category }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- No Results Message -->
            <div v-if="showResults && results.length === 0 && searchQuery" class="absolute left-0 right-0 top-full mt-2 w-[600px] max-w-[90vw] bg-gray-800/95 backdrop-blur-sm rounded-md shadow-lg p-4 text-center text-gray-300 ring-1 ring-white/20">
                No results found
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
import { Link } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import _ from 'lodash';

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
        results.value = data.results;
        showResults.value = true;
    } catch (error) {
        console.error('Search error:', error);
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

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