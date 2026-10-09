<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRightIcon, ArrowUpTrayIcon, CheckCircleIcon, ClockIcon, DocumentTextIcon, ExclamationCircleIcon, FolderIcon, ChartBarIcon, UsersIcon } from '@heroicons/vue/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateFormatter } from '@/Composables/useDateFormatter';
import { fileStatusLabel } from '@/utils/fileStatus';

const props = defineProps({
    archiveStats: { type: Object, default: () => ({ total: 0, processing: 0, failed: 0, needs_review: 0 }) },
    recentUploads: { type: Array, default: () => [] },
    expiringVouchers: { type: Object, default: () => ({ total: 0, items: [] }) },
    endingWarranties: { type: Object, default: () => ({ total: 0, items: [] }) },
});
const { formatDate, formatCurrency } = useDateFormatter();
const attentionCount = computed(() => Number(props.archiveStats.needs_review) + Number(props.archiveStats.failed));
const shortcuts = [
    { label: 'Collections', description: 'Browse your folders and projects', icon: FolderIcon, href: route('collections.index') },
    { label: 'Reports', description: 'Understand spending and activity', icon: ChartBarIcon, href: route('analytics.index') },
    { label: 'Shared with me', description: 'Documents shared by others', icon: UsersIcon, href: route('library.index', { view: 'shared' }) },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Home" />
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h1 class="page-heading">Home</h1><p class="page-description">Pick up where you left off, or see what needs your attention.</p></div>
                <Link :href="route('library.index')" class="workspace-button">Open library <ArrowRightIcon class="h-4 w-4" aria-hidden="true" /></Link>
            </div>
        </template>

        <div class="flex flex-col gap-8">
            <section v-if="!archiveStats.total" class="workspace-surface flex flex-col items-start gap-4 p-6 sm:p-8" aria-labelledby="welcome-heading">
                <DocumentTextIcon class="h-8 w-8 text-orange-700 dark:text-orange-400" aria-hidden="true" />
                <div><h2 id="welcome-heading" class="text-xl">Your documents, in one place</h2><p class="page-description">Add a receipt, invoice or document. PaperPulse extracts the details so you can find them again.</p></div>
                <div class="flex flex-wrap items-center gap-4"><Link :href="route('documents.upload')" class="workspace-primary"><ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true" />Upload your first files</Link><Link :href="route('scanner')" class="text-sm font-medium text-zinc-600 hover:text-orange-700 dark:text-zinc-300">Scan a document</Link></div>
            </section>

            <section v-else aria-labelledby="attention-heading" class="workspace-surface overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 id="attention-heading" class="section-heading">{{ attentionCount ? 'Needs attention' : archiveStats.processing ? 'Processing your latest files' : 'Your archive is up to date' }}</h2>
                    <Link v-if="attentionCount" :href="route('library.index', { view: 'needs-review' })" class="text-sm font-medium text-orange-700 dark:text-orange-400">Review {{ attentionCount }} {{ attentionCount === 1 ? 'file' : 'files' }} <ArrowRightIcon class="ml-1 inline h-4 w-4" aria-hidden="true" /></Link>
                    <span v-else class="inline-flex items-center gap-2 text-sm text-zinc-500"><CheckCircleIcon class="h-4 w-4 text-emerald-600" aria-hidden="true" />No action needed</span>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <Link v-if="archiveStats.failed" :href="route('library.index', { view: 'needs-review', status: 'failed' })" class="flex items-center gap-4 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <ExclamationCircleIcon class="h-5 w-5 shrink-0 text-orange-700 dark:text-orange-400" aria-hidden="true" />
                        <div class="min-w-0 flex-1"><p class="text-sm font-medium">{{ archiveStats.failed }} {{ archiveStats.failed === 1 ? 'file could' : 'files could' }} not be processed</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Check what happened and retry when ready.</p></div><ArrowRightIcon class="h-4 w-4 shrink-0 text-zinc-400" aria-hidden="true" />
                    </Link>
                    <Link v-if="archiveStats.needs_review" :href="route('library.index', { view: 'needs-review' })" class="flex items-center gap-4 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <DocumentTextIcon class="h-5 w-5 shrink-0 text-orange-700 dark:text-orange-400" aria-hidden="true" />
                        <div class="min-w-0 flex-1"><p class="text-sm font-medium">{{ archiveStats.needs_review }} {{ archiveStats.needs_review === 1 ? 'file needs' : 'files need' }} a review</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Check flagged details against the original.</p></div><ArrowRightIcon class="h-4 w-4 shrink-0 text-zinc-400" aria-hidden="true" />
                    </Link>
                    <Link v-if="archiveStats.processing" :href="route('library.index', { view: 'processing' })" class="flex items-center gap-4 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <ClockIcon class="h-5 w-5 shrink-0 text-zinc-400" aria-hidden="true" /><div class="flex-1 text-sm"><span class="font-medium">{{ archiveStats.processing }} {{ archiveStats.processing === 1 ? 'file' : 'files' }} processing</span><p class="mt-1 text-zinc-500 dark:text-zinc-400">You can keep working while your files are prepared.</p></div><ArrowRightIcon class="h-4 w-4 shrink-0 text-zinc-400" aria-hidden="true" />
                    </Link>
                    <p v-if="!attentionCount && !archiveStats.processing" class="px-5 py-4 text-sm text-zinc-500 dark:text-zinc-400">Your files are ready to browse. Add something new or return to a recent document.</p>
                </div>
            </section>

            <div class="grid min-w-0 items-start gap-8 xl:grid-cols-[minmax(0,1fr)_280px]">
                <section aria-labelledby="recent-heading" class="min-w-0">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 id="recent-heading" class="section-heading">Recent uploads</h2><Link :href="route('library.index', { view: 'recent' })" class="text-sm text-zinc-500 hover:text-orange-700 dark:hover:text-orange-400">View all</Link></div>
                    <ul v-if="recentUploads.length" class="workspace-surface divide-y divide-zinc-100 dark:divide-zinc-800">
                        <li v-for="file in recentUploads.slice(0, 8)" :key="file.id">
                            <Link :href="route('files.show', file.id)" class="group flex items-center gap-3 px-4 py-4 transition-colors hover:bg-zinc-50 sm:gap-4 dark:hover:bg-zinc-800/50">
                                <span class="flex h-10 w-9 shrink-0 items-center justify-center rounded border border-zinc-200 bg-zinc-50 text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800"><DocumentTextIcon class="h-5 w-5" aria-hidden="true" /></span>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-zinc-900 group-hover:text-orange-700 dark:text-zinc-100 dark:group-hover:text-orange-400">{{ file.identity?.title || file.name }}</p><p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ file.original_name || file.name }} · Uploaded {{ formatDate(file.uploaded_at) }}</p></div>
                                <div class="flex shrink-0 flex-col items-end gap-1"><span v-if="file.identity?.amount != null" class="max-w-[9rem] break-words text-right text-xs font-medium tabular-nums text-zinc-700 sm:text-sm dark:text-zinc-200">{{ formatCurrency(file.identity.amount, file.identity.currency) }}</span><span class="text-xs" :class="['failed', 'needs_review'].includes(file.status) ? 'text-orange-700 dark:text-orange-400' : 'text-zinc-500 dark:text-zinc-400'">{{ fileStatusLabel(file) }}</span></div>
                            </Link>
                        </li>
                    </ul>
                    <div v-else class="workspace-surface workspace-empty"><p>Your latest uploads will appear here.</p><Link :href="route('library.index', { view: 'shared' })" class="font-medium text-zinc-700 underline dark:text-zinc-300">Browse documents shared with you</Link></div>
                </section>

                <aside class="flex min-w-0 flex-col gap-8" aria-label="Archive shortcuts and upcoming dates">
                    <section v-if="expiringVouchers.total || endingWarranties.total" aria-labelledby="upcoming-heading">
                        <h2 id="upcoming-heading" class="section-heading">Coming up</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Expiring within 30 days</p>
                        <div class="mt-4 flex flex-col gap-4">
                            <Link v-if="expiringVouchers.total" :href="route('library.index', { type: 'voucher', view: 'expiring' })" class="block border-l-2 border-orange-500 pl-3"><p class="text-sm font-medium">{{ expiringVouchers.total }} expiring {{ expiringVouchers.total === 1 ? 'voucher' : 'vouchers' }}</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Check balances before they expire.</p></Link>
                            <Link v-if="endingWarranties.total" :href="route('library.index', { type: 'warranty', view: 'expiring' })" class="block border-l-2 border-zinc-300 pl-3 dark:border-zinc-600"><p class="text-sm font-medium">{{ endingWarranties.total }} {{ endingWarranties.total === 1 ? 'warranty ending' : 'warranties ending' }}</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Review coverage and product details.</p></Link>
                        </div>
                    </section>
                    <section aria-labelledby="browse-heading"><h2 id="browse-heading" class="section-heading">Explore your archive</h2><nav aria-label="Archive shortcuts" class="mt-3 flex flex-col gap-1"><Link v-for="shortcut in shortcuts" :key="shortcut.label" :href="shortcut.href" class="group flex items-start gap-3 rounded-md py-3"><component :is="shortcut.icon" class="mt-0.5 h-5 w-5 shrink-0 text-zinc-400" aria-hidden="true" /><span><span class="block text-sm font-medium text-zinc-700 group-hover:text-orange-700 dark:text-zinc-200 dark:group-hover:text-orange-400">{{ shortcut.label }}</span><span class="mt-1 block text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ shortcut.description }}</span></span></Link></nav></section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
