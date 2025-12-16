<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center px-5 py-2 bg-white dark:bg-zinc-800 border-2 border-zinc-900 dark:border-zinc-600 rounded-md font-bold text-sm text-zinc-900 dark:text-zinc-100 uppercase tracking-widest shadow-sm hover:bg-amber-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900 disabled:opacity-25 transition-all duration-200"
    >
        <slot />
    </button>
</template>
