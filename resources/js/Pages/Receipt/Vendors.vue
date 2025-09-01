<template>
    <Head :title="__('product_vendors')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('product_vendors') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <template v-if="vendors.length > 0">
                    <ul role="list" class="grid grid-cols-1 gap-x-6 gap-y-8 lg:grid-cols-3 xl:gap-x-8">
                        <li v-for="vendor in vendors" :key="vendor.id" class="overflow-hidden rounded-xl border border-gray-200">
                            <div class="flex items-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6">
                                <img :src="vendor.imageUrl" :alt="vendor.name" class="h-12 w-12 flex-none rounded-lg bg-white object-cover ring-1 ring-gray-900/10" />
                                <div class="text-sm font-medium leading-6 text-gray-900">{{ vendor.name }}</div>
                                <Menu as="div" class="relative ml-auto">
                                    <MenuButton class="-m-2.5 block p-2.5 text-gray-400 hover:text-gray-500">
                                        <span class="sr-only">{{ __('open_options') }}</span>
                                        <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
                                    </MenuButton>
                                    <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                        <MenuItems class="absolute right-0 z-10 mt-0.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none">
                                            <MenuItem v-slot="{ active }">
                                                <Link :href="route('vendors.show', vendor.id)" :class="[active ? 'bg-gray-50' : '', 'block px-3 py-1 text-sm leading-6 text-gray-900']">
                                                    {{ __('view_details') }}<span class="sr-only">, {{ vendor.name }}</span>
                                                </Link>
                                            </MenuItem>
                                        </MenuItems>
                                    </transition>
                                </Menu>
                            </div>
                            <dl class="-my-3 divide-y divide-gray-100 px-6 py-4 text-sm leading-6">
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500">{{ __('last_item') }}</dt>
                                    <dd class="text-gray-700">
                                        <time :datetime="vendor.stats.dateTime">{{ vendor.stats.date }}</time>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500">{{ __('total_items') }}</dt>
                                    <dd class="text-gray-700">{{ vendor.stats.totalItems }}</dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500">{{ __('total_value') }}</dt>
                                    <dd class="flex items-start gap-x-2">
                                        <div class="font-medium text-gray-900">{{ vendor.stats.totalValue }}</div>
                                        <div :class="[statuses[vendor.stats.status], 'rounded-md py-1 px-2 text-xs font-medium ring-1 ring-inset']">
                                            {{ vendor.stats.status }}
                                        </div>
                                    </dd>
                                </div>
                                <div v-if="vendor.website" class="flex justify-between gap-x-4 py-3">
                                    <dt class="text-gray-500">{{ __('website') }}</dt>
                                    <dd class="text-gray-700">
                                        <a :href="vendor.website" target="_blank" class="text-indigo-600 hover:text-indigo-500">{{ __('visit_site') }}</a>
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-24 sm:py-32 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <p class="text-base/7 font-semibold text-indigo-600">{{ __('no_vendors_found') }}</p>
                            <h2 class="text-5xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-7xl">{{ __('add_products_from_vendors') }}</h2>
                            <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">{{ __('no_vendors_description') }}</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { EllipsisHorizontalIcon } from '@heroicons/vue/20/solid'
import { usePage } from '@inertiajs/vue3';

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

defineProps({
    vendors: {
        type: Array,
        required: true
    }
});

const statuses = {
    'Active': 'text-green-700 bg-green-50 ring-green-600/20',
    'No items': 'text-gray-600 bg-gray-50 ring-gray-500/10'
}
</script>
