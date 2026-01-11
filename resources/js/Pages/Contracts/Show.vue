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

interface Party {
    name: string;
    role?: string;
    contact?: string;
}

interface PaymentSchedule {
    description?: string;
    milestone?: string;
    label?: string;
    amount: number;
    date?: string;
    due_date?: string;
    scheduled_at?: string;
}

interface Obligation {
    party?: string;
    description: string;
}

interface Contract {
    id: number;
    file_id: number;
    contract_title: string;
    contract_number?: string;
    contract_type?: string;
    status: string;
    effective_date?: string | null;
    expiry_date?: string | null;
    signature_date?: string | null;
    duration?: string | null;
    contract_value?: number | null;
    currency?: string;
    parties?: Party[];
    payment_schedule?: PaymentSchedule[];
    key_terms?: string[];
    obligations?: Obligation[];
    summary?: string | null;
    renewal_terms?: string | null;
    termination_conditions?: string | null;
    governing_law?: string | null;
    jurisdiction?: string | null;
    tags: Tag[];
    collections: Collection[];
    shared_users: SharedUser[];
    created_at: string | null;
    updated_at: string | null;
    file: FileInfo | null;
}

interface Props {
    contract: Contract;
    available_tags: Tag[];
}

const props = defineProps<Props>();

const isEditing = ref(false);
const showDeleteModal = ref(false);
const contractTags = ref(props.contract.tags);
const contractCollections = ref<number[]>(props.contract.collections?.map(c => c.id) || []);
const editedContract = ref({
    contract_title: props.contract.contract_title,
    summary: props.contract.summary,
});

