<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Common/Modal.vue';
import TagManager from '@/Components/Domain/TagManager.vue';
import SharingControls from '@/Components/Domain/SharingControls.vue';
import DocumentImage from '@/Components/Domain/DocumentImage.vue';
import CollectionSelector from '@/Components/Domain/CollectionSelector.vue';
import CollectionBadge from '@/Components/Domain/CollectionBadge.vue';
import {
    DocumentIcon,
    ArrowDownTrayIcon,
    TrashIcon,
    PencilIcon,
    CheckIcon,
    ArrowLeftIcon,
    RectangleStackIcon
} from '@heroicons/vue/24/outline';

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface Collection {
    id: number;
    name: string;
    icon: string;
    color: string;
}

interface SharedUser {
    id: number;
    name: string;
    email: string;
    permission: 'view' | 'edit';
    shared_at: string;
}

interface FileInfo {
    id: number;
    url: string;
    pdfUrl: string | null;
    previewUrl?: string | null;
    extension: string;
    mime_type?: string;
    size?: number;
    guid?: string;
    has_preview?: boolean;
    is_pdf?: boolean;
    uploaded_at?: string | null;
    file_created_at?: string | null;
    file_modified_at?: string | null;
}

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    tax_rate: number;
    total_amount: number;
}

interface Invoice {
    id: number;
    file_id: number;
    invoice_number: string;
    invoice_type?: string | null;
    invoice_date: string | null;
    due_date: string | null;
    delivery_date?: string | null;
    payment_status: string;
    from_name: string;
    from_address?: string | null;
    from_vat_number?: string | null;
    from_email?: string | null;
    to_name: string;
    to_address?: string | null;
    to_vat_number?: string | null;
    to_email?: string | null;
    line_items: LineItem[];
    subtotal?: number | null;
    tax_amount?: number | null;
    discount_amount?: number | null;
    shipping_amount?: number | null;
    total_amount: number;
    amount_paid?: number;
    amount_due?: number;
    currency?: string;
    payment_method?: string | null;
    payment_terms?: string | null;
    purchase_order_number?: string | null;
    reference_number?: string | null;
    notes?: string | null;
    tags: Tag[];
    collections: Collection[];
    shared_users: SharedUser[];
    created_at: string | null;
    updated_at: string | null;
    file: FileInfo | null;
}

interface Props {
    invoice: Invoice;
    available_tags: Tag[];
}

const props = defineProps<Props>();

const isEditing = ref(false);
const showDeleteModal = ref(false);
const invoiceTags = ref(props.invoice.tags);
const invoiceCollections = ref<number[]>(props.invoice.collections?.map(c => c.id) || []);
const editedInvoice = ref({
    notes: props.invoice.notes,
});

const isOverdue = computed(() => {
    return props.invoice.due_date &&
           new Date(props.invoice.due_date) < new Date() &&
           props.invoice.payment_status !== 'paid';
});

