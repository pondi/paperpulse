<template>
    <div v-if="links.length > 3" class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 sm:px-6">
        <div class="flex flex-1 justify-between sm:hidden">
            <component
                :is="links[0].url ? 'Link' : 'span'"
                :href="links[0].url"
                :class="[
                    'relative inline-flex items-center rounded-md border px-4 py-2 text-sm font-medium',
                    links[0].url
                        ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                        : 'border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                ]"
            >
                Previous
            </component>
            <component
                :is="links[links.length - 1].url ? 'Link' : 'span'"
                :href="links[links.length - 1].url"
                :class="[
                    'relative ml-3 inline-flex items-center rounded-md border px-4 py-2 text-sm font-medium',
                    links[links.length - 1].url
                        ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
                        : 'border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                ]"
            >
                Next
            </component>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Showing
                    <span class="font-medium">{{ from }}</span>
                    to
                    <span class="font-medium">{{ to }}</span>
                    of
                    <span class="font-medium">{{ total }}</span>
                    results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <component
                        v-for="(link, index) in links"
                        :key="index"
                        :is="link.url ? 'Link' : 'span'"
                        :href="link.url"
                        v-html="link.label"
                        :class="[
                            'relative inline-flex items-center px-4 py-2 text-sm font-semibold',
                            index === 0 ? 'rounded-l-md' : '',
                            index === links.length - 1 ? 'rounded-r-md' : '',
                            link.active
                                ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                                : link.url
                                    ? 'text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0'
                                    : 'text-gray-400 dark:text-gray-600 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 cursor-not-allowed'
                        ]"
                    />
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: Array,
    from: Number,
    to: Number,
    total: Number
})
</script>