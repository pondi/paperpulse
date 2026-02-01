<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        default: '',
    },
    as: {
        type: String,
        default: 'link',
    },
});
</script>

<template>
    <button
        v-if="as === 'button'"
        type="button"
        class="flex w-full items-center px-4 py-2 text-start text-sm leading-5 text-zinc-700 dark:text-zinc-300 hover:bg-amber-100 dark:hover:bg-zinc-800 focus:outline-none focus:bg-amber-100 dark:focus:bg-zinc-800 transition duration-150 ease-in-out"
    >
        <slot />
    </button>
    <Link
        v-else
        :href="href"
        class="flex w-full items-center px-4 py-2 text-start text-sm leading-5 text-zinc-700 dark:text-zinc-300 hover:bg-amber-100 dark:hover:bg-zinc-800 focus:outline-none focus:bg-amber-100 dark:focus:bg-zinc-800 transition duration-150 ease-in-out"
    >
        <slot />
    </Link>
</template>
