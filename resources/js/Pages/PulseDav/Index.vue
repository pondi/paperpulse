<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Scanner Imports (PulseDav)
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Sync Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Sync Scanner Files</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    Check for new files uploaded by your scanner
                                </p>
                            </div>
                            <button
                                @click="syncFiles"
                                :disabled="syncing"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                <svg v-if="syncing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ syncing ? 'Syncing...' : 'Sync Files' }}
                            </button>
                        </div>
                        <div v-if="syncMessage" class="mt-4 p-4 rounded-md" :class="syncSuccess ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
                            {{ syncMessage }}
                        </div>
                    </div>
                </div>

                <!-- Files Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Scanner Files</h3>
                            <button
                                v-if="selectedFiles.length > 0"
                                @click="processSelectedFiles"
                                :disabled="processing"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                Process {{ selectedFiles.length }} File{{ selectedFiles.length > 1 ? 's' : '' }}
                            </button>
                        </div>

                        <div v-if="files.data.length === 0" class="text-center py-8 text-gray-500">
                            No files found. Click "Sync Files" to check for new scanner uploads.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                @change="toggleAll"
                                                :checked="selectedFiles.length === selectableFiles.length && selectableFiles.length > 0"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Filename
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Size
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Uploaded
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="file in files.data" :key="file.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input
                                                v-if="isSelectable(file)"
                                                type="checkbox"
                                                :value="file.id"
                                                v-model="selectedFiles"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ file.filename }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatFileSize(file.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(file.uploaded_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="getStatusClass(file.status)"
                                            >
                                                {{ file.status }}
                                            </span>
                                            <span v-if="file.error_message" class="block text-xs text-red-600 mt-1">
                                                {{ file.error_message }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                v-if="file.receipt_id"
                                                :href="route('receipts.show', file.receipt_id)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                                            >
                                                View Receipt
                                            </Link>
                                            <button
                                                @click="deleteFile(file)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="files.links.length > 3" class="mt-4">
                            <nav class="flex justify-center">
                                <Link
                                    v-for="link in files.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-2 mx-1 text-sm font-medium rounded-md',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50',
                                        !link.url && 'cursor-not-allowed opacity-50'
                                    ]"
                                    v-html="link.label"
                                    :disabled="!link.url"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    files: Object,
});

const syncing = ref(false);
const syncMessage = ref('');
const syncSuccess = ref(false);
const processing = ref(false);
const selectedFiles = ref([]);

const selectableFiles = computed(() => {
    return props.files.data.filter(file => isSelectable(file));
});

const isSelectable = (file) => {
    return ['pending', 'failed'].includes(file.status);
};

const toggleAll = (event) => {
    if (event.target.checked) {
        selectedFiles.value = selectableFiles.value.map(file => file.id);
    } else {
        selectedFiles.value = [];
    }
};

const syncFiles = async () => {
    syncing.value = true;
    syncMessage.value = '';
    
    try {
        const response = await fetch(route('pulsedav.sync'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        const data = await response.json();
        syncSuccess.value = response.ok;
        syncMessage.value = data.message;

        if (response.ok && data.synced > 0) {
            router.reload();
        }
    } catch (error) {
        syncSuccess.value = false;
        syncMessage.value = 'Failed to sync files. Please try again.';
    } finally {
        syncing.value = false;
    }
};

const processSelectedFiles = async () => {
    if (selectedFiles.value.length === 0) return;

    processing.value = true;

    try {
        const response = await fetch(route('pulsedav.process'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_ids: selectedFiles.value,
            }),
        });

        const data = await response.json();
        
        if (response.ok) {
            selectedFiles.value = [];
            router.reload();
        }
    } catch (error) {
        console.error('Failed to process files:', error);
    } finally {
        processing.value = false;
    }
};

const deleteFile = async (file) => {
    if (!confirm(`Are you sure you want to delete ${file.filename}?`)) return;

    try {
        const response = await fetch(route('pulsedav.destroy', file.id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            router.reload();
        }
    } catch (error) {
        console.error('Failed to delete file:', error);
    }
};

const formatFileSize = (bytes) => {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>