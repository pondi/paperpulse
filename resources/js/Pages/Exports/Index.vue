<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ exports: Array });
const exports = ref(props.exports);
const error = ref('');
let timer;
let loading = false;

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
                <p v-if="error" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</p>
                <p v-if="!exports.length" class="text-zinc-600 dark:text-zinc-300">No exports available.</p>
                <div v-for="item in exports" :key="item.id" class="flex flex-col gap-2 rounded-lg bg-white p-4 shadow dark:bg-zinc-900 dark:text-zinc-200">
                    <p class="font-medium">{{ item.format.toUpperCase() }} export · {{ item.status }}</p>
                    <template v-if="['pending', 'processing'].includes(item.status)">
                        <progress class="w-full" :max="item.total" :value="item.processed" />
                        <p class="text-sm">{{ item.processed }} / {{ item.total }} processed</p>
                    </template>
                    <p v-if="item.error" role="alert" class="text-red-600 dark:text-red-400">{{ item.error }}</p>
                    <a v-if="item.download_url" :href="item.download_url" class="text-amber-700 underline dark:text-amber-400">Download {{ item.format.toUpperCase() }}</a>
                </div>
            </div>
        </AuthenticatedLayout>
    </div>
</template>
