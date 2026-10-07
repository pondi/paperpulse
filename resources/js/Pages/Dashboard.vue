<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileWorkspace from '@/Components/Domain/FileWorkspace.vue';
import { Head, Link } from '@inertiajs/vue3';
const props = defineProps({
    archiveStats: { type: Object, default: () => ({ total: 0, processing: 0, failed: 0, needs_review: 0 }) },
    recentUploads: { type: Array, default: () => [] },
    expiringVouchers: { type: Object, default: () => ({ total: 0 }) },
    endingWarranties: { type: Object, default: () => ({ total: 0 }) },
    workspaceTags: { type: Array, default: () => [] },
});
const queues = [
    { label: 'All documents', key: 'total', view: 'all' },
    { label: 'Processing', key: 'processing', view: 'processing' },
    { label: 'Needs review', key: 'needs_review', view: 'needs-review' },
    { label: 'Failed', key: 'failed', view: 'needs-review', status: 'failed' },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Home" />
        <template #header><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold tracking-tight">Home</h1><p class="mt-1 text-sm text-zinc-500">Your document queue. Review what needs attention, then keep moving.</p></div><Link :href="route('library.index', { view: 'needs-review' })" class="workspace-primary">Review queue <span class="tabular-nums">{{ archiveStats.needs_review + archiveStats.failed }}</span></Link></div></template>
        <div class="flex flex-col gap-4">
            <nav aria-label="Archive overview" class="flex flex-wrap items-center divide-x divide-zinc-200 border-y border-zinc-200 bg-white py-3 dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                <Link v-for="queue in queues" :key="queue.key" :href="route('library.index', { view: queue.view, status: queue.status })" class="flex items-baseline gap-3 px-5 py-1 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100"><span class="text-xl font-semibold tabular-nums text-zinc-800 dark:text-zinc-100">{{ archiveStats[queue.key] }}</span>{{ queue.label }}</Link>
            </nav>
            <div v-if="expiringVouchers.total || endingWarranties.total" class="flex items-center justify-between gap-3 border-b border-zinc-200 pb-3 text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400"><span>{{ expiringVouchers.total }} vouchers and {{ endingWarranties.total }} warranties expire within 30 days.</span><Link :href="route('library.index', { view: 'expiring' })" class="font-medium underline">Review upcoming expirations</Link></div>
            <div class="flex items-center justify-between"><h2 class="text-sm font-semibold">Recent uploads</h2><Link :href="route('library.index')" class="text-xs text-zinc-500 underline">Open Library →</Link></div>
            <FileWorkspace :files="recentUploads" :tags="workspaceTags" />
        </div>
    </AuthenticatedLayout>
</template>
