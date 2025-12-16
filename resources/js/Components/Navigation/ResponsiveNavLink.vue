<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-amber-400 dark:border-amber-600 text-start text-base font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-zinc-900/50 focus:outline-none focus:text-amber-800 dark:focus:text-amber-200 focus:bg-amber-100 dark:focus:bg-zinc-900 focus:border-amber-700 dark:focus:border-amber-300 transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200 hover:bg-amber-50 dark:hover:bg-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 focus:outline-none focus:text-zinc-900 dark:focus:text-zinc-200 focus:bg-amber-50 dark:focus:bg-zinc-700 focus:border-zinc-300 dark:focus:border-zinc-600 transition duration-150 ease-in-out'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
