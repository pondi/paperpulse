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
                    
                    <div class="flex items-center space-x-6">
                        <!-- GitHub Icon -->
                        <a href="https://github.com/pondi/paperpulse" target="_blank" class="text-gray-600 hover:text-gray-900 transition-colors duration-200" aria-label="View on GitHub">
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
                        <button @click="showBetaModal = true" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 px-8 py-4 text-lg font-semibold text-white shadow-xl hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                            Request Beta Access
                            <svg class="ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                        
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

            <!-- How It Works Section -->
            <section class="px-6 py-24 bg-white lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center mb-20">
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                            How PaperPulse Works
                        </h2>
                        <p class="mt-6 text-xl text-gray-600 max-w-2xl mx-auto">
                            From chaos to clarity in three simple steps
                        </p>
                    </div>
                    
                    <div class="grid gap-8 lg:grid-cols-3">
                        <div class="relative">
                            <div class="flex items-center mb-6">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-600 font-bold text-xl">
                                    1
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-semibold text-gray-900">Upload Documents</h3>
                                </div>
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Snap a photo, scan, or upload PDFs directly. PaperPulse accepts receipts, invoices, contracts, warranties, and any document you need to keep track of.
                            </p>
                        </div>
                        
                        <div class="relative">
                            <div class="flex items-center mb-6">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 text-purple-600 font-bold text-xl">
                                    2
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-semibold text-gray-900">AI Extraction</h3>
                                </div>
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Our AI instantly reads and extracts all important information: dates, amounts, vendors, line items, tax details, and more. No manual data entry needed.
                            </p>
                        </div>
                        
                        <div class="relative">
                            <div class="flex items-center mb-6">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600 font-bold text-xl">
                                    3
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-semibold text-gray-900">Smart Organization</h3>
                                </div>
                            </div>
                            <p class="text-gray-600 leading-relaxed">
                                Everything is automatically categorized, tagged, and made searchable. Find any document in seconds, export for taxes, or track spending patterns.
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
        
        <!-- Beta Invite Modal -->
        <div v-if="showBetaModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showBetaModal = false"></div>
                
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div v-if="!betaSuccess">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100">
                            <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Request Beta Access</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Join the exclusive beta and be among the first to transform your document management.</p>
                            </div>
                        </div>
                        
                        <form @submit.prevent="requestBetaInvite" class="mt-5 sm:mt-6">
                            <div class="space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" v-model="betaName" id="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="John Doe">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" v-model="betaEmail" id="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="john@example.com">
                                </div>
                                
                                <div>
                                    <label for="company" class="block text-sm font-medium text-gray-700">Company (Optional)</label>
                                    <input type="text" v-model="betaCompany" id="company" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" placeholder="Acme Inc.">
                                </div>
                            </div>
                            
                            <div v-if="betaError" class="mt-4 rounded-md bg-red-50 p-3">
                                <p class="text-sm text-red-800">{{ betaError }}</p>
                            </div>
                            
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                <button type="submit" :disabled="betaSubmitting" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 sm:col-start-2">
                                    {{ betaSubmitting ? 'Submitting...' : 'Request Invite' }}
                                </button>
                                <button type="button" @click="showBetaModal = false" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div v-else class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Success!</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">We've received your beta request. You'll hear from us soon!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
