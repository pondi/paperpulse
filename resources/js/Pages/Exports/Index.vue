<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ exports: Array });
const exports = ref(props.exports);
const error = ref('');
let timer;
let loading = false;

function formatExpiration(date) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date));
}

onMounted(() => {
    timer = setInterval(async () => {
        if (loading) return;
        if (!exports.value.some(item => ['pending', 'processing'].includes(item.status))) {
            clearInterval(timer);
            return;
        }
        loading = true;
        try {
            const response = await axios.get(route('exports.index'), { headers: { Accept: 'application/json' } });
            exports.value = response.data.exports;
            error.value = '';
        } catch {
            error.value = 'Could not refresh exports. Retrying shortly.';
        } finally {
            loading = false;
        }
    }, 5000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div>
        <Head title="Exports" />
        <AuthenticatedLayout>
            <template #header><h2 class="text-xl font-semibold text-zinc-800 dark:text-zinc-200">Exports</h2></template>
            <div class="mx-auto flex max-w-5xl flex-col gap-4 px-4 py-8">
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Downloads expire after 24 hours.</p>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">To create an export, open Receipts, select the receipts using their checkboxes, then choose Export as CSV or PDF.</p>
                <Link :href="route('receipts.index')" class="self-start rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Select receipts to export</Link>
                <p v-if="error" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</p>
                <section aria-labelledby="export-history-heading" class="mt-4 flex flex-col gap-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 id="export-history-heading" class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Export history</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ exports.length }} {{ exports.length === 1 ? 'export' : 'exports' }}</p>
                    </div>
                    <p v-if="!exports.length" class="rounded-lg border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">No exports available. Completed downloads will appear here and stay available for 24 hours.</p>
                    <article v-for="item in exports" :key="item.id" class="flex flex-col gap-2 rounded-lg bg-white p-4 shadow-sm dark:bg-zinc-900 dark:text-zinc-200">
                        <p class="font-medium" :role="['pending', 'processing'].includes(item.status) ? 'status' : null">{{ item.format.toUpperCase() }} export · {{ item.status }}</p>
                        <template v-if="['pending', 'processing'].includes(item.status)">
                            <progress class="w-full" :max="item.total || 1" :value="item.processed" :aria-label="`${item.format.toUpperCase()} export progress`" />
                            <p class="text-sm">{{ item.processed }} / {{ item.total }} processed</p>
                        </template>
                        <p v-if="item.status === 'expired'" class="text-sm">This download expired<span v-if="item.expires_at"> on {{ formatExpiration(item.expires_at) }}</span>. Select the receipts again to create a new export.</p>
                        <p v-if="item.download_url" class="text-sm text-zinc-600 dark:text-zinc-400">Ready to download until {{ formatExpiration(item.expires_at) }}.</p>
                        <p v-if="item.error" role="alert" class="text-red-600 dark:text-red-400">{{ item.error }}</p>
                        <a v-if="item.download_url" :href="item.download_url" class="inline-flex min-h-11 self-start items-center rounded-sm font-semibold text-amber-700 underline focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:text-amber-400">Download {{ item.format.toUpperCase() }} export</a>
                    </article>
                </section>
            </div>
        </AuthenticatedLayout>
    </div>
</template>
