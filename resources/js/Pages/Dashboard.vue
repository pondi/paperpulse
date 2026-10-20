<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRightIcon, ArrowUpTrayIcon, CheckCircleIcon, ClockIcon, DocumentTextIcon, ExclamationCircleIcon, FolderIcon, ChartBarIcon, UsersIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileWorkspace from '@/Components/Domain/FileWorkspace.vue';

const props = defineProps({
    archiveStats: { type: Object, default: () => ({ total: 0, processing: 0, failed: 0, needs_review: 0 }) },
    workspaceTags: { type: Array, default: () => [] },
    recentUploads: { type: Array, default: () => [] },
    expiringVouchers: { type: Object, default: () => ({ total: 0, items: [] }) },
    endingWarranties: { type: Object, default: () => ({ total: 0, items: [] }) },
});
const attentionCount = computed(() => Number(props.archiveStats.needs_review) + Number(props.archiveStats.failed));
const shortcuts = [
    { label: 'Collections', description: 'Browse your folders and projects', icon: FolderIcon, href: route('collections.index') },
    { label: 'Reports', description: 'Understand spending and activity', icon: ChartBarIcon, href: route('analytics.index') },
    { label: 'Shared with me', description: 'Documents shared by others', icon: UsersIcon, href: route('library.index', { view: 'shared' }) },
];
</script>

<template>
    <AuthenticatedLayout framed>
        <Head title="Home" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h1 class="page-heading">Home</h1><p class="page-description">Pick up where you left off, or see what needs your attention.</p></div>
                <Link :href="route('library.index')" class="workspace-button">Open library <ArrowRightIcon class="h-4 w-4" aria-hidden="true" /></Link>
            </div>
        </template>

        <div class="workspace-stack">
            <section v-if="!archiveStats.total" class="workspace-surface flex flex-col items-start gap-3 p-4" aria-labelledby="welcome-heading">
                <DocumentTextIcon class="h-8 w-8 text-orange-700 dark:text-orange-400" aria-hidden="true" />
                <div><h2 id="welcome-heading" class="text-xl">Your documents, in one place</h2><p class="page-description">Add a receipt, invoice or document. PaperPulse extracts the details so you can find them again.</p></div>
                <div class="flex flex-wrap items-center gap-4"><Link :href="route('documents.upload')" class="workspace-primary"><ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true" />Upload your first files</Link><Link :href="route('scanner')" class="text-sm font-medium text-zinc-600 hover:text-orange-700 dark:text-zinc-300">Scan a document</Link></div>
            </section>

            <section v-else aria-labelledby="attention-heading" class="workspace-surface flex flex-wrap items-center gap-x-5 gap-y-2 px-4 py-3">
                <h2 id="attention-heading" class="section-heading">{{ attentionCount ? 'Needs attention' : archiveStats.processing ? 'Processing your latest files' : 'Your archive is up to date' }}</h2>
                <Link v-if="archiveStats.failed" :href="route('library.index', { view: 'needs-review', status: 'failed' })" class="inline-flex items-center gap-2 text-sm text-orange-700 dark:text-orange-400"><ExclamationCircleIcon class="h-4 w-4" />{{ archiveStats.failed }} {{ archiveStats.failed === 1 ? 'file could' : 'files could' }} not be processed<ArrowRightIcon class="h-4 w-4" /></Link>
                <Link v-if="archiveStats.needs_review" :href="route('library.index', { view: 'needs-review' })" class="text-sm text-orange-700 dark:text-orange-400">{{ archiveStats.needs_review }} {{ archiveStats.needs_review === 1 ? 'file needs' : 'files need' }} a review →</Link>
                <Link v-if="archiveStats.processing" :href="route('library.index', { view: 'processing' })" class="inline-flex items-center gap-2 text-sm text-zinc-500"><ClockIcon class="h-4 w-4" />{{ archiveStats.processing }} {{ archiveStats.processing === 1 ? 'file' : 'files' }} processing</Link>
                <span v-if="!attentionCount && !archiveStats.processing" class="inline-flex items-center gap-2 text-sm text-zinc-500"><CheckCircleIcon class="h-4 w-4 text-emerald-600" />No action needed</span>
            </section>

            <div class="home-content workspace-fill grid min-h-0 min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                <section aria-labelledby="recent-heading" class="flex flex-1 min-h-0 min-w-0 flex-col">
                    <div class="mb-2 flex shrink-0 items-center justify-between gap-3"><h2 id="recent-heading" class="section-heading">Recent uploads</h2><Link :href="route('library.index', { view: 'recent' })" class="text-sm text-zinc-500 hover:text-orange-700 dark:hover:text-orange-400">View all</Link></div>
                    <FileWorkspace :files="recentUploads" :tags="workspaceTags" empty-title="Your latest uploads will appear here" empty-description="Upload a file or open your library to browse shared documents." />
                </section>

                <aside class="home-shortcuts flex min-h-0 min-w-0 flex-col gap-4 overflow-y-auto" aria-label="Archive shortcuts and upcoming dates">
                    <section v-if="expiringVouchers.total || endingWarranties.total" aria-labelledby="upcoming-heading">
                        <h2 id="upcoming-heading" class="section-heading">Coming up</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Expiring within 30 days</p>
                        <div class="mt-3 flex flex-col gap-3">
                            <Link v-if="expiringVouchers.total" :href="route('library.index', { type: 'voucher', view: 'expiring' })" class="block border-l-2 border-orange-500 pl-3"><p class="text-sm font-medium">{{ expiringVouchers.total }} expiring {{ expiringVouchers.total === 1 ? 'voucher' : 'vouchers' }}</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Check balances before they expire.</p></Link>
                            <Link v-if="endingWarranties.total" :href="route('library.index', { type: 'warranty', view: 'expiring' })" class="block border-l-2 border-zinc-300 pl-3 dark:border-zinc-600"><p class="text-sm font-medium">{{ endingWarranties.total }} {{ endingWarranties.total === 1 ? 'warranty ending' : 'warranties ending' }}</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Review coverage and product details.</p></Link>
                        </div>
                    </section>
                    <section aria-labelledby="browse-heading"><h2 id="browse-heading" class="section-heading">Explore your archive</h2><nav aria-label="Archive shortcuts" class="mt-3 flex flex-col gap-1"><Link v-for="shortcut in shortcuts" :key="shortcut.label" :href="shortcut.href" class="group flex items-start gap-3 rounded-md py-2"><component :is="shortcut.icon" class="mt-0.5 h-5 w-5 shrink-0 text-zinc-400" aria-hidden="true" /><span><span class="block text-sm font-medium text-zinc-700 group-hover:text-orange-700 dark:text-zinc-200 dark:group-hover:text-orange-400">{{ shortcut.label }}</span><span class="mt-1 hidden text-xs leading-5 text-zinc-500 lg:block dark:text-zinc-400">{{ shortcut.description }}</span></span></Link></nav></section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@reference '../../css/app.css';
@media (max-width: 1023px) {
    .home-content { @apply flex flex-col; }
    .home-shortcuts { @apply shrink-0 gap-2; }
    .home-shortcuts h2, .home-shortcuts p:not(.font-medium) { @apply sr-only; }
    .home-shortcuts nav { @apply mt-0; }
    .home-shortcuts nav { @apply flex-row flex-wrap gap-x-5; }
}
</style>
