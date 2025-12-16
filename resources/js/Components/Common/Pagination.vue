<template>
    <div v-if="links.length > 3" class="flex items-center justify-between border-t-2 border-amber-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-3 sm:px-6">
        <div class="flex flex-1 justify-between sm:hidden">
            <component
                :is="links[0].url ? 'Link' : 'span'"
                :href="links[0].url"
                :class="[
                    'relative inline-flex items-center rounded-md border-2 px-4 py-2 text-sm font-bold transition-all duration-200',
                    links[0].url
                        ? 'border-zinc-900 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 hover:bg-amber-50 dark:hover:bg-zinc-700'
                        : 'border-zinc-300 dark:border-zinc-600 bg-zinc-100 dark:bg-zinc-900 text-zinc-400 dark:text-zinc-600 cursor-not-allowed'
                ]"
            >
                Previous
            </component>
            <component
                :is="links[links.length - 1].url ? 'Link' : 'span'"
                :href="links[links.length - 1].url"
                :class="[
                    'relative ml-3 inline-flex items-center rounded-md border-2 px-4 py-2 text-sm font-bold transition-all duration-200',
                    links[links.length - 1].url
                        ? 'border-zinc-900 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 hover:bg-amber-50 dark:hover:bg-zinc-700'
                        : 'border-zinc-300 dark:border-zinc-600 bg-zinc-100 dark:bg-zinc-900 text-zinc-400 dark:text-zinc-600 cursor-not-allowed'
                ]"
            >
                Next
            </component>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    Showing
                    <span class="font-bold">{{ from }}</span>
                    to
                    <span class="font-bold">{{ to }}</span>
                    of
                    <span class="font-bold">{{ total }}</span>
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
                            'relative inline-flex items-center px-4 py-2 text-sm font-bold transition-all duration-200',
                            index === 0 ? 'rounded-l-md' : '',
                            index === links.length - 1 ? 'rounded-r-md' : '',
                            link.active
                                ? 'z-10 bg-amber-600 dark:bg-amber-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600'
                                : link.url
                                    ? 'text-zinc-900 dark:text-zinc-100 ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 hover:bg-amber-50 dark:hover:bg-zinc-700 focus:z-20 focus:outline-offset-0'
                                    : 'text-zinc-400 dark:text-zinc-600 ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 cursor-not-allowed'
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