const paymentStatusClass = computed(() => {
    const classes: Record<string, string> = {
        'paid': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
        'unpaid': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
        'partial': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
        'overdue': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
    };
    return classes[props.invoice.payment_status] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300';
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('no-NO', {
        style: 'currency',
        currency: props.invoice.currency || 'NOK'
    }).format(amount);
};

const formatDate = (date: string | null | undefined) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Auto-save when exiting edit mode
watch(isEditing, (newValue) => {
    if (!newValue) {
        // Exiting edit mode - save changes
        router.patch(route('invoices.update', props.invoice.id), {
            ...editedInvoice.value,
            tags: invoiceTags.value.map(t => t.id)
        }, {
            preserveScroll: true
        });
    } else {
        // Entering edit mode - reset form
        editedInvoice.value = {
            notes: props.invoice.notes,
        };
    }
});

const deleteInvoice = () => {
    router.delete(route('invoices.destroy', props.invoice.id));
};

const downloadInvoice = () => {
    window.location.href = route('invoices.download', props.invoice.id);
};

const handleTagAdded = (tag: Tag) => {
    if (isEditing.value) {
        invoiceTags.value = [...invoiceTags.value, tag];
    } else {
        router.post(route('invoices.tags.store', props.invoice.id), {
            name: tag.name
        }, {
            preserveScroll: true
        });
    }
};

const handleTagRemoved = (tag: Tag) => {
    if (isEditing.value) {
        invoiceTags.value = invoiceTags.value.filter(t => t.id !== tag.id);
    } else {
        router.delete(route('invoices.tags.destroy', [props.invoice.id, tag.id]), {
            preserveScroll: true
        });
    }
};

const handleCollectionsChanged = (collectionIds: number[]) => {
    invoiceCollections.value = collectionIds;

    // If not in edit mode, save immediately
    if (!isEditing.value && props.invoice.file_id) {
        // Find collections to add and remove
        const currentIds = props.invoice.collections?.map(c => c.id) || [];
        const toAdd = collectionIds.filter(id => !currentIds.includes(id));
        const toRemove = currentIds.filter(id => !collectionIds.includes(id));

        // Add to new collections
        toAdd.forEach(collectionId => {
            router.post(route('collections.files.add', collectionId), {
                file_ids: [props.invoice.file_id]
            }, {
                preserveScroll: true
            });
        });

        // Remove from old collections
        toRemove.forEach(collectionId => {
            router.delete(route('collections.files.remove', collectionId), {
                data: { file_ids: [props.invoice.file_id] },
                preserveScroll: true
            });
        });
    }
};

const handleSharesUpdated = (shares: any[]) => {
    props.invoice.shared_users = (shares || []).map((s: any) => ({
        id: s.shared_with_user?.id ?? s.id,
        name: s.shared_with_user?.name ?? s.name,
        email: s.shared_with_user?.email ?? s.email,
        permission: s.permission,
        shared_at: s.shared_at,
    }));
};

const sharingControlShares = computed(() => {
    const users = (props.invoice.shared_users || []) as any[];
    return users.map((u: any) => ({
        shared_with_user: { id: u.id, name: u.name, email: u.email },
        permission: u.permission,
        shared_at: u.shared_at,
    }));
});

const getInvoiceTypeClass = () => {
    return 'text-green-400 bg-green-400/10';
};
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight flex items-center gap-x-2">
                    <DocumentIcon class="size-6" />
                    Invoice #{{ invoice.invoice_number }}
                </h2>
                <div class="flex items-center gap-x-4">
                    <button
                        @click="downloadInvoice"
                        class="inline-flex items-center gap-x-2 px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Download
                    </button>
                    <SharingControls
                        :file-id="invoice.id"
                        file-type="invoice"
                        :current-shares="sharingControlShares"
                        @shares-updated="handleSharesUpdated"
                    />
                    <Link
                        :href="route('invoices.index')"
                        class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Back to Invoices
                    </Link>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-9rem)] overflow-hidden">
            <!-- Left Panel - Invoice Details -->
            <div class="w-1/2 p-6 overflow-y-auto border-r border-amber-200 dark:border-zinc-700">
                <div class="space-y-8">
                    <!-- Invoice Status Badge -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-3">
                                <div :class="[getInvoiceTypeClass(), 'flex-none rounded-full p-1']">
                                    <div class="size-2 rounded-full bg-current" />
                                </div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">Invoice Details</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium" :class="paymentStatusClass">
                                {{ invoice.payment_status }}
                            </span>
                        </div>

                        <dl class="mt-6 space-y-6">
                            <!-- Invoice Number -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Number</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ invoice.invoice_number }}
                                </dd>
                            </div>

                            <!-- Invoice Type -->
                            <div v-if="invoice.invoice_type" class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Invoice Type</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ invoice.invoice_type }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Parties Information -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Parties</h3>
                        <div class="grid grid-cols-2 gap-6">
                            <!-- From -->
                            <div>
                                <h4 class="text-xs font-medium text-zinc-500 mb-2">From</h4>
                                <div class="text-zinc-900 dark:text-zinc-100">
                                    <p class="font-semibold">{{ invoice.from_name }}</p>
                                    <p v-if="invoice.from_address" class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line mt-1">
                                        {{ invoice.from_address }}
                                    </p>
                                    <p v-if="invoice.from_vat_number" class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                                        VAT: {{ invoice.from_vat_number }}
                                    </p>
                                    <p v-if="invoice.from_email" class="text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ invoice.from_email }}
                                    </p>
                                </div>
                            </div>

                            <!-- To -->
                            <div>
                                <h4 class="text-xs font-medium text-zinc-500 mb-2">To</h4>
                                <div class="text-zinc-900 dark:text-zinc-100">
                                    <p class="font-semibold">{{ invoice.to_name }}</p>
                                    <p v-if="invoice.to_address" class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line mt-1">
                                        {{ invoice.to_address }}
                                    </p>
                                    <p v-if="invoice.to_vat_number" class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                                        VAT: {{ invoice.to_vat_number }}
                                    </p>
                                    <p v-if="invoice.to_email" class="text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ invoice.to_email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Important Dates -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Important Dates</h3>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">Invoice Date</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.invoice_date) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Due Date</dt>
                                <dd class="text-sm" :class="{'text-red-600 dark:text-red-400': isOverdue, 'text-zinc-900 dark:text-white': !isOverdue}">
                                    {{ formatDate(invoice.due_date) }}
                                </dd>
                            </div>
                            <div v-if="invoice.delivery_date" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Delivery Date</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.delivery_date) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- References -->
                    <div
                        v-if="invoice.payment_method || invoice.purchase_order_number || invoice.reference_number"
                        class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700"
                    >
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">References</h3>
                        <dl class="space-y-3">
                            <div v-if="invoice.payment_method">
                                <dt class="text-xs font-medium text-zinc-500">Payment Method</dt>
                                <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ invoice.payment_method }}</dd>
                            </div>
                            <div v-if="invoice.purchase_order_number" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Purchase Order</dt>
                                <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ invoice.purchase_order_number }}</dd>
                            </div>
                            <div v-if="invoice.reference_number" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Reference Number</dt>
                                <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ invoice.reference_number }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Line Items -->
                    <div v-if="invoice.line_items && invoice.line_items.length > 0" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Line Items</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-200 dark:divide-zinc-700">
                                <thead class="bg-amber-50 dark:bg-zinc-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">Description</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">Unit Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">Tax</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-amber-200 dark:divide-zinc-700">
                                    <tr v-for="item in invoice.line_items" :key="item.id">
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ item.description }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-zinc-900 dark:text-zinc-100">{{ item.quantity }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-zinc-900 dark:text-zinc-100">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-zinc-600 dark:text-zinc-300">{{ item.tax_rate }}%</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-zinc-900 dark:text-zinc-100">{{ formatCurrency(item.total_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Financial Summary</h3>
                        <div class="space-y-2">
                            <div v-if="invoice.subtotal" class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>Subtotal:</span>
                                <span>{{ formatCurrency(invoice.subtotal) }}</span>
                            </div>
                            <div v-if="invoice.tax_amount" class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>Tax:</span>
                                <span>{{ formatCurrency(invoice.tax_amount) }}</span>
                            </div>
                            <div v-if="invoice.discount_amount" class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>Discount:</span>
                                <span class="text-red-600 dark:text-red-400">-{{ formatCurrency(invoice.discount_amount) }}</span>
                            </div>
                            <div v-if="invoice.shipping_amount" class="flex justify-between text-zinc-700 dark:text-zinc-300">
                                <span>Shipping:</span>
                                <span>{{ formatCurrency(invoice.shipping_amount) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-zinc-900 dark:text-zinc-100 pt-2 border-t border-amber-200 dark:border-zinc-600">
                                <span>Total:</span>
                                <span>{{ formatCurrency(invoice.total_amount) }}</span>
                            </div>
                            <div v-if="invoice.amount_paid && invoice.amount_paid > 0" class="flex justify-between text-green-600 dark:text-green-400">
                                <span>Paid:</span>
                                <span>{{ formatCurrency(invoice.amount_paid) }}</span>
                            </div>
                            <div v-if="invoice.amount_due && invoice.amount_due > 0" class="flex justify-between text-red-600 dark:text-red-400 font-semibold">
                                <span>Amount Due:</span>
                                <span>{{ formatCurrency(invoice.amount_due) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Terms -->
                    <div v-if="invoice.payment_terms" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Payment Terms</h3>
                        <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">{{ invoice.payment_terms }}</p>
                    </div>

                    <!-- Notes -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Notes</h3>
                        <p v-if="!isEditing" class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ invoice.notes || 'No notes available' }}
                        </p>
                        <textarea
                            v-else
                            v-model="editedInvoice.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                        />
                        <div class="mt-4">
                            <button
                                @click="isEditing = !isEditing"
                                class="w-full inline-flex justify-center items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                                :class="isEditing ? 'text-zinc-900 bg-amber-100 hover:bg-amber-200 dark:text-zinc-100 dark:bg-zinc-700 dark:hover:bg-amber-600' : 'text-zinc-100 bg-zinc-700 hover:bg-amber-600 dark:bg-zinc-600 dark:hover:bg-zinc-500'"
                            >
                                <PencilIcon v-if="!isEditing" class="size-4" />
                                <CheckIcon v-else class="size-4" />
                                {{ isEditing ? 'Save Changes' : 'Edit Notes' }}
                            </button>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Tags</h3>
                        <TagManager
                            v-model="invoiceTags"
                            :readonly="!isEditing"
                            @tag-added="handleTagAdded"
                            @tag-removed="handleTagRemoved"
                        />
                    </div>

                    <!-- Collections -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4 flex items-center gap-2">
                            <RectangleStackIcon class="size-5" />
                            Collections
                        </h3>
                        <CollectionSelector
                            v-model="invoiceCollections"
                            placeholder="Add to collections..."
                            :allow-create="true"
                            @update:model-value="handleCollectionsChanged"
                        />
                        <div v-if="invoice.collections && invoice.collections.length > 0" class="mt-3 flex flex-wrap gap-2">
                            <CollectionBadge
                                v-for="collection in invoice.collections"
                                :key="collection.id"
                                :collection="collection"
                                :linkable="true"
                            />
                        </div>
                        <p v-else class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Not assigned to any collections
                        </p>
                    </div>

                    <!-- File Metadata -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">File Information</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">File Size</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatFileSize(invoice.file?.size || 0) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Uploaded to PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.file?.uploaded_at || invoice.created_at) }}
                                </dd>
                            </div>
                            <div v-if="invoice.file?.file_created_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Created</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.file.file_created_at) }}
                                </dd>
                            </div>
                            <div v-if="invoice.file?.file_modified_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Modified</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.file.file_modified_at) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Last Updated in PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(invoice.updated_at) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Delete Button -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <button
                            @click="showDeleteModal = true"
                            class="w-full inline-flex justify-center items-center gap-x-2 px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete Invoice
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Invoice Preview -->
            <div class="w-1/2 bg-amber-50 dark:bg-zinc-900 overflow-auto">
                <DocumentImage
                    :file="invoice.file"
                    :alt-text="`Invoice ${invoice.invoice_number}`"
                    error-message="Failed to load invoice preview"
                    no-image-message="No invoice preview available"
                    :show-pdf-button="true"
                    pdf-button-position="fixed bottom-6 right-6"
                />
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-white">
                    Delete Invoice
                </h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete this invoice? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md font-semibold text-xs text-zinc-700 dark:text-zinc-300 uppercase tracking-widest hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteInvoice"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
