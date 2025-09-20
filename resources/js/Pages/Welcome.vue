<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const betaEmail = ref('');
const betaName = ref('');
const betaCompany = ref('');
const showBetaModal = ref(false);
const betaSubmitting = ref(false);
const betaSuccess = ref(false);
const betaError = ref('');

const requestBetaInvite = async () => {
    betaSubmitting.value = true;
    betaError.value = '';
    
    try {
        await axios.post('/api/beta-request', {
            email: betaEmail.value,
            name: betaName.value,
            company: betaCompany.value,
        });
        
        betaSuccess.value = true;
        setTimeout(() => {
            showBetaModal.value = false;
            betaSuccess.value = false;
            betaEmail.value = '';
            betaName.value = '';
            betaCompany.value = '';
        }, 3000);
    } catch (error) {
        betaError.value = error.response?.data?.message || 'Something went wrong. Please try again.';
    } finally {
        betaSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Welcome to PaperPulse" />
    
    <div class="min-h-screen bg-white dark:bg-gray-900">
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
                    
                    <div class="flex items-center space-x-6">
                        <!-- GitHub Icon -->
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200" aria-label="View on GitHub">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </a>
                        
                        <div v-if="canLogin" class="flex items-center space-x-6">
                            <template v-if="$page.props.auth.user">
                                <Link :href="route('dashboard')" class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                                    Dashboard
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="route('login')" class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                                    Sign in
                                </Link>
                                <Link v-if="canRegister" :href="route('register')" class="inline-flex items-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                                    Get Started
                                </Link>
                            </template>
                        </div>
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
                        <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-4 py-2 text-sm font-medium text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-700/10 dark:ring-blue-400/30">
                            🎯 Invite-Only Beta
                        </span>
                    </div>
                    
                    <h1 class="text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-7xl lg:text-8xl">
                        <span class="block text-indigo-900 dark:text-white">Say Goodbye to</span>
                        <span class="block bg-gradient-to-r from-blue-600 via-purple-600 to-blue-800 bg-clip-text text-transparent">
                            Paper Chaos
                        </span>
                        <span class="block text-indigo-900 dark:text-white">Forever</span>
                    </h1>
                    
                    <p class="mt-8 text-xl leading-8 text-gray-700 dark:text-gray-300 max-w-3xl mx-auto">
                        Stop losing receipts, missing tax deductions, and spending hours organizing paperwork. 
                        PaperPulse turns your document mess into organized, searchable intelligence.
                    </p>
                    
                    <div class="mt-12 flex flex-col sm:flex-row gap-6 justify-center">
                        <button @click="showBetaModal = true" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-4 text-lg font-semibold text-white shadow-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                            Request Beta Access
                            <svg class="ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                        
                        <a href="#features" class="inline-flex items-center justify-center rounded-full border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-8 py-4 text-lg font-semibold text-gray-900 dark:text-white shadow-md hover:border-gray-300 dark:hover:border-gray-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 transition-all duration-200">
                            Learn More
                        </a>
                    </div>
                </div>
            </section>

            <!-- Features Section (Redesigned) -->
            <section id="features" class="relative px-6 py-28 bg-gray-50 dark:bg-gray-800/50 lg:px-8 overflow-hidden">
                <!-- Decorative background -->
                <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-gradient-to-tr from-blue-500/20 to-purple-500/20 blur-3xl"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-gradient-to-tr from-pink-500/10 to-blue-500/10 blur-3xl"></div>
                
                <div class="relative mx-auto max-w-7xl">
                    <div class="text-center mb-16">
                        <h2 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            Paperwork, handled.
                        </h2>
                        <p class="mt-5 text-lg sm:text-xl text-gray-700 dark:text-gray-300 max-w-3xl mx-auto">
                            Capture once. Find fast. Get your time back.
                        </p>
                    </div>

                    <div class="grid gap-8 md:gap-10 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Feature 1 -->
                        <div class="group rounded-2xl bg-white/90 dark:bg-gray-900/60 p-7 shadow-md ring-1 ring-gray-200/60 dark:ring-white/10 hover:shadow-lg transition-all text-center">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-purple-600 text-white shadow-lg mx-auto">
                                <!-- Camera + Sparkles icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 8.5A2.5 2.5 0 016 6h2l1.2-1.6a1 1 0 01.8-.4h4a1 1 0 01.8.4L16.8 6H18a2.5 2.5 0 012.5 2.5V17A2.5 2.5 0 0118 19.5H6A2.5 2.5 0 013.5 17V8.5z"/>
                                    <circle cx="12" cy="13" r="3.2"/>
                                    <path stroke-linecap="round" d="M7.5 4.5l.7-1.8M19.2 9.2l1.3-.7M5 11.5l-1.6.7"/>
                                </svg>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold text-gray-900 dark:text-white">Capture once</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Snap a receipt, forward an email, or drop a PDF. We pull out vendor, totals, taxes, and dates automatically—no typing.
                            </p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="group rounded-2xl bg-white/90 dark:bg-gray-900/60 p-7 shadow-md ring-1 ring-gray-200/60 dark:ring-white/10 hover:shadow-lg transition-all text-center">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-purple-600 to-pink-600 text-white shadow-lg mx-auto">
                                <!-- Folder tree icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 6.5h6l1.2 1.2H20.5a1.5 1.5 0 011.5 1.5v7A2.8 2.8 0 0119.2 19H8.8A2.8 2.8 0 016 16.2V7.8A1.3 1.3 0 004.7 6.5H3.5z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8M8 15h5"/>
                                </svg>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold text-gray-900 dark:text-white">Everything in its place</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Smart tags and categories keep documents tidy. Duplicates are merged, and your timeline stays clean without lifting a finger.
                            </p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="group rounded-2xl bg-white/90 dark:bg-gray-900/60 p-7 shadow-md ring-1 ring-gray-200/60 dark:ring-white/10 hover:shadow-lg transition-all md:col-span-2 lg:col-span-1 text-center">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-green-600 to-blue-600 text-white shadow-lg mx-auto">
                                <!-- Lightning + search icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 2L6 13h5l-1 9 7.5-11h-5z"/>
                                    <circle cx="18.5" cy="18.5" r="3.5"/>
                                    <path stroke-linecap="round" d="M21.5 21.5l1.5 1.5"/>
                                </svg>
                            </div>
                            <h3 class="mt-5 text-xl font-semibold text-gray-900 dark:text-white">Ready when you need it</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Find anything by store, amount, item, or text. Export for taxes or share a secure link with your accountant in seconds.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How It Works Section (Redesigned) -->
            <section class="relative px-6 py-28 bg-white dark:bg-gray-900 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center mb-16">
                        <h2 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                            How PaperPulse works
                        </h2>
                        <p class="mt-5 text-lg sm:text-xl text-gray-700 dark:text-gray-300 max-w-3xl mx-auto">
                            From clutter to clarity in three quick steps.
                        </p>
                    </div>

                    

                    <div class="grid gap-8 lg:grid-cols-3">
                        <!-- Step 1 -->
                        <div class="relative rounded-2xl bg-gray-50/80 dark:bg-gray-800/60 p-7 pt-10 ring-1 ring-gray-200/60 dark:ring-white/10 shadow-sm text-center">
                            <div class="absolute -top-5 left-1/2 -translate-x-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-blue-600 to-purple-600 text-white text-sm font-semibold shadow-lg">1</div>
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-purple-600 text-white shadow-lg mx-auto">
                                <!-- Inbox icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5A2.5 2.5 0 016.5 5h11A2.5 2.5 0 0120 7.5V17a2 2 0 01-2 2H6a2 2 0 01-2-2V7.5z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h4l1.8 2h6.4l1.8-2h4"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">Capture</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Snap a photo or drag-and-drop PDFs. Receipts, invoices, and docs—all welcome.
                            </p>
                        </div>

                        <!-- Step 2 -->
                        <div class="relative rounded-2xl bg-gray-50/80 dark:bg-gray-800/60 p-7 pt-10 ring-1 ring-gray-200/60 dark:ring-white/10 shadow-sm text-center">
                            <div class="absolute -top-5 left-1/2 -translate-x-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-purple-600 to-pink-600 text-white text-sm font-semibold shadow-lg">2</div>
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-purple-600 to-pink-600 text-white shadow-lg mx-auto">
                                <!-- Magic wand icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 20l6-6M14 4l1.5-2M18 8l2-1.5M10 6L8.5 4M19 15l1 2"/>
                                    <rect x="11" y="11" width="10" height="2" rx="1" transform="rotate(45 11 11)"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">We extract the details</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Vendor, totals, taxes, dates—even line items. We dedupe, flag issues, and file everything neatly with smart tags.
                            </p>
                        </div>

                        <!-- Step 3 -->
                        <div class="relative rounded-2xl bg-gray-50/80 dark:bg-gray-800/60 p-7 pt-10 ring-1 ring-gray-200/60 dark:ring-white/10 shadow-sm text-center">
                            <div class="absolute -top-5 left-1/2 -translate-x-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-green-600 to-blue-600 text-white text-sm font-semibold shadow-lg">3</div>
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-tr from-green-600 to-blue-600 text-white shadow-lg mx-auto">
                                <!-- Share/export icon -->
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l5-5 5 5"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v10"/>
                                    <rect x="4" y="15" width="16" height="5" rx="2"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">Search, share, export</h3>
                            <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed">
                                Lightning‑fast search by store, amount, or text. Export CSV/PDF, or share a secure link with your accountant.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
 

            <!-- CTA Section -->
            <section class="px-6 py-24 bg-gradient-to-r from-blue-600 to-purple-600 lg:px-8">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                        Join the Paperwork Revolution
                    </h2>
                    <p class="mt-6 text-xl text-blue-100">
                        PaperPulse is currently in invite-only beta. Be among the first to experience the future of document management.
                    </p>
                    <div class="mt-12 flex flex-col sm:flex-row gap-6 justify-center">
                        <button @click="showBetaModal = true" class="inline-flex items-center justify-center rounded-full bg-white px-8 py-4 text-lg font-semibold text-blue-600 shadow-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600 transition-all duration-200 transform hover:scale-105">
                            Request Beta Invite
                        </button>
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="inline-flex items-center justify-center rounded-full border-2 border-white bg-transparent px-8 py-4 text-lg font-semibold text-white shadow-xl hover:bg-white hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600 transition-all duration-200">
                            <svg class="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            View on GitHub
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
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
                    
                    <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                        &copy; {{ new Date().getFullYear() }} PaperPulse. Transforming documents with intelligence.
                    </p>
                </div>
            </div>
        </footer>
        
        <!-- Beta Invite Modal -->
        <div v-if="showBetaModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" @click="showBetaModal = false"></div>
                
                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div v-if="!betaSuccess">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">Request Beta Access</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-600 dark:text-gray-300">Join the exclusive beta and be among the first to transform your document management.</p>
                            </div>
                        </div>
                        
                        <form @submit.prevent="requestBetaInvite" class="mt-5 sm:mt-6">
                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                    <input type="text" v-model="betaName" id="name" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="John Doe">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                    <input type="email" v-model="betaEmail" id="email" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="john@example.com">
                                </div>
                                
                                <div>
                                    <label for="company" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company (Optional)</label>
                                    <input type="text" v-model="betaCompany" id="company" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Acme Inc.">
                                </div>
                            </div>
                            
                            <div v-if="betaError" class="mt-4 rounded-md bg-red-50 p-3">
                                <p class="text-sm text-red-800">{{ betaError }}</p>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                <button type="submit" :disabled="betaSubmitting" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 sm:col-start-2">
                                    {{ betaSubmitting ? 'Submitting...' : 'Request Invite' }}
                                </button>
                                <button type="button" @click="showBetaModal = false" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 sm:col-start-1 sm:mt-0">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div v-else class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Success!</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-600 dark:text-gray-300">We've received your beta request. You'll hear from us soon!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
