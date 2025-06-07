<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Welcome to PaperPulse" />
    
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-white dark:from-gray-900 dark:to-gray-800 flex flex-col">
        <!-- Navigation -->
        <nav class="relative px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xl font-bold text-gray-900 dark:text-white">PaperPulse</span>
                </div>
                
                <div v-if="canLogin" class="flex items-center space-x-4">
                    <template v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-semibold text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400">
                            Log in
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            Get Started
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="relative px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col items-center space-y-10 py-20 text-center lg:py-32">
                    <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                        Transform Your Paper Documents Into Digital Intelligence
                    </h1>
                    <p class="max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        PaperPulse helps you digitize, organize, and analyze your receipts and documents. Save time and gain insights with our powerful document processing platform.
                    </p>
                    <div class="flex flex-col space-y-4 sm:flex-row sm:space-x-4 sm:space-y-0">
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="rounded-lg bg-blue-600 px-8 py-3 text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            View on GitHub
                        </a>
                    </div>
                </div>

                <!-- Features Section -->
                <div id="features" class="py-20">
                    <div class="grid gap-12 md:grid-cols-3">
                        <!-- Feature 1 -->
                        <div class="flex flex-col items-center space-y-4 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Smart Document Processing</h3>
                            <p class="text-gray-600 dark:text-gray-400">Upload any document and let our AI extract and organize the important information automatically.</p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="flex flex-col items-center space-y-4 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Advanced Analytics</h3>
                            <p class="text-gray-600 dark:text-gray-400">Get insights into your spending patterns and document trends with powerful analytics tools.</p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="flex flex-col items-center space-y-4 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Secure Storage</h3>
                            <p class="text-gray-600 dark:text-gray-400">Your documents are encrypted and stored securely in the cloud, accessible whenever you need them.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t border-gray-200 bg-gray-50 py-12 dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-8">
                <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                    &copy; {{ new Date().getFullYear() }} PaperPulse. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
