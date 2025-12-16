<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-zinc-700 dark:text-zinc-300 hover:bg-amber-100 dark:hover:bg-zinc-800 focus:outline-none focus:bg-amber-100 dark:focus:bg-zinc-800 transition duration-150 ease-in-out"
    >
        <slot />
    </Link>
</template>
