<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import PublicFileViewer from '@/Components/Public/PublicFileViewer.vue'
import { Icon } from '@iconify/vue'

const props = defineProps({
    collection: {
        type: Object,
        required: true,
    },
    files: {
        type: Array,
        required: true,
    },
    link: {
        type: Object,
        required: true,
    },
})

const selectedIndex = ref(props.files.length > 0 ? 0 : -1)

const selectedFile = computed(() => {
    if (selectedIndex.value < 0 || selectedIndex.value >= props.files.length) return null
    return props.files[selectedIndex.value]
})

const expiresLabel = computed(() => {
    if (!props.link.expires_at) return null
    const date = new Date(props.link.expires_at)
    return `Expires ${date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}`
})

function selectFile(index) {
    selectedIndex.value = index
}

function getEntityTypeBadge(type) {
    const badges = {
        receipt: { label: 'Receipt', classes: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
        document: { label: 'Document', classes: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
        invoice: { label: 'Invoice', classes: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' },
        contract: { label: 'Contract', classes: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
        voucher: { label: 'Voucher', classes: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400' },
        bank_statement: { label: 'Statement', classes: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400' },
    }
    return badges[type] || { label: type || 'File', classes: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }
}

function formatCurrency(amount, currency) {
    if (!amount) return null
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency || 'USD',
        }).format(amount)
    } catch {
        return `${amount}`
    }
}

function getFileTypeKey(file) {
    const ext = (file.extension || '').toLowerCase()
    if (ext === 'pdf') return 'pdf'
    if (['doc', 'docx'].includes(ext)) return 'word'
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel'
    if (['ppt', 'pptx'].includes(ext)) return 'powerpoint'
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'tiff', 'tif', 'bmp', 'svg'].includes(ext)) return 'image'
    if (['txt', 'log', 'md'].includes(ext)) return 'text'
    return 'generic'
}

const fileTypeConfig = {
    pdf: { icon: 'mdi:file-pdf-box', color: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-900/20' },
    word: { icon: 'mdi:file-word-box', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    excel: { icon: 'mdi:file-excel-box', color: 'text-green-600 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-900/20' },
    powerpoint: { icon: 'mdi:file-powerpoint-box', color: 'text-orange-600 dark:text-orange-400', bg: 'bg-orange-50 dark:bg-orange-900/20' },
    image: { icon: 'mdi:file-image-box', color: 'text-purple-600 dark:text-purple-400', bg: 'bg-purple-50 dark:bg-purple-900/20' },
    text: { icon: 'mdi:file-document-outline', color: 'text-zinc-500 dark:text-zinc-400', bg: 'bg-zinc-100 dark:bg-zinc-700' },
    generic: { icon: 'mdi:file-outline', color: 'text-zinc-400 dark:text-zinc-500', bg: 'bg-zinc-100 dark:bg-zinc-700' },
}

function getDisplayTitle(file) {
    return file.entity_title || file.name
}
</script>

<template>
    <Head :title="`${collection.name} - Shared Collection`" />

    <div class="flex flex-col h-screen bg-white dark:bg-zinc-900">
        <!-- Header -->
        <header class="flex-shrink-0 border-b border-amber-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-5 py-3.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :style="{ backgroundColor: collection.color + '20' }"
                    >
                        <svg class="h-5 w-5" :style="{ color: collection.color }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ collection.name }}</h1>
                        <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>{{ files.length }} {{ files.length === 1 ? 'file' : 'files' }}</span>
                            <span v-if="collection.owner_name">&middot; Shared by {{ collection.owner_name }}</span>
                            <span v-if="expiresLabel" class="text-amber-600 dark:text-amber-400">&middot; {{ expiresLabel }}</span>
                        </div>
                    </div>
                </div>

                <a
                    v-if="files.length > 0"
                    :href="route('shared.collections.download', link.token)"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 dark:bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 dark:hover:bg-amber-500 transition"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download All
                </a>
            </div>
        </header>

        <!-- Main three-column layout -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Left column: File list -->
            <div class="w-96 flex-shrink-0 border-r border-zinc-200 dark:border-zinc-700 overflow-y-auto bg-zinc-50 dark:bg-zinc-800/50">
                <div class="p-2">
                    <div
                        v-for="(file, index) in files"
                        :key="file.guid"
                        @click="selectFile(index)"
                        :class="[
                            'flex items-start gap-3 rounded-lg px-3 py-3 cursor-pointer transition',
                            selectedIndex === index
                                ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-100'
                                : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700/50',
                        ]"
                    >
                        <div
                            :class="[
                                'flex items-center justify-center h-10 w-10 rounded-lg shrink-0',
                                fileTypeConfig[getFileTypeKey(file)].bg,
                            ]"
                        >
                            <Icon
                                :icon="fileTypeConfig[getFileTypeKey(file)].icon"
                                :class="['h-6 w-6', fileTypeConfig[getFileTypeKey(file)].color]"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm leading-snug break-words">{{ getDisplayTitle(file) }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5 break-all">{{ file.name }}</p>
                            <span
                                :class="['inline-block mt-1 rounded-full px-2 py-0.5 text-[11px] font-medium', getEntityTypeBadge(file.entity_type).classes]"
                            >
                                {{ getEntityTypeBadge(file.entity_type).label }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="files.length === 0" class="p-6 text-center text-sm text-zinc-400 dark:text-zinc-500">
                    No files in this collection.
                </div>
            </div>

            <!-- Middle column: Metadata -->
            <div class="w-80 flex-shrink-0 border-r border-zinc-200 dark:border-zinc-700 overflow-y-auto bg-white dark:bg-zinc-800">
                <template v-if="selectedFile">
                    <div class="p-5 space-y-5">
                        <!-- Entity type badge + download button -->
                        <div class="flex items-center justify-between">
                            <span
                                :class="['rounded-full px-2.5 py-1 text-xs font-semibold', getEntityTypeBadge(selectedFile.entity_type).classes]"
                            >
                                {{ getEntityTypeBadge(selectedFile.entity_type).label }}
                            </span>
                            <a
                                :href="selectedFile.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 dark:border-amber-700 bg-white dark:bg-zinc-700 px-3.5 py-2 text-sm font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-zinc-600 transition"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Download
                            </a>
                        </div>

                        <!-- Title (AI name) + file name -->
                        <div>
                            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 break-words">{{ getDisplayTitle(selectedFile) }}</h3>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 break-all">{{ selectedFile.name }}</p>
                        </div>

                        <!-- Entity details -->
                        <div v-if="selectedFile.entity_details" class="space-y-4">
                            <!-- Receipt details -->
                            <template v-if="selectedFile.entity_type === 'receipt'">
                                <div v-if="selectedFile.entity_details.merchant_name" class="flex items-center gap-2">
                                    <img
                                        v-if="selectedFile.entity_details.merchant_logo"
                                        :src="selectedFile.entity_details.merchant_logo"
                                        :alt="selectedFile.entity_details.merchant_name"
                                        class="h-6 w-6 rounded object-contain"
                                    />
                                    <div>
                                        <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Merchant</p>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.merchant_name }}</p>
                                    </div>
                                </div>
                                <div v-if="selectedFile.entity_details.purchase_date">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Date</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.purchase_date }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.total">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total</p>
                                    <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ formatCurrency(selectedFile.entity_details.total, selectedFile.entity_details.currency) }}
                                    </p>
                                </div>
                            </template>

                            <!-- Document details -->
                            <template v-else-if="selectedFile.entity_type === 'document'">
                                <div v-if="selectedFile.entity_details.title">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Title</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.title }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.summary">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Summary</p>
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ selectedFile.entity_details.summary }}</p>
                                </div>
                            </template>

                            <!-- Invoice details -->
                            <template v-else-if="selectedFile.entity_type === 'invoice'">
                                <div v-if="selectedFile.entity_details.vendor_name">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Vendor</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.vendor_name }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.invoice_number">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Invoice #</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.invoice_number }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.due_date">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Due Date</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.due_date }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.total_amount">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total</p>
                                    <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ formatCurrency(selectedFile.entity_details.total_amount, selectedFile.entity_details.currency) }}
                                    </p>
                                </div>
                            </template>

                            <!-- Contract details -->
                            <template v-else-if="selectedFile.entity_type === 'contract'">
                                <div v-if="selectedFile.entity_details.title">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Title</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.title }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.parties">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Parties</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.parties }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.effective_date">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Effective Date</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.effective_date }}</p>
                                </div>
                            </template>

                            <!-- Voucher details -->
                            <template v-else-if="selectedFile.entity_type === 'voucher'">
                                <div v-if="selectedFile.entity_details.code">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Code</p>
                                    <p class="text-sm font-mono font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.code }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.value">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Value</p>
                                    <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ formatCurrency(selectedFile.entity_details.value, selectedFile.entity_details.currency) }}
                                    </p>
                                </div>
                                <div v-if="selectedFile.entity_details.expires_at">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Expires</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.expires_at }}</p>
                                </div>
                            </template>

                            <!-- Bank statement details -->
                            <template v-else-if="selectedFile.entity_type === 'bank_statement'">
                                <div v-if="selectedFile.entity_details.bank_name">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Bank</p>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.bank_name }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.account_number_masked">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Account</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.account_number_masked }}</p>
                                </div>
                                <div v-if="selectedFile.entity_details.statement_period">
                                    <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Period</p>
                                    <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ selectedFile.entity_details.statement_period }}</p>
                                </div>
                            </template>
                        </div>

                        <!-- Tags -->
                        <div v-if="selectedFile.tags && selectedFile.tags.length > 0" class="pt-4 border-t border-zinc-100 dark:border-zinc-700">
                            <p class="text-xs uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Tags</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="tag in selectedFile.tags"
                                    :key="tag.id"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :style="{
                                        backgroundColor: tag.color + '20',
                                        color: tag.color,
                                    }"
                                >
                                    {{ tag.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="p-5 text-center text-sm text-zinc-400 dark:text-zinc-500 mt-8">
                    Select a file to view details
                </div>
            </div>

            <!-- Right column: File viewer -->
            <div class="flex-1 overflow-hidden">
                <PublicFileViewer :file="selectedFile" />
            </div>
        </div>

        <!-- Footer -->
        <footer class="flex-shrink-0 border-t border-amber-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-2 text-center">
            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                Shared via PaperPulse
            </p>
        </footer>
    </div>
</template>
