<template>
    <Head :title="__('product_vendors')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">{{ __('product_vendors') }}</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <template v-if="vendors.length > 0">
                    <ul role="list" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <li v-for="vendor in vendors" :key="vendor.id" class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-200 rounded-xl border-t-4 border-orange-600 dark:border-orange-500">
                            <div class="flex items-center gap-x-4 border-b border-amber-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                                <img :src="vendor.imageUrl" :alt="vendor.name" class="h-12 w-12 flex-none rounded-lg bg-white dark:bg-zinc-700 object-cover ring-1 ring-zinc-900/10 dark:ring-zinc-700" />
                                <div class="text-sm font-bold leading-6 text-zinc-900 dark:text-zinc-100">{{ vendor.name }}</div>
                                <Menu as="div" class="relative ml-auto">
                                    <MenuButton class="-m-2.5 block p-2.5 text-zinc-400 hover:text-zinc-500 dark:hover:text-zinc-300">
                                        <span class="sr-only">{{ __('open_options') }}</span>
                                        <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
                                    </MenuButton>
                                    <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                        <MenuItems class="absolute right-0 z-10 mt-0.5 w-32 origin-top-right rounded-md bg-white dark:bg-zinc-800 py-2 shadow-lg ring-1 ring-zinc-900/5 dark:ring-zinc-700 focus:outline-none">
                                            <MenuItem v-slot="{ active }">
                                                <Link :href="route('vendors.show', vendor.id)" :class="[active ? 'bg-amber-50 dark:bg-zinc-700' : '', 'block px-3 py-1 text-sm leading-6 text-zinc-900 dark:text-zinc-100']">
                                                    {{ __('view_details') }}<span class="sr-only">, {{ vendor.name }}</span>
                                                </Link>
                                            </MenuItem>
                                        </MenuItems>
                                    </transition>
                                </Menu>
                            </div>
                            <dl class="-my-3 divide-y divide-amber-200 dark:divide-zinc-700 px-6 py-4 text-sm leading-6 bg-white dark:bg-zinc-900">
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('last_item') }}</dt>
                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                        <time :datetime="vendor.stats.dateTime">{{ vendor.stats.date }}</time>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('total_items') }}</dt>
                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ vendor.stats.totalItems }}</dd>
                                </div>
                                <div class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('total_value') }}</dt>
                                    <dd class="flex items-start gap-x-2">
                                        <div class="font-black text-zinc-900 dark:text-zinc-100">{{ vendor.stats.totalValue }}</div>
                                        <div :class="[statuses[vendor.stats.status], 'rounded-md py-1 px-2 text-xs font-medium ring-1 ring-inset']">
                                            {{ vendor.stats.status }}
                                        </div>
                                    </dd>
                                </div>
                                <div v-if="vendor.website" class="flex justify-between gap-x-4 py-3">
                                    <dt class="font-bold text-xs uppercase tracking-wider text-zinc-600 dark:text-zinc-400">{{ __('website') }}</dt>
                                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">
                                        <a :href="vendor.website" target="_blank" class="text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400">{{ __('visit_site') }}</a>
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </ul>
                </template>
                <template v-else>
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-orange-600 dark:border-orange-500">
                        <div class="px-6 py-24 sm:py-32 lg:px-8">
                            <div class="mx-auto max-w-2xl text-center">
                                <p class="text-base/7 font-bold text-orange-600 dark:text-orange-500 uppercase tracking-wider">{{ __('no_vendors_found') }}</p>
                                <h2 class="mt-2 text-4xl font-black tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-5xl">{{ __('add_products_from_vendors') }}</h2>
                                <p class="mt-6 text-lg leading-8 text-zinc-600 dark:text-zinc-400">{{ __('no_vendors_description') }}</p>
                            </div>
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
    'No items': 'text-zinc-600 bg-amber-50 ring-zinc-500/10'
}
</script>
