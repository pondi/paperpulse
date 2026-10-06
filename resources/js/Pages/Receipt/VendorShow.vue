<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

defineProps({ vendor: Object, items: Object });
const { formatDate, formatCurrency } = useDateFormatter();
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="vendor.name" />
        <template #header>
            <h2 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ vendor.name }}</h2>
        </template>
        <div class="mx-auto flex max-w-7xl flex-col gap-6 p-6 text-zinc-900 dark:text-zinc-100">
            <Link :href="route('vendors.index')" class="text-amber-700 hover:underline dark:text-amber-400">All vendors</Link>
            <section class="flex flex-col gap-3 rounded-lg bg-white p-6 shadow dark:bg-zinc-900">
                <h3 class="text-lg font-bold">Vendor details</h3>
                <p v-if="vendor.description">{{ vendor.description }}</p>
                <p v-if="vendor.website" class="break-all">Website: {{ vendor.website }}</p>
                <p v-if="vendor.contact_email" class="break-all">Email: {{ vendor.contact_email }}</p>
                <p v-if="vendor.contact_phone">Phone: {{ vendor.contact_phone }}</p>
            </section>
            <section class="flex flex-col gap-4 rounded-lg bg-white p-6 shadow dark:bg-zinc-900">
                <h3 class="text-lg font-bold">Purchased items ({{ items.total }})</h3>
                <ul class="flex flex-col gap-4">
                    <li v-for="item in items.data" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 pb-4 dark:border-zinc-700">
                        <div class="min-w-0">
                            <p class="break-words font-semibold">{{ item.text || 'Unnamed item' }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ item.qty ?? '—' }} × {{ item.price == null ? '—' : formatCurrency(item.price, item.currency) }}</p>
                        </div>
                        <Link :href="route('receipts.show', item.receipt_id)" class="text-sm text-amber-700 hover:underline dark:text-amber-400">{{ item.merchant || `Receipt #${item.receipt_id}` }} · {{ formatDate(item.receipt_date) }}</Link>
                    </li>
                </ul>
                <Pagination :links="items.links" :from="items.from" :to="items.to" :total="items.total" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