const isExpiring = computed(() => {
    if (!props.contract.expiry_date) return false;
    const daysUntil = Math.ceil(
        (new Date(props.contract.expiry_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)
    );
    return daysUntil <= 90 && daysUntil > 0;
});

const daysUntilExpiry = computed(() => {
    if (!props.contract.expiry_date) return 0;
    return Math.ceil(
        (new Date(props.contract.expiry_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)
    );
});

const statusClass = computed(() => {
    const classes: Record<string, string> = {
        'draft': 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300',
        'active': 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
        'expired': 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
        'terminated': 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300',
        'renewed': 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
    };
    return classes[props.contract.status] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300';
});

const formatContractType = (type: string | undefined) => {
    if (!type) return 'N/A';
    const types: Record<string, string> = {
        'employment': 'Employment Contract',
        'service': 'Service Agreement',
        'rental': 'Rental Agreement',
        'purchase': 'Purchase Agreement',
        'nda': 'Non-Disclosure Agreement',
    };
    return types[type] || type;
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('no-NO', {
        style: 'currency',
        currency: props.contract.currency || 'NOK'
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

const paymentLabel = (payment: PaymentSchedule) => {
    return payment.description || payment.milestone || payment.label || 'Payment';
};

const paymentDate = (payment: PaymentSchedule) => {
    return payment.date || payment.due_date || payment.scheduled_at || null;
};

// Auto-save when exiting edit mode
watch(isEditing, (newValue) => {
    if (!newValue) {
        // Exiting edit mode - save changes
        router.patch(route('contracts.update', props.contract.id), {
            ...editedContract.value,
            tags: contractTags.value.map(t => t.id)
        }, {
            preserveScroll: true
        });
    } else {
        // Entering edit mode - reset form
        editedContract.value = {
            contract_title: props.contract.contract_title,
            summary: props.contract.summary,
        };
    }
});

const deleteContract = () => {
    router.delete(route('contracts.destroy', props.contract.id));
};

const downloadContract = () => {
    window.location.href = route('contracts.download', props.contract.id);
};

const handleTagAdded = (tag: Tag) => {
    if (isEditing.value) {
        contractTags.value = [...contractTags.value, tag];
    } else {
        router.post(route('contracts.tags.store', props.contract.id), {
            name: tag.name
        }, {
            preserveScroll: true
        });
    }
};

const handleTagRemoved = (tag: Tag) => {
    if (isEditing.value) {
        contractTags.value = contractTags.value.filter(t => t.id !== tag.id);
    } else {
        router.delete(route('contracts.tags.destroy', [props.contract.id, tag.id]), {
            preserveScroll: true
        });
    }
};

const handleCollectionsChanged = (collectionIds: number[]) => {
    contractCollections.value = collectionIds;

    // If not in edit mode, save immediately
    if (!isEditing.value && props.contract.file_id) {
        // Find collections to add and remove
        const currentIds = props.contract.collections?.map(c => c.id) || [];
        const toAdd = collectionIds.filter(id => !currentIds.includes(id));
        const toRemove = currentIds.filter(id => !collectionIds.includes(id));

        // Add to new collections
        toAdd.forEach(collectionId => {
            router.post(route('collections.files.add', collectionId), {
                file_ids: [props.contract.file_id]
            }, {
                preserveScroll: true
            });
        });

        // Remove from old collections
        toRemove.forEach(collectionId => {
            router.delete(route('collections.files.remove', collectionId), {
                data: { file_ids: [props.contract.file_id] },
                preserveScroll: true
            });
        });
    }
};

const handleSharesUpdated = (shares: any[]) => {
    props.contract.shared_users = (shares || []).map((s: any) => ({
        id: s.shared_with_user?.id ?? s.id,
        name: s.shared_with_user?.name ?? s.name,
        email: s.shared_with_user?.email ?? s.email,
        permission: s.permission,
        shared_at: s.shared_at,
    }));
};

const sharingControlShares = computed(() => {
    const users = (props.contract.shared_users || []) as any[];
    return users.map((u: any) => ({
        shared_with_user: { id: u.id, name: u.name, email: u.email },
        permission: u.permission,
        shared_at: u.shared_at,
    }));
});

const getContractTypeClass = () => {
    return 'text-blue-400 bg-blue-400/10';
};
</script>

<template>
    <Head :title="contract.contract_title || 'Contract'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight flex items-center gap-x-2">
                    <DocumentIcon class="size-6" />
                    {{ contract.contract_title || 'Contract' }}
                </h2>
                <div class="flex items-center gap-x-4">
                    <button
                        @click="downloadContract"
                        class="inline-flex items-center gap-x-2 px-3 py-2 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Download
                    </button>
                    <SharingControls
                        :file-id="contract.id"
                        file-type="contract"
                        :current-shares="sharingControlShares"
                        @shares-updated="handleSharesUpdated"
                    />
                    <Link
                        :href="route('contracts.index')"
                        class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                    >
                        <ArrowLeftIcon class="size-4" />
                        Back to Contracts
                    </Link>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-9rem)] overflow-hidden">
            <!-- Left Panel - Contract Details -->
            <div class="w-1/2 p-6 overflow-y-auto border-r border-amber-200 dark:border-zinc-700">
                <div class="space-y-8">
                    <!-- Contract Status Badge -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-3">
                                <div :class="[getContractTypeClass(), 'flex-none rounded-full p-1']">
                                    <div class="size-2 rounded-full bg-current" />
                                </div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200">Contract Details</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusClass">
                                {{ contract.status }}
                            </span>
                        </div>

                        <dl class="mt-6 space-y-6">
                            <!-- Title -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Title</dt>
                                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ contract.contract_title || 'Untitled Contract' }}
                                </dd>
                                <input
                                    v-else
                                    v-model="editedContract.contract_title"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                />
                            </div>

                            <!-- Contract Number -->
                            <div v-if="contract.contract_number" class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Contract Number</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ contract.contract_number }}
                                </dd>
                            </div>

                            <!-- Contract Type -->
                            <div class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Contract Type</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ formatContractType(contract.contract_type) }}
                                </dd>
                            </div>

                            <!-- Duration -->
                            <div v-if="contract.duration" class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Duration</dt>
                                <dd class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ contract.duration }}
                                </dd>
                            </div>

                            <!-- Summary -->
                            <div v-if="contract.summary || isEditing" class="flex flex-col">
                                <dt class="text-sm font-bold text-zinc-500 dark:text-zinc-400">Summary</dt>
                                <dd v-if="!isEditing" class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                                    {{ contract.summary || 'No summary available' }}
                                </dd>
                                <textarea
                                    v-else
                                    v-model="editedContract.summary"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200 shadow-sm ring-1 ring-inset ring-zinc-300 dark:ring-zinc-600 focus:ring-2 focus:ring-inset focus:ring-amber-600"
                                />
                            </div>
                        </dl>

                        <div class="mt-6">
                            <button
                                @click="isEditing = !isEditing"
                                class="w-full inline-flex justify-center items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                                :class="isEditing ? 'text-zinc-900 bg-amber-100 hover:bg-amber-200 dark:text-zinc-100 dark:bg-zinc-700 dark:hover:bg-amber-600' : 'text-zinc-100 bg-zinc-700 hover:bg-amber-600 dark:bg-zinc-600 dark:hover:bg-zinc-500'"
                            >
                                <PencilIcon v-if="!isEditing" class="size-4" />
                                <CheckIcon v-else class="size-4" />
                                {{ isEditing ? 'Save Changes' : 'Edit Contract' }}
                            </button>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Important Dates</h3>
                        <dl class="space-y-4">
                            <div v-if="contract.effective_date">
                                <dt class="text-xs font-medium text-zinc-500">Effective Date</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.effective_date) }}
                                </dd>
                            </div>
                            <div v-if="contract.expiry_date" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Expiry Date</dt>
                                <dd class="text-sm" :class="{'text-red-600 dark:text-red-400': isExpiring, 'text-zinc-900 dark:text-white': !isExpiring}">
                                    {{ formatDate(contract.expiry_date) }}
                                    <span v-if="daysUntilExpiry > 0" class="text-xs block mt-1">
                                        ({{ daysUntilExpiry }} days remaining)
                                    </span>
                                </dd>
                            </div>
                            <div v-if="contract.signature_date" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Signature Date</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.signature_date) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Parties -->
                    <div v-if="contract.parties && contract.parties.length > 0" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Parties</h3>
                        <div class="space-y-3">
                            <div
                                v-for="(party, index) in contract.parties"
                                :key="index"
                                class="bg-amber-50 dark:bg-zinc-700/50 rounded-lg p-4"
                            >
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ party.name }}</p>
                                <p v-if="party.role" class="text-sm text-zinc-600 dark:text-zinc-400">{{ party.role }}</p>
                                <p v-if="party.contact" class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">{{ party.contact }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Details -->
                    <div v-if="contract.contract_value" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Financial Details</h3>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span class="text-green-700 dark:text-green-300 font-medium">Contract Value</span>
                                <span class="text-2xl font-bold text-green-900 dark:text-green-100">
                                    {{ formatCurrency(contract.contract_value) }}
                                </span>
                            </div>
                            <div v-if="contract.payment_schedule && contract.payment_schedule.length > 0" class="mt-4">
                                <p class="text-sm text-green-700 dark:text-green-300 mb-2">Payment Schedule:</p>
                                <ul class="space-y-1 text-sm text-green-800 dark:text-green-200">
                                    <li v-for="(payment, index) in contract.payment_schedule" :key="index">
                                        {{ paymentLabel(payment) }}: {{ formatCurrency(payment.amount) }}
                                        <span v-if="paymentDate(payment)">- {{ formatDate(paymentDate(payment)) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Key Terms -->
                    <div v-if="contract.key_terms && contract.key_terms.length > 0" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Key Terms</h3>
                        <ul class="space-y-2">
                            <li v-for="(term, index) in contract.key_terms" :key="index" class="flex items-start">
                                <svg class="h-5 w-5 text-blue-500 dark:text-blue-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ term }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Obligations -->
                    <div v-if="contract.obligations && contract.obligations.length > 0" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Obligations</h3>
                        <div class="space-y-3">
                            <div
                                v-for="(obligation, index) in contract.obligations"
                                :key="index"
                                class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4"
                            >
                                <p v-if="obligation.party" class="text-sm text-yellow-800 dark:text-yellow-300 font-medium">
                                    {{ obligation.party }}
                                </p>
                                <p class="text-zinc-700 dark:text-zinc-300 mt-1">{{ obligation.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Renewal & Termination -->
                    <div v-if="contract.renewal_terms || contract.termination_conditions" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Terms & Conditions</h3>
                        <dl class="space-y-4">
                            <div v-if="contract.renewal_terms">
                                <dt class="text-xs font-medium text-zinc-500">Renewal Terms</dt>
                                <dd class="text-sm text-zinc-700 dark:text-zinc-300 mt-1 whitespace-pre-wrap">{{ contract.renewal_terms }}</dd>
                            </div>
                            <div v-if="contract.termination_conditions" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Termination Conditions</dt>
                                <dd class="text-sm text-zinc-700 dark:text-zinc-300 mt-1 whitespace-pre-wrap">{{ contract.termination_conditions }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Legal Information -->
                    <div v-if="contract.governing_law || contract.jurisdiction" class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Legal Information</h3>
                        <dl class="space-y-3">
                            <div v-if="contract.governing_law">
                                <dt class="text-xs font-medium text-zinc-500">Governing Law</dt>
                                <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ contract.governing_law }}</dd>
                            </div>
                            <div v-if="contract.jurisdiction" class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Jurisdiction</dt>
                                <dd class="text-sm text-zinc-900 dark:text-zinc-100">{{ contract.jurisdiction }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Tags -->
                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-amber-200 dark:border-zinc-700">
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-200 mb-4">Tags</h3>
                        <TagManager
                            v-model="contractTags"
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
                            v-model="contractCollections"
                            placeholder="Add to collections..."
                            :allow-create="true"
                            @update:model-value="handleCollectionsChanged"
                        />
                        <div v-if="contract.collections && contract.collections.length > 0" class="mt-3 flex flex-wrap gap-2">
                            <CollectionBadge
                                v-for="collection in contract.collections"
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
                                    {{ formatFileSize(contract.file?.size || 0) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Uploaded to PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.file?.uploaded_at || contract.created_at) }}
                                </dd>
                            </div>
                            <div v-if="contract.file?.file_created_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Created</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.file.file_created_at) }}
                                </dd>
                            </div>
                            <div v-if="contract.file?.file_modified_at">
                                <dt class="text-xs font-medium text-zinc-500">Original File Modified</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.file.file_modified_at) }}
                                </dd>
                            </div>
                            <div class="border-t border-amber-200 dark:border-zinc-700 pt-3">
                                <dt class="text-xs font-medium text-zinc-500">Last Updated in PaperPulse</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">
                                    {{ formatDate(contract.updated_at) }}
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
                            Delete Contract
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Contract Preview -->
            <div class="w-1/2 bg-amber-50 dark:bg-zinc-900 overflow-auto">
                <DocumentImage
                    :file="contract.file"
                    :alt-text="contract.contract_title || 'Contract'"
                    error-message="Failed to load contract preview"
                    no-image-message="No contract preview available"
                    :show-pdf-button="true"
                    pdf-button-position="fixed bottom-6 right-6"
                />
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-white">
                    Delete Contract
                </h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete this contract? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="showDeleteModal = false"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md font-semibold text-xs text-zinc-700 dark:text-zinc-300 uppercase tracking-widest hover:bg-amber-50 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteContract"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
