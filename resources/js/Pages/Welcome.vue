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
    
    <div class="min-h-screen bg-white">
        <!-- Navigation -->
        <nav class="relative px-6 py-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-purple-600 shadow-lg">
                            <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">PaperPulse</span>
                    </div>
                    
                    <div v-if="canLogin" class="flex items-center space-x-6">
                        <template v-if="$page.props.auth.user">
                            <Link :href="route('dashboard')" class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="text-sm font-semibold text-gray-600 hover:text-blue-600 transition-colors duration-200">
                                Sign in
                            </Link>
                            <Link v-if="canRegister" :href="route('register')" class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                                Get Started
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="relative">
            <!-- Hero -->
            <section class="px-6 py-24 lg:px-8 lg:py-32">
                <div class="mx-auto max-w-4xl text-center">
                    <div class="mb-8">
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                            🎯 Invite-Only Beta
                        </span>
                    </div>
                    
                    <h1 class="text-5xl font-bold tracking-tight text-gray-900 sm:text-7xl lg:text-8xl">
                        <span class="block">Say Goodbye to</span>
                        <span class="block bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 bg-clip-text text-transparent">
                            Paper Chaos
                        </span>
                        <span class="block">Forever</span>
                    </h1>
                    
                    <p class="mt-8 text-xl leading-8 text-gray-600 max-w-3xl mx-auto">
                        Stop losing receipts, missing tax deductions, and spending hours organizing paperwork. 
                        PaperPulse turns your document mess into organized, searchable intelligence.
                    </p>
                    
                    <div class="mt-12 flex flex-col sm:flex-row gap-6 justify-center">
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-4 text-lg font-semibold text-white shadow-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                            View on GitHub
                            <svg class="ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        
                        <a href="#features" class="inline-flex items-center justify-center rounded-full border-2 border-gray-200 bg-white px-8 py-4 text-lg font-semibold text-gray-900 shadow-md hover:border-gray-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 transition-all duration-200">
                            Learn More
                        </a>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section id="features" class="px-6 py-24 bg-gray-50 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center mb-20">
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                            Stop Fighting Your Paperwork
                        </h2>
                        <p class="mt-6 text-xl text-gray-600 max-w-2xl mx-auto">
                            Three simple ways PaperPulse saves you time, money, and headaches
                        </p>
                    </div>
                    
                    <div class="grid gap-12 lg:grid-cols-3 lg:gap-16">
                        <!-- Feature 1 -->
                        <div class="group text-center">
                            <div class="relative flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-500 to-purple-600 shadow-lg group-hover:shadow-xl transition-shadow duration-300 mx-auto">
                                    <svg class="h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.641-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                    </svg>
                                </div>
                            </div>
                            <h3 class="mt-8 text-2xl font-bold text-gray-900">Snap and Forget</h3>
                            <p class="mt-4 text-lg text-gray-600 leading-7">
                                Just take a photo of your receipt or document. We'll read everything important and organize it automatically. No more lost paperwork or manual data entry.
                            </p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="group text-center">
                            <div class="relative flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-tr from-purple-500 to-pink-600 shadow-lg group-hover:shadow-xl transition-shadow duration-300 mx-auto">
                                    <svg class="h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </div>
                            </div>
                            <h3 class="mt-8 text-2xl font-bold text-gray-900">Find Anything Instantly</h3>
                            <p class="mt-4 text-lg text-gray-600 leading-7">
                                Need that warranty from 2022? Or last month's coffee expense? Search by store, amount, date, or even what you bought. Everything is instantly searchable.
                            </p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="group text-center">
                            <div class="relative flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-tr from-green-500 to-blue-600 shadow-lg group-hover:shadow-xl transition-shadow duration-300 mx-auto">
                                    <svg class="h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <h3 class="mt-8 text-2xl font-bold text-gray-900">Save Money at Tax Time</h3>
                            <p class="mt-4 text-lg text-gray-600 leading-7">
                                Never miss another deduction. We track your spending patterns and help you spot opportunities to save money. Your accountant will thank you.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="px-6 py-24 lg:px-8">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                        Ready to Reclaim Your Time?
                    </h2>
                    <p class="mt-6 text-xl text-gray-600">
                        PaperPulse is currently invite-only while we perfect the experience. Want to try it yourself? The complete source code is available on GitHub.
                    </p>
                    <div class="mt-12">
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-4 text-lg font-semibold text-white shadow-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                            Get PaperPulse on GitHub
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-50 border-t border-gray-200">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <div class="flex flex-col items-center">
                    <div class="flex items-center space-x-3 mb-8">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-tr from-blue-600 to-purple-600">
                            <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">PaperPulse</span>
                    </div>
                    
                    <p class="text-center text-sm text-gray-500">
                        &copy; {{ new Date().getFullYear() }} PaperPulse. Transforming documents with intelligence.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
