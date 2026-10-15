<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import DocumentDrawer from '@/Components/Domain/DocumentDrawer.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';

const props = defineProps({ documents: { type: Object, required: true }, filters: { type: Object, default: () => ({}) } });
const { formatDate } = useDateFormatter();
const search = ref(props.filters.search || '');
const selectedDocument = ref(null);
const loading = ref(false);
const error = ref('');
watch(() => props.filters.search, value => { search.value = value || ''; });
function browse() {
    error.value = '';
    router.get(route('documents.shared'), { search: search.value || undefined }, {
        preserveState: true, preserveScroll: true,
        onStart: () => { loading.value = true; },
        onError: errors => { error.value = Object.values(errors).join(' '); },
        onFinish: () => { loading.value = false; },
    });
}
function formatFileSize(bytes) {
    if (bytes == null) return 'Size unavailable';
    if (bytes === 0) return '0 Bytes';
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), 3);
    return `${parseFloat((bytes / Math.pow(1024, index)).toFixed(2))} ${['Bytes', 'KB', 'MB', 'GB'][index]}`;
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Shared Documents" />
        <template #header><div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="page-heading">Shared Documents</h1><p class="page-description">Documents others have shared with you. Your access is shown beside each document.</p></div><Link :href="route('documents.index')" class="workspace-button">My documents</Link></div></template>
        <div class="flex min-w-0 flex-col gap-5">
            <form role="search" aria-label="Find shared documents" class="flex flex-wrap items-end gap-3" @submit.prevent="browse"><div class="min-w-0 flex-1 basis-48"><label for="shared-document-search" class="field-label mb-1">Search shared documents</label><input id="shared-document-search" v-model="search" type="search" class="workspace-filter w-full" placeholder="Title or document text" /></div><button type="submit" class="workspace-button" :disabled="loading">Search</button><Link v-if="filters.search" :href="route('documents.shared')" class="workspace-button">Clear search</Link></form>
            <p v-if="loading" role="status" class="text-sm text-zinc-500">Loading shared documents…</p><p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
            <section class="workspace-surface overflow-hidden" aria-label="Shared documents" :aria-busy="loading">
                <div v-if="!documents.data.length" class="workspace-empty"><h2 class="section-heading">{{ filters.search ? 'No shared documents match your search' : 'No shared documents yet' }}</h2><p>{{ filters.search ? 'Try another title or clear the search.' : 'Documents shared with your account will appear here.' }}</p><Link v-if="filters.search" :href="route('documents.shared')" class="workspace-button">Clear search</Link></div>
                <div v-else class="overflow-x-auto" tabindex="0" role="region" aria-label="Shared document table, scroll to see all columns">
                    <table class="w-full text-left text-sm"><caption class="sr-only">Shared documents with owner, category, permission and sharing date.</caption><thead class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950"><tr><th scope="col" class="px-4 py-3 font-medium">Document</th><th scope="col" class="px-4 py-3 font-medium">Shared by</th><th scope="col" class="px-4 py-3 font-medium">Category</th><th scope="col" class="px-4 py-3 font-medium">Access</th><th scope="col" class="px-4 py-3 font-medium">Shared on</th><th scope="col" class="px-4 py-3 font-medium">Actions</th></tr></thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800"><tr v-for="document in documents.data" :key="document.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50"><td class="min-w-[180px] max-w-sm px-4 py-4"><button type="button" class="max-w-full truncate text-left font-medium hover:underline" :dusk="`shared-document-preview-${document.id}`" @click="selectedDocument = document">{{ document.title }}</button><p class="mt-1 truncate text-xs text-zinc-500">{{ document.file_name }}</p><p class="mt-1 text-xs text-zinc-500">{{ formatFileSize(document.size) }}</p></td><td class="min-w-[160px] max-w-xs px-4 py-4"><p class="truncate">{{ document.owner?.name }}</p><p class="mt-1 truncate text-xs text-zinc-500">{{ document.owner?.email }}</p></td><td class="max-w-[180px] px-4 py-4 text-xs text-zinc-500 [overflow-wrap:anywhere]">{{ document.category?.name || '—' }}</td><td class="whitespace-nowrap px-4 py-4 text-xs text-zinc-500">{{ document.shared_permission === 'edit' ? 'Can edit' : 'View only' }}</td><td class="whitespace-nowrap px-4 py-4 text-xs text-zinc-500">{{ formatDate(document.shared_at) }}</td><td class="px-4 py-4"><div class="flex items-center gap-3"><Link :href="route('documents.show', document.id)" class="underline" :aria-label="`Open ${document.title}`">Open</Link><a :href="route('documents.download', document.id)" class="underline" :aria-label="`Download ${document.title}`">Download</a></div></td></tr></tbody>
                    </table>
                </div>
                <Pagination v-if="documents.links?.length > 3" :links="documents.links" :from="documents.from" :to="documents.to" :total="documents.total" />
            </section>
            <p v-if="documents.total" class="text-sm text-zinc-500" role="status">Showing {{ documents.from || 0 }} to {{ documents.to || 0 }} of {{ documents.total }} shared documents</p>
        </div>
        <DocumentDrawer :document="selectedDocument" :show="!!selectedDocument" @close="selectedDocument = null" />
    </AuthenticatedLayout>
</template>
