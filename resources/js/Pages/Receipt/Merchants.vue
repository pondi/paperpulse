<template>
    <Head title="Merchants" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">Merchants</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <template v-if="merchants.length > 0">
                    <ul role="list" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <li v-for="merchant in merchants" :key="merchant.id" class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 rounded-xl border-t-4 border-amber-600 dark:border-amber-500">
                            <div class="flex items-center gap-x-4 border-b border-amber-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                                <div class="flex-1 flex items-center min-w-0 gap-x-4">
                                    <img 
                                        :src="merchant.imageUrl" 
                                        :alt="merchant.name" 
                                        :class="[
                                            'rounded-lg bg-white dark:bg-zinc-700 object-contain ring-1 ring-zinc-900/10 dark:ring-zinc-700',
                                            merchant.imageUrl.includes('/merchants/') || merchant.imageUrl.includes('/logo/generate/')
                                                ? 'h-12 w-12 flex-none' 
                                                : 'h-12 w-full flex-1'
                                        ]" 
                                    />
                                    <div
                                        v-if="merchant.imageUrl.includes('/merchants/') || merchant.imageUrl.includes('/logo/generate/')"
                                        class="text-sm font-bold leading-6 text-zinc-900 dark:text-zinc-100 truncate"
                                    >
                                        {{ merchant.name }}
                                    </div>
                                    <div v-else class="sr-only">
                                        {{ merchant.name }}
                                    </div>
                                </div>
                                <Menu as="div" class="relative flex-none">
                                    <MenuButton class="-m-2.5 block p-2.5 text-zinc-400 hover:text-zinc-500 dark:hover:text-zinc-300">
                                        <span class="sr-only">Open options</span>
                                        <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
                                    </MenuButton>
                                    <transition 
                                        enter-active-class="transition ease-out duration-100" 
                                        enter-from-class="transform opacity-0 scale-95" 
                                        enter-to-class="transform opacity-100 scale-100" 
                                        leave-active-class="transition ease-in duration-75" 
                                        leave-from-class="transform opacity-100 scale-100" 
                                        leave-to-class="transform opacity-0 scale-95"
                                    >
                                        <MenuItems class="absolute right-0 z-10 mt-0.5 w-32 origin-top-right rounded-md bg-white dark:bg-zinc-800 py-2 shadow-lg ring-1 ring-zinc-900/5 dark:ring-zinc-700 focus:outline-none">
                                            <MenuItem v-slot="{ active }">
                                                <Link 
                                                    :href="route('receipts.byMerchant', merchant.id)" 
                                                    :class="[active ? 'bg-amber-50 dark:bg-zinc-700' : '', 'block px-3 py-1 text-sm leading-6 text-zinc-900 dark:text-zinc-100']"
                                                >
                                                    View Receipts<span class="sr-only">, {{ merchant.name }}</span>
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <button 
                                                    @click="openLogoModal(merchant)" 
                                                    :class="[active ? 'bg-amber-50 dark:bg-zinc-700' : '', 'block w-full px-3 py-1 text-left text-sm leading-6 text-zinc-900 dark:text-zinc-100']"
                                                >
                                                    Update Logo<span class="sr-only">, {{ merchant.name }}</span>
                                                </button>
                                            </MenuItem>
                                        </MenuItems>
                                    </transition>
                                </Menu>
                            </div>
                            <dl class="-my-3 divide-y divide-amber-200 dark:divide-zinc-700 px-6 py-4 text-sm leading-6 bg-white dark:bg-zinc-900">
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Last Receipt</dt>
                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                        <time :datetime="merchant.lastInvoice.dateTime">{{ merchant.lastInvoice.date }}</time>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Total Amount</dt>
                                    <dd class="font-black text-zinc-900 dark:text-zinc-100">{{ merchant.lastInvoice.amount }}</dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-amber-600 dark:border-amber-500">
                        <div class="px-6 py-24 sm:py-32 lg:px-8">
                            <div class="mx-auto max-w-2xl text-center">
                                <p class="text-base/7 font-bold text-amber-600 dark:text-amber-500 uppercase tracking-wider">No merchants found</p>
                                <h2 class="mt-2 text-4xl font-black tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-5xl">Upload your first receipt</h2>
                                <p class="mt-6 text-lg leading-8 text-zinc-600 dark:text-zinc-400">Merchants will appear here after you upload your first receipt.</p>
                                <div class="mt-10">
                                    <Link
                                        :href="route('documents.upload')"
                                        class="inline-flex items-center rounded-md bg-zinc-900 dark:bg-amber-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 transition-all duration-200"
                                    >
                                        Upload Receipts
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Logo Upload Modal -->
        <TransitionRoot appear :show="isLogoModalOpen" as="template">
            <Dialog as="div" @close="closeLogoModal" class="relative z-10">
                <TransitionChild
                    as="template"
                    enter="duration-300 ease-out"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="duration-200 ease-in"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" />
                </TransitionChild>

                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <TransitionChild
                            as="template"
                            enter="duration-300 ease-out"
                            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enter-to="opacity-100 translate-y-0 sm:scale-100"
                            leave="duration-200 ease-in"
                            leave-from="opacity-100 translate-y-0 sm:scale-100"
                            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        >
                            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-zinc-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                                <div>
                                    <div class="mt-3 text-center sm:mt-5">
                                        <DialogTitle as="h3" class="text-lg font-black leading-6 text-zinc-900 dark:text-zinc-100">
                                            Update Merchant Logo
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                                Upload a new logo for this merchant. The logo will be displayed on all receipts and in the merchant list.
                                            </p>
                                        </div>
                                    </div>
                                    <form @submit.prevent="submitLogo" class="mt-5 sm:mt-6">
                                        <div class="mt-2 flex justify-center rounded-lg border border-dashed border-zinc-900/25 dark:border-zinc-700 px-6 py-10">
                                            <div class="text-center">
                                                <template v-if="!selectedFile">
                                                    <PhotoIcon class="mx-auto h-12 w-12 text-zinc-300" aria-hidden="true" />
                                                    <div class="mt-4 flex text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                                        <label
                                                            for="logo-upload"
                                                            class="relative cursor-pointer rounded-md bg-white dark:bg-zinc-800 font-semibold text-amber-600 dark:text-amber-400 focus-within:outline-none focus-within:ring-2 focus-within:ring-amber-600 focus-within:ring-offset-2 hover:text-amber-500"
                                                        >
                                                            <span>Upload a file</span>
                                                            <input 
                                                                @change="handleFileSelect" 
                                                                id="logo-upload" 
                                                                name="logo-upload" 
                                                                type="file" 
                                                                class="sr-only" 
                                                                accept="image/*" 
                                                            />
                                                        </label>
                                                        <p class="pl-1">or drag and drop</p>
                                                    </div>
                                                    <p class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">PNG, JPG, GIF up to 2MB</p>
                                                </template>
                                                <template v-else>
                                                    <div class="flex flex-col items-center">
                                                        <div class="relative">
                                                            <img 
                                                                :src="selectedFilePreview" 
                                                                class="h-32 w-32 object-contain rounded-lg border border-amber-200 dark:border-zinc-700"
                                                                alt="Logo preview"
                                                            />
                                                            <button 
                                                                @click.prevent="clearSelectedFile" 
                                                                class="absolute -top-2 -right-2 rounded-full bg-red-100 dark:bg-red-900 p-1 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-800"
                                                            >
                                                                <span class="sr-only">Remove file</span>
                                                                <XMarkIcon class="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                        <span class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ selectedFile.name }}</span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                            <button
                                                type="submit"
                                                class="inline-flex w-full justify-center rounded-md bg-zinc-900 dark:bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 dark:hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 sm:col-start-2"
                                                :disabled="!selectedFile"
                                            >
                                                Upload
                                            </button>
                                            <button
                                                type="button"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-zinc-800 px-3 py-2 text-sm font-bold text-zinc-900 dark:text-zinc-100 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-700 hover:bg-amber-50 dark:hover:bg-zinc-700 sm:col-start-1 sm:mt-0"
                                                @click="closeLogoModal"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems, Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { EllipsisHorizontalIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { ref } from 'vue'

defineProps({
    merchants: {
        type: Array,
        required: true
    }
});

const isLogoModalOpen = ref(false);
const selectedMerchant = ref(null);
const selectedFile = ref(null);
const selectedFilePreview = ref(null);

const openLogoModal = (merchant) => {
    selectedMerchant.value = merchant;
    isLogoModalOpen.value = true;
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            selectedFilePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    selectedFilePreview.value = null;
};

const closeLogoModal = () => {
    isLogoModalOpen.value = false;
    selectedMerchant.value = null;
    clearSelectedFile();
};

const submitLogo = () => {
    if (!selectedFile.value || !selectedMerchant.value) return;

    const form = new FormData();
    form.append('logo', selectedFile.value);

    router.post(route('merchants.updateLogo', selectedMerchant.value.id), form, {
        onSuccess: () => {
            closeLogoModal();
        },
    });
};
</script> 
