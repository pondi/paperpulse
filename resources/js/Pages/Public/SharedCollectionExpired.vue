<script setup>
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Link Expired" />

    <div class="min-h-screen flex items-center justify-center bg-amber-50 dark:bg-zinc-900 px-4">
        <div class="max-w-md w-full bg-white dark:bg-zinc-800 rounded-xl shadow-lg p-8 text-center border border-amber-200 dark:border-zinc-700">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 mb-6">
                <svg class="h-8 w-8 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">
                Link Expired or Revoked
            </h1>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                This shared link is no longer available. It may have expired, reached its view limit, or been revoked by the owner.
            </p>

            <div class="pt-4 border-t border-amber-200 dark:border-zinc-700">
                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                    Shared via PaperPulse
                </p>
            </div>
        </div>
    </div>
</template>
