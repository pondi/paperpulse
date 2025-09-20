<template>
    <div class="sharing-controls">
        <!-- Share Button -->
        <button
            v-if="!readonly"
            @click="showShareModal = true"
            type="button"
            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
            <ShareIcon class="h-5 w-5 mr-2" />
            Share
        </button>

        <!-- Current Shares List -->
        <div v-if="currentShares.length > 0" class="mt-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                Shared with
            </h4>
            <div class="space-y-2">
                <div
                    v-for="share in currentShares"
                    :key="share.id"
                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <UserCircleIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ share.shared_with_user.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ share.shared_with_user.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-1 rounded-full" :class="getPermissionClasses(share.permission)">
                            {{ share.permission }}
                        </span>
                        <button
                            v-if="!readonly && canRemoveShare"
                            @click="removeShare(share)"
                            type="button"
                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Modal -->
        <Modal :show="showShareModal" @close="closeShareModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Share {{ fileType === 'document' ? 'Document' : 'Receipt' }}
                </h2>

                <div class="mt-6">
                    <!-- Share with User -->
                    <div>
                        <InputLabel for="share-email" value="Email Address" />
                        <TextInput
                            id="share-email"
                            v-model="shareForm.email"
                            type="email"
                            class="mt-1 block w-full"
                            placeholder="Enter email address"
                            :error="shareForm.errors.email"
                            @keyup.enter="shareWithUser"
                        />
                        <InputError :message="shareForm.errors.email" class="mt-2" />
                    </div>

                    <!-- Permission Selection -->
                    <div class="mt-4">
                        <InputLabel for="share-permission" value="Permission" />
                        <select
                            id="share-permission"
                            v-model="shareForm.permission"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                        >
                            <option value="view">View Only</option>
                            <option value="edit" v-if="allowEditPermission">Edit</option>
                        </select>
                        <InputError :message="shareForm.errors.permission" class="mt-2" />
                    </div>

                    <!-- Expiration Date (Optional) -->
                    <div class="mt-4">
                        <InputLabel for="share-expires" value="Expires (Optional)" />
                        <DatePicker
                            id="share-expires"
                            v-model="shareForm.expires_at"
                            :min-date="minExpirationDate"
                            placeholder="Select expiration date..."
                            :error="shareForm.errors.expires_at"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Leave blank for no expiration
                        </p>
                    </div>

                    <!-- Share Link Section -->
                    <div v-if="enableShareLinks" class="mt-6 pt-6 border-t dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                            Share Link
                        </h3>
                        <div v-if="shareLink" class="flex items-center gap-2">
                            <TextInput
                                :value="shareLink"
                                type="text"
                                class="flex-1"
                                readonly
                                @click="$event.target.select()"
                            />
                            <SecondaryButton @click="copyShareLink">
                                <ClipboardIcon v-if="!linkCopied" class="h-5 w-5" />
                                <CheckIcon v-else class="h-5 w-5 text-green-600" />
                            </SecondaryButton>
                        </div>
                        <div v-else>
                            <SecondaryButton @click="generateShareLink" :disabled="generatingLink">
                                <LinkIcon class="h-5 w-5 mr-2" />
                                Generate Share Link
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeShareModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="shareWithUser" :disabled="shareForm.processing || !shareForm.email">
                        Share
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Success/Error Messages -->
        <Transition name="fade">
            <div v-if="successMessage" class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200 rounded-md text-sm">
                {{ successMessage }}
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Common/Modal.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import InputError from '@/Components/Forms/InputError.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import DatePicker from '@/Components/Forms/DatePicker.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import {
    ShareIcon,
    UserCircleIcon,
    XMarkIcon,
    LinkIcon,
    ClipboardIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    fileId: {
        type: Number,
        required: true,
    },
    fileType: {
        type: String,
        required: true,
        validator: (value) => ['document', 'receipt'].includes(value),
    },
    currentShares: {
        type: Array,
        default: () => [],
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    canRemoveShare: {
        type: Boolean,
        default: true,
    },
    allowEditPermission: {
        type: Boolean,
        default: true,
    },
    enableShareLinks: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['share-added', 'share-removed', 'shares-updated']);

const showShareModal = ref(false);
const shareLink = ref('');
const generatingLink = ref(false);
const linkCopied = ref(false);
const successMessage = ref('');

const shareForm = useForm({
    email: '',
    permission: 'view',
    expires_at: '',
});

// Minimum expiration date is tomorrow
const minExpirationDate = computed(() => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return tomorrow.toISOString().slice(0, 10);
});

// Get permission badge classes
const getPermissionClasses = (permission) => {
    return permission === 'edit'
        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-200';
};

// Share with user
const shareWithUser = () => {
    const route_name = props.fileType === 'document' 
        ? 'documents.share' 
        : 'receipts.share';
    
    shareForm.post(route(route_name, props.fileId), {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Successfully shared!';
            shareForm.reset();
            emit('share-added');
            
            // Clear success message after 3 seconds
            setTimeout(() => {
                successMessage.value = '';
            }, 3000);
            
            // Refresh shares list
            loadShares();
        },
    });
};

// Remove share
const removeShare = (share) => {
    if (!confirm('Are you sure you want to remove this share?')) {
        return;
    }

    const route_name = props.fileType === 'document' 
        ? 'documents.unshare' 
        : 'receipts.unshare';
    
    router.delete(route(route_name, [props.fileId, share.shared_with_user.id]), {
        preserveScroll: true,
        onSuccess: () => {
            emit('share-removed', share);
            loadShares();
        },
    });
};

// Generate share link
const generateShareLink = async () => {
    if (!props.enableShareLinks) return;
    
    generatingLink.value = true;
    try {
        const response = await axios.post(`/api/${props.fileType}s/${props.fileId}/share-link`, {
            permission: shareForm.permission,
            expires_at: shareForm.expires_at,
        });
        
        shareLink.value = response.data.link;
    } catch (error) {
        console.error('Failed to generate share link:', error);
    } finally {
        generatingLink.value = false;
    }
};

// Copy share link to clipboard
const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(shareLink.value);
        linkCopied.value = true;
        
        setTimeout(() => {
            linkCopied.value = false;
        }, 2000);
    } catch (error) {
        console.error('Failed to copy link:', error);
    }
};

// Load current shares
const loadShares = async () => {
    try {
        const response = await axios.get(`/api/${props.fileType}s/${props.fileId}/shares`);
        emit('shares-updated', response.data);
    } catch (error) {
        console.error('Failed to load shares:', error);
    }
};

// Close modal and reset
const closeShareModal = () => {
    showShareModal.value = false;
    shareForm.reset();
    shareLink.value = '';
};

// Clear link copied status when modal closes
watch(showShareModal, (newValue) => {
    if (!newValue) {
        linkCopied.value = false;
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>