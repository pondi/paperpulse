<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const showInviteModal = ref(false);
const inviteSuccess = ref(false);

// Avoid rendering <Head> during the very first paint to rule out
// any interaction between Inertia's head manager and initial mount.
const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

const inviteForm = useForm({
    name: '',
    email: '',
    company: '',
});

const requestInvite = () => {
    inviteForm.post('/invitation-request', {
        preserveScroll: true,
        onSuccess: () => {
            inviteSuccess.value = true;
            setTimeout(() => {
                showInviteModal.value = false;
                inviteSuccess.value = false;
                inviteForm.reset();
            }, 3000);
        },
    });
};
</script>

<template>
    <Head v-if="isMounted" title="Welcome to PaperPulse" />

    <div class="min-h-screen bg-amber-50 dark:bg-zinc-900">
        <!-- Navigation -->
        <nav class="relative px-6 py-5 lg:px-8 border-b border-amber-200/50 dark:border-zinc-700/50 bg-white/60 dark:bg-zinc-800/60 backdrop-blur-sm">
            <div class="mx-auto max-w-7xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex h-9 w-9 items-center justify-center rotate-6 bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 shadow-md" style="clip-path: polygon(5% 5%, 100% 0%, 95% 95%, 0% 100%);">
                            <svg class="h-5 w-5 text-white -rotate-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-xl font-black text-zinc-900 dark:text-zinc-100">PaperPulse</span>
                    </div>

                    <div class="flex items-center space-x-4 md:space-x-6">
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors duration-200" aria-label="View on GitHub">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </a>

                        <div v-if="canLogin" class="flex items-center space-x-4 md:space-x-6">
                            <template v-if="$page.props.auth.user">
                                <Link :href="route('dashboard')" class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700 shadow-sm hover:shadow transition-all duration-200">
                                    Dashboard
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="route('login')" class="text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors duration-200">
                                    Sign in
                                </Link>
                                <button v-if="canRegister" @click="showInviteModal = true" class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700 shadow-sm hover:shadow transition-all duration-200">
                                    Get Started
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="relative">
            <section class="relative px-6 py-16 lg:px-8 lg:py-24 overflow-hidden">
                <!-- Decorative elements -->
                <div aria-hidden="true" class="absolute top-20 right-10 w-64 h-64 bg-gradient-to-br from-amber-200/40 to-orange-200/40 dark:from-amber-900/20 dark:to-orange-900/20 blur-3xl rounded-full"></div>
                <div aria-hidden="true" class="absolute bottom-20 left-10 w-80 h-80 bg-gradient-to-br from-orange-200/30 to-red-200/30 dark:from-orange-900/10 dark:to-red-900/10 blur-3xl rounded-full"></div>

                <div class="relative mx-auto max-w-7xl">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <!-- Left Column -->
                        <div>
                            <div class="inline-block mb-6 px-3 py-1 bg-zinc-900 dark:bg-amber-600 text-white text-xs font-bold uppercase tracking-wider transform -rotate-1">
                                Invitation Only
                            </div>

                            <h1 class="text-5xl md:text-6xl lg:text-7xl font-black text-zinc-900 dark:text-zinc-100 leading-tight mb-6">
                                Your receipts.<br/>
                                <span class="text-amber-600 dark:text-amber-500">Sorted.</span><br/>
                                Finally.
                            </h1>

                            <p class="text-lg md:text-xl text-zinc-700 dark:text-zinc-300 mb-10 max-w-xl leading-relaxed">
                                No more shoebox chaos. No missed deductions. No manual entry. Just point, scan, and forget—PaperPulse remembers it all.
                            </p>

                            <div class="flex flex-col sm:flex-row gap-4">
                                <button @click="showInviteModal = true" class="group inline-flex items-center justify-center px-8 py-4 text-base font-bold text-white bg-zinc-900 dark:bg-amber-600 hover:bg-zinc-800 dark:hover:bg-amber-700 shadow-lg hover:shadow-xl transition-all duration-200">
                                    Request Invitation
                                    <svg class="ml-2 h-5 w-5 group-hover:translate-x-1 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </button>

                                <a href="#how-it-works" class="inline-flex items-center justify-center px-8 py-4 text-base font-bold text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-800 border-2 border-zinc-900 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-sm transition-all duration-200">
                                    See How It Works
                                </a>
                            </div>
                        </div>

                        <!-- Right Column - Visual Element -->
                        <div class="relative hidden lg:block">
                            <div class="relative">
                                <!-- Stacked receipt effect -->
                                <div class="absolute top-0 right-8 w-64 h-80 bg-white dark:bg-zinc-800 shadow-xl transform rotate-6 border border-zinc-200 dark:border-zinc-700"></div>
                                <div class="absolute top-4 right-12 w-64 h-80 bg-white dark:bg-zinc-800 shadow-lg transform rotate-3 border border-zinc-200 dark:border-zinc-700"></div>
                                <div class="relative w-64 h-80 bg-gradient-to-br from-white to-amber-50 dark:from-zinc-800 dark:to-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 p-6">
                                    <div class="h-4 w-32 bg-zinc-900 dark:bg-amber-600 mb-4"></div>
                                    <div class="space-y-3 mb-6">
                                        <div class="h-2 w-full bg-zinc-200 dark:bg-zinc-700"></div>
                                        <div class="h-2 w-5/6 bg-zinc-200 dark:bg-zinc-700"></div>
                                        <div class="h-2 w-4/6 bg-zinc-200 dark:bg-zinc-700"></div>
                                    </div>
                                    <div class="space-y-2 mb-6">
                                        <div class="flex justify-between">
                                            <div class="h-2 w-20 bg-zinc-300 dark:bg-zinc-600"></div>
                                            <div class="h-2 w-12 bg-zinc-300 dark:bg-zinc-600"></div>
                                        </div>
                                        <div class="flex justify-between">
                                            <div class="h-2 w-24 bg-zinc-300 dark:bg-zinc-600"></div>
                                            <div class="h-2 w-10 bg-zinc-300 dark:bg-zinc-600"></div>
                                        </div>
                                        <div class="flex justify-between">
                                            <div class="h-2 w-16 bg-zinc-300 dark:bg-zinc-600"></div>
                                            <div class="h-2 w-14 bg-zinc-300 dark:bg-zinc-600"></div>
                                        </div>
                                    </div>
                                    <div class="border-t border-zinc-300 dark:border-zinc-600 pt-3">
                                        <div class="flex justify-between items-center">
                                            <div class="h-3 w-16 bg-zinc-900 dark:bg-amber-600"></div>
                                            <div class="h-3 w-20 bg-zinc-900 dark:bg-amber-600"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section id="features" class="relative px-6 py-20 bg-white dark:bg-zinc-800 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-16">
                        <h2 class="text-3xl md:text-4xl font-black text-zinc-900 dark:text-zinc-100 mb-3">
                            What makes it different
                        </h2>
                        <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl">
                            Three powerful features. Zero busywork.
                        </p>
                    </div>

                    <div class="grid md:grid-cols-3 gap-8">
                        <!-- Feature 1 -->
                        <div class="group relative bg-amber-50 dark:bg-zinc-900 p-8 border-l-4 border-amber-600 dark:border-amber-500 hover:shadow-lg transition-shadow duration-200">
                            <div class="flex items-center mb-4">
                                <div class="flex h-10 w-10 items-center justify-center bg-zinc-900 dark:bg-amber-600 text-white">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">Snap & Done</h3>
                            <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                Photo, email, or PDF—we extract vendor, totals, tax, dates, and line items automatically. Zero typing required.
                            </p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="group relative bg-amber-50 dark:bg-zinc-900 p-8 border-l-4 border-orange-600 dark:border-orange-500 hover:shadow-lg transition-shadow duration-200">
                            <div class="flex items-center mb-4">
                                <div class="flex h-10 w-10 items-center justify-center bg-zinc-900 dark:bg-orange-600 text-white">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">Auto-Organized</h3>
                            <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                Smart tags, categories, and deduplication keep everything tidy. No folder hunting. No duplicate headaches.
                            </p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="group relative bg-amber-50 dark:bg-zinc-900 p-8 border-l-4 border-red-600 dark:border-red-500 hover:shadow-lg transition-shadow duration-200">
                            <div class="flex items-center mb-4">
                                <div class="flex h-10 w-10 items-center justify-center bg-zinc-900 dark:bg-red-600 text-white">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">Find Instantly</h3>
                            <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                Search by store, amount, item, or date. Export for taxes. Share secure links with your accountant in seconds.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How It Works Section -->
            <section id="how-it-works" class="relative px-6 py-20 bg-gradient-to-br from-amber-100 to-orange-100 dark:from-zinc-900 dark:to-zinc-800 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-16 text-center">
                        <h2 class="text-3xl md:text-4xl font-black text-zinc-900 dark:text-zinc-100 mb-3">
                            How it works
                        </h2>
                        <p class="text-lg text-zinc-700 dark:text-zinc-300 max-w-2xl mx-auto">
                            Three steps from chaos to clarity.
                        </p>
                    </div>

                    <div class="relative">
                        <!-- Connection line -->
                        <div class="hidden lg:block absolute top-1/2 left-0 right-0 h-1 bg-zinc-900 dark:bg-amber-600 transform -translate-y-1/2"></div>

                        <div class="grid lg:grid-cols-3 gap-8 relative">
                            <!-- Step 1 -->
                            <div class="relative bg-white dark:bg-zinc-800 p-8 shadow-lg border-t-4 border-amber-600">
                                <div class="inline-flex items-center justify-center w-12 h-12 bg-zinc-900 dark:bg-amber-600 text-white text-xl font-black mb-6">
                                    1
                                </div>
                                <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">Upload</h3>
                                <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                    Snap a receipt with your phone, forward an invoice email, or drop a PDF. All formats welcome.
                                </p>
                            </div>

                            <!-- Step 2 -->
                            <div class="relative bg-white dark:bg-zinc-800 p-8 shadow-lg border-t-4 border-orange-600">
                                <div class="inline-flex items-center justify-center w-12 h-12 bg-zinc-900 dark:bg-orange-600 text-white text-xl font-black mb-6">
                                    2
                                </div>
                                <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">AI Extracts</h3>
                                <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                    We pull vendor names, amounts, tax, dates, and line items. Files get tagged, categorized, and deduplicated automatically.
                                </p>
                            </div>

                            <!-- Step 3 -->
                            <div class="relative bg-white dark:bg-zinc-800 p-8 shadow-lg border-t-4 border-red-600">
                                <div class="inline-flex items-center justify-center w-12 h-12 bg-zinc-900 dark:bg-red-600 text-white text-xl font-black mb-6">
                                    3
                                </div>
                                <h3 class="text-xl font-black text-zinc-900 dark:text-zinc-100 mb-3">You're Done</h3>
                                <p class="text-zinc-700 dark:text-zinc-300 leading-relaxed">
                                    Search anything instantly. Export CSV or PDF for taxes. Share secure links with your team or accountant.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="relative px-6 py-20 bg-zinc-900 dark:bg-amber-600 lg:px-8 overflow-hidden">
                <!-- Decorative elements -->
                <div aria-hidden="true" class="absolute top-0 right-0 w-96 h-96 bg-amber-600/20 dark:bg-zinc-900/20 blur-3xl rounded-full"></div>

                <div class="relative mx-auto max-w-4xl text-center">
                    <h2 class="text-4xl md:text-5xl font-black text-white mb-6">
                        Ready to ditch the shoebox?
                    </h2>
                    <p class="text-xl text-amber-100 dark:text-zinc-900 mb-10 max-w-2xl mx-auto">
                        Request an invitation and be among the first to experience effortless document management.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button @click="showInviteModal = true" class="group inline-flex items-center justify-center px-8 py-4 text-lg font-bold text-zinc-900 dark:text-amber-600 bg-white dark:bg-zinc-900 hover:bg-amber-50 dark:hover:bg-zinc-800 shadow-xl hover:shadow-2xl transition-all duration-200">
                            Request Invitation
                        </button>
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold text-white border-2 border-white hover:bg-white hover:text-zinc-900 dark:hover:text-amber-600 transition-all duration-200">
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
        <footer class="bg-white dark:bg-zinc-800 border-t border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex items-center space-x-3 mb-6 md:mb-0">
                        <div class="flex h-7 w-7 items-center justify-center rotate-6 bg-gradient-to-br from-amber-500 via-orange-500 to-red-500" style="clip-path: polygon(5% 5%, 100% 0%, 95% 95%, 0% 100%);">
                            <svg class="h-4 w-4 text-white -rotate-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-base font-bold text-zinc-900 dark:text-zinc-100">PaperPulse</span>
                    </div>

                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        &copy; {{ new Date().getFullYear() }} PaperPulse. Document intelligence, simplified.
                    </p>
                </div>
            </div>
        </footer>
        
        <!-- Invitation Request Modal -->
        <div v-if="showInviteModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="fixed inset-0 bg-zinc-500 dark:bg-zinc-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" @click="showInviteModal = false"></div>

                <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div v-if="!inviteSuccess">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-orange-900/30">
                            <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg font-black leading-6 text-zinc-900 dark:text-white" id="modal-title">Request an Invitation</h3>
                            <div class="mt-2">
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">Request access and be among the first to transform your document management.</p>
                            </div>
                        </div>

                        <form @submit.prevent="requestInvite" class="mt-5 sm:mt-6">
                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Name</label>
                                    <input type="text" v-model="inviteForm.name" id="name" required class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="John Doe">
                                    <p v-if="inviteForm.errors.name" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.name }}</p>
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Email</label>
                                    <input type="email" v-model="inviteForm.email" id="email" required class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="john@example.com">
                                    <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.email }}</p>
                                </div>

                                <div>
                                    <label for="company" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Company (Optional)</label>
                                    <input type="text" v-model="inviteForm.company" id="company" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm" placeholder="Acme Inc.">
                                    <p v-if="inviteForm.errors.company" class="mt-1 text-sm text-red-600">{{ inviteForm.errors.company }}</p>
                                </div>
                            </div>

                            <div v-if="inviteForm.errors.message" class="mt-4 rounded-md bg-red-50 dark:bg-red-900/20 p-3">
                                <p class="text-sm text-red-800 dark:text-red-400">{{ inviteForm.errors.message }}</p>
                            </div>

                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                <button type="submit" :disabled="inviteForm.processing" class="inline-flex w-full justify-center rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 sm:col-start-2">
                                    {{ inviteForm.processing ? 'Submitting...' : 'Request Invitation' }}
                                </button>
                                <button type="button" @click="showInviteModal = false" class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-700 px-3 py-2 text-sm font-semibold text-zinc-900 dark:text-white shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 hover:bg-amber-50 dark:hover:bg-amber-600 sm:col-start-1 sm:mt-0">
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
                            <h3 class="text-lg font-black leading-6 text-zinc-900 dark:text-white">Success!</h3>
                            <div class="mt-2">
                                <p class="text-sm text-zinc-600 dark:text-zinc-300">We've received your invitation request. You'll hear from us soon!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
