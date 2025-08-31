<template>
    <div class="flex items-center gap-x-3">
        <button
            @click="exportCsv"
            :disabled="exporting"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
            <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export CSV
        </button>
        <button
            @click="exportPdf"
            :disabled="exporting"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
        >
            <svg class="-ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            Export PDF
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({})
    }
});

const exporting = ref(false);

const buildQueryString = () => {
    const params = new URLSearchParams();
    Object.keys(props.filters).forEach(key => {
        if (props.filters[key]) {
            params.append(key, props.filters[key]);
        }
    });
    return params.toString();
};

const exportCsv = () => {
    exporting.value = true;
    const queryString = buildQueryString();
    const url = route('export.receipts.csv') + (queryString ? '?' + queryString : '');
    
    window.location.href = url;
    
    setTimeout(() => {
        exporting.value = false;
    }, 2000);
};

const exportPdf = () => {
    exporting.value = true;
    const queryString = buildQueryString();
    const url = route('export.receipts.pdf') + (queryString ? '?' + queryString : '');
    
    window.location.href = url;
    
    setTimeout(() => {
        exporting.value = false;
    }, 2000);
};
</script>