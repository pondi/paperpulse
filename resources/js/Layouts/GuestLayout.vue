<script setup>
import ApplicationLogo from '@/Components/Common/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="relative flex min-h-screen flex-col justify-center overflow-hidden bg-zinc-50 px-4 py-8 dark:bg-zinc-950 sm:px-6">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-linear-to-b from-amber-100/80 to-transparent dark:from-amber-950/30"></div>
        <main class="relative mx-auto flex w-full max-w-md flex-col gap-6">
            <Link href="/" class="mx-auto inline-flex min-h-11 items-center gap-3 rounded-lg px-3 text-zinc-900 outline-hidden transition hover:bg-white focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-zinc-100 dark:hover:bg-zinc-900">
                <ApplicationLogo class="h-10 w-10" />
                <span class="text-xl font-bold tracking-tight">PaperPulse</span>
            </Link>

            <section aria-labelledby="guest-page-title" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl shadow-zinc-900/5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-8">
                <header class="mb-6">
                    <h1 id="guest-page-title" class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-zinc-50">{{ title }}</h1>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ description }}</p>
                </header>
                <slot />
            </section>

            <p class="text-center text-xs text-zinc-500 dark:text-zinc-500">
                Secure document management for your home and business.
            </p>
        </main>
    </div>
</template>
