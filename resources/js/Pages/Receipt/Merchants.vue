<template>
    <Head :title="__('merchants')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('merchants') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <template v-if="merchants.length > 0">
                    <ul role="list" class="grid grid-cols-1 gap-x-6 gap-y-8 lg:grid-cols-3 xl:gap-x-8">
                        <li v-for="merchant in merchants" :key="merchant.id" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-x-4 border-b border-gray-900/5 bg-gray-50 dark:bg-gray-800 p-6">
                                <div class="flex-1 flex items-center min-w-0 gap-x-4">
                                    <img 
                                        :src="merchant.imageUrl" 
                                        :alt="merchant.name" 
                                        :class="[
                                            'rounded-lg bg-white dark:bg-gray-700 object-contain ring-1 ring-gray-900/10 dark:ring-gray-700',
                                            merchant.imageUrl.includes('ui-avatars.com') 
                                                ? 'h-12 w-12 flex-none' 
                                                : 'h-12 w-full flex-1'
                                        ]" 
                                    />
                                    <div 
                                        v-if="merchant.imageUrl.includes('ui-avatars.com')" 
                                        class="text-sm font-medium leading-6 text-gray-900 dark:text-gray-100 truncate"
                                    >
                                        {{ merchant.name }}
                                    </div>
                                    <div 
                                        v-else 
                                        class="sr-only"
                                    >
                                        {{ merchant.name }}
                                    </div>
                                </div>
                                <Menu as="div" class="relative flex-none">
                                    <MenuButton class="-m-2.5 block p-2.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                                        <span class="sr-only">{{ __('open_options') }}</span>
                                        <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
                                    </MenuButton>
                                    <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                        <MenuItems class="absolute right-0 z-10 mt-0.5 w-32 origin-top-right rounded-md bg-white dark:bg-gray-800 py-2 shadow-lg ring-1 ring-gray-900/5 dark:ring-gray-700 focus:outline-none">
                                            <MenuItem v-slot="{ active }">
                                                <Link :href="route('receipts.byMerchant', merchant.id)" :class="[active ? 'bg-gray-50 dark:bg-gray-700' : '', 'block px-3 py-1 text-sm leading-6 text-gray-900 dark:text-gray-100']">
                                                    {{ __('view_receipts') }}<span class="sr-only">, {{ merchant.name }}</span>
                                                </Link>
                                            </MenuItem>
                                            <MenuItem v-slot="{ active }">
                                                <button @click="openLogoModal(merchant)" :class="[active ? 'bg-gray-50 dark:bg-gray-700' : '', 'block w-full px-3 py-1 text-left text-sm leading-6 text-gray-900 dark:text-gray-100']">
                                                    {{ __('update_logo') }}<span class="sr-only">, {{ merchant.name }}</span>
                                                </button>
                                            </MenuItem>
                                        </MenuItems>
                                    </transition>
                                </Menu>
                            </div>
                            <dl class="-my-3 divide-y divide-gray-100 dark:divide-gray-700 px-6 py-4 text-sm leading-6">
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('last_receipt') }}</dt>
                                    <dd class="text-gray-700 dark:text-gray-300">
                                        <time :datetime="merchant.lastInvoice.dateTime">{{ merchant.lastInvoice.date }}</time>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('total_amount') }}</dt>
                                    <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ merchant.lastInvoice.amount }}</dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <div class="bg-gray-900 px-6 py-24 sm:py-32 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <p class="text-base/7 font-semibold text-indigo-600">{{ __('no_merchants_found') }}</p>
                            <h2 class="text-5xl font-semibold tracking-tight text-white sm:text-7xl">{{ __('upload_first_merchants') }}</h2>
                            <p class="mt-6 text-lg leading-8 text-gray-300">{{ __('merchants_appear_description') }}</p>
                            <Link :href="route('documents.upload')" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                {{ __('upload_receipts') }}
                            </Link>
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
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
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
                            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                                <div>
                                    <div class="mt-3 text-center sm:mt-5">
                                        <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">
                                            {{ __('merchant_logo_upload') }}
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Velg en ny logo for denne butikken. Støttede formater er JPEG, PNG, og GIF.
                                            </p>
                                        </div>
                                    </div>
                                    <form @submit.prevent="submitLogo" class="mt-5 sm:mt-6">
                                        <div class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 dark:border-gray-700 px-6 py-10">
                                            <div class="text-center">
                                                <template v-if="!selectedFile">
                                                    <PhotoIcon class="mx-auto h-12 w-12 text-gray-300" aria-hidden="true" />
                                                    <div class="mt-4 flex text-sm leading-6 text-gray-600 dark:text-gray-400">
                                                        <label
                                                            for="logo-upload"
                                                            class="relative cursor-pointer rounded-md bg-white dark:bg-gray-800 font-semibold text-indigo-600 dark:text-indigo-400 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500"
                                                        >
                                                            <span>{{ __('upload_file') }}</span>
                                                            <input @change="handleFileSelect" id="logo-upload" name="logo-upload" type="file" class="sr-only" accept="image/*" />
                                                        </label>
                                                        <p class="pl-1">{{ __('drag_drop') }}</p>
                                                    </div>
                                                    <p class="text-xs leading-5 text-gray-600 dark:text-gray-400">{{ __('supported_formats') }}</p>
                                                </template>
                                                <template v-else>
                                                    <div class="flex flex-col items-center">
                                                        <div class="relative">
                                                            <img 
                                                                :src="selectedFilePreview" 
                                                                class="h-32 w-32 object-contain rounded-lg border border-gray-200 dark:border-gray-700"
                                                                alt="Logo preview"
                                                            />
                                                            <button 
                                                                @click.prevent="clearSelectedFile" 
                                                                class="absolute -top-2 -right-2 rounded-full bg-red-100 dark:bg-red-900 p-1 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-800"
                                                            >
                                                                <span class="sr-only">{{ __('remove_file') }}</span>
                                                                <XMarkIcon class="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                        <span class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ selectedFile.name }}</span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                                            <button
                                                type="submit"
                                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                                :disabled="!selectedFile"
                                            >
                                                {{ __('upload') }}
                                            </button>
                                            <button
                                                type="button"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 sm:col-start-1 sm:mt-0"
                                                @click="closeLogoModal"
                                            >
                                                {{ __('cancel') }}
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
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems, Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { EllipsisHorizontalIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { ref } from 'vue'

defineProps({
    merchants: {
        type: Array,
        required: true
    }
});

const page = usePage();
const __ = (key) => {
  const messages = page.props.language?.messages || {};
  const parts = key.split('.');
  let value = messages;
  
  for (const part of parts) {
    value = value?.[part];
    if (value === undefined) break;
  }
  
  return value || key.split('.').pop();
};

const isLogoModalOpen = ref(false)
const selectedMerchant = ref(null)
const selectedFile = ref(null)
const selectedFilePreview = ref(null)

const openLogoModal = (merchant) => {
    selectedMerchant.value = merchant
    isLogoModalOpen.value = true
}

const handleFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        const reader = new FileReader()
        reader.onload = (e) => {
            selectedFilePreview.value = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const clearSelectedFile = () => {
    selectedFile.value = null
    selectedFilePreview.value = null
}

const closeLogoModal = () => {
    isLogoModalOpen.value = false
    selectedMerchant.value = null
    selectedFile.value = null
    selectedFilePreview.value = null
}

const submitLogo = () => {
    if (!selectedFile.value || !selectedMerchant.value) return

    const form = new FormData()
    form.append('logo', selectedFile.value)

    router.post(route('merchants.updateLogo', selectedMerchant.value.id), form, {
        onSuccess: () => {
            closeLogoModal()
        },
    })
}
</script> 