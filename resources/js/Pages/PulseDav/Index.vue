<template>
    <AuthenticatedLayout>
        <Head title="Scanner imports" />
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                Scanner inbox
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Sync Section -->
                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Check for new scans</h3>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Check your private scanner inbox for new uploads. This does not use your device camera or local files.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    @click="toggleView"
                                    class="inline-flex items-center px-4 py-2 bg-zinc-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700 focus:bg-zinc-700 active:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg v-if="viewMode === 'list'" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    </svg>
                                    {{ viewMode === 'list' ? 'Browse folders' : 'Browse as list' }}
                                </button>
                                <button
                                    @click="syncFiles"
                                    :disabled="syncing || !scannerImportsEnabled"
                                    class="inline-flex items-center px-4 py-2 bg-zinc-900 dark:bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-800 focus:bg-zinc-800 active:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    <svg v-if="syncing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ syncing ? 'Checking inbox…' : 'Check scanner inbox' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="syncMessage" class="mt-4 p-4 rounded-md" :role="syncSuccess ? 'status' : 'alert'" aria-live="polite" :class="syncSuccess ? 'bg-green-50 dark:bg-green-900/50 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/50 text-red-800 dark:text-red-200'">
                            {{ syncMessage }}
                        </div>
                    </div>
                </div>

                <!-- Import Controls -->
                <div v-if="hasFolderSelections" class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Import selection</h3>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400" aria-live="polite">
                            {{ selectedFileCount }} file{{ selectedFileCount === 1 ? '' : 's' }} and {{ selectedFolderCount }} folder{{ selectedFolderCount === 1 ? '' : 's' }} selected.
                            <span v-if="viewMode === 'folder'">Current folder: {{ currentPath || 'Scanner inbox root' }}.</span>
                        </p>
                        <details class="mt-4">
                            <summary class="cursor-pointer font-medium text-amber-700 dark:text-amber-400">Optional import settings</summary>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="scanner-file-type" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Import as</label>
                                    <select id="scanner-file-type" v-model="selectedFileType" class="w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50">
                                        <option value="receipt">Receipt</option>
                                        <option value="document">Document</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Tags</label>
                                    <TagSelector v-model="importOptions.tagIds" />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="scanner-import-notes" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Notes (optional)</label>
                                    <textarea id="scanner-import-notes" v-model="importOptions.notes" rows="2" class="w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50" placeholder="Add any notes about this import..." />
                                </div>
                            </div>
                        </details>
                        <div v-if="importMessage" class="mt-4 rounded-md p-3 text-sm" :role="importSuccess ? 'status' : 'alert'" aria-live="polite" :class="importSuccess ? 'bg-green-50 dark:bg-green-900/50 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/50 text-red-800 dark:text-red-200'">{{ importMessage }}</div>
                        
                        <div class="mt-4 flex justify-end items-center">
                            <div class="flex space-x-2">
                                <button
                                    @click="clearSelections"
                                    class="inline-flex items-center px-4 py-2 bg-zinc-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                                >
                                    Clear
                                </button>
                                <button
                                    @click="importSelections"
                                    :disabled="importing"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 disabled:opacity-50"
                                >
                                    <svg v-if="importing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ importing ? 'Importing…' : `Import ${selectedFileCount} file${selectedFileCount === 1 ? '' : 's'}${selectedFolderCount ? ` and ${selectedFolderCount} folder${selectedFolderCount === 1 ? '' : 's'}` : ''}` }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Folder View -->
                <div v-if="viewMode === 'folder'" class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Folder Structure</h3>
                        
                        <!-- Breadcrumb -->
                        <div v-if="currentPath" class="mb-4 flex items-center text-sm">
                            <button @click="navigateToFolder('')" class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300">
                                Root
                            </button>
                            <span v-for="(segment, index) in pathSegments" :key="index" class="flex items-center">
                                <svg class="w-4 h-4 mx-2 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <button
                                    @click="navigateToFolder(pathSegments.slice(0, index + 1).join('/'))"
                                    class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                >
                                    {{ segment }}
                                </button>
                            </span>
                        </div>
                        
                        <div v-if="loadingFolders" class="text-center py-8">
                            <svg class="animate-spin h-8 w-8 mx-auto text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-zinc-600 dark:text-zinc-400">Loading folder structure...</p>
                        </div>
                        
                        <div v-else-if="folderError" role="alert" class="text-sm text-red-700 dark:text-red-300">
                            {{ folderError }}
                            <button type="button" @click="retryFolderLoad" class="ml-2 underline">Try again</button>
                        </div>
                        <div v-else>
                            <div v-if="currentFolderFiles.length > 0" class="mb-4">
                                <label class="flex items-center space-x-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    <input
                                        type="checkbox"
                                        :checked="areAllFolderFilesSelected"
                                        @change="toggleSelectAllFolderFiles"
                                        class="rounded border-zinc-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                    />
                                    <span>
                                        Select all {{ currentFolderFiles.length }} file{{ currentFolderFiles.length === 1 ? '' : 's' }} in this folder
                                    </span>
                                </label>
                            </div>
                            <div class="space-y-2">
                                <FolderItem
                                    v-for="item in currentFolderContents"
                                    :key="item.s3_path || item.path"
                                    :item="item"
                                    :current-path="currentPath"
                                    :selected="isSelected(item)"
                                    @toggle-selection="toggleSelection(item)"
                                    @navigate="navigateToFolder"
                                    @update-tags="updateFolderTags"
                                />
                            </div>
                        </div>
                        
                        <div v-if="!loadingFolders && !folderError && currentFolderContents.length === 0" class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            No files or folders found in this location.
                        </div>
                    </div>
                </div>

                <!-- List View (Original Table) -->
                <div v-else class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Scanner Files</h3>
                            <div class="flex items-center space-x-4">
                                <div v-if="selectedListFileCount > 0" class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ selectedListFileCount }} file{{ selectedListFileCount === 1 ? '' : 's' }} selected
                                </div>
                                <div v-if="selectedListFileCount > 0" class="flex items-center gap-2">
                                    <label for="scanner-process-file-type" class="sr-only">Process as</label>
                                    <select id="scanner-process-file-type" v-model="selectedFileType" aria-label="Process selected files as" class="rounded-md border-zinc-300 text-sm shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200">
                                        <option value="receipt">Receipt</option>
                                        <option value="document">Document</option>
                                    </select>
                                </div>
                                <button
                                    v-if="selectedListFileCount > 0"
                                    @click="processSelectedFiles"
                                    :disabled="processing"
                                    class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50"
                                >
                                    {{ processing ? 'Processing…' : `Process ${selectedListFileCount} file${selectedListFileCount === 1 ? '' : 's'}` }}
                                </button>
                            </div>
                        </div>

                        <div v-if="processMessage" class="mt-4 rounded-md p-3 text-sm" :role="processSuccess ? 'status' : 'alert'" aria-live="polite" :class="processSuccess ? 'bg-green-50 dark:bg-green-900/50 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/50 text-red-800 dark:text-red-200'">{{ processMessage }}</div>

                        <div v-if="files.data.length === 0" class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">No scanner imports yet</h3>
                            <p class="mt-2">{{ scannerImportsEnabled ? 'Scanner imports are enabled. Connect your scanner, send a file, then choose Sync Files.' : 'Scanner imports are not configured on this server. Contact your administrator to enable PulseDav before connecting a scanner.' }}</p>
                            <details class="mt-4 text-left">
                                <summary class="cursor-pointer font-medium text-amber-700 dark:text-amber-400">Scanner connection instructions</summary>
                                <ol class="mt-3 flex list-decimal flex-col gap-2 pl-5">
                                    <li>Get your PulseDav WebDAV server address from your administrator.</li>
                                    <li>Choose WebDAV as the upload destination in your scanner or scanning app.</li>
                                    <li>Enter that server address and sign in with your verified PaperPulse email address and password.</li>
                                    <li>Send a scan, then return here and choose Sync Files to find it in your private scanner inbox.</li>
                                </ol>
                            </details>
                            <Link :href="route('preferences.index') + '#preferences-scanner'" class="mt-4 inline-block font-medium text-amber-700 hover:underline dark:text-amber-400">Scanner processing settings</Link>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-200 dark:divide-gray-600">
                                <thead class="bg-amber-50 dark:bg-zinc-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                @change="toggleAll"
                                                :checked="selectedListFileCount === selectableFiles.length && selectableFiles.length > 0"
                                                aria-label="Select all scanner files on this page"
                                                class="rounded border-zinc-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                            />
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Filename
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Folder
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Size
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Uploaded
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Import Status
                                        </th>
                                        <th class="px-6 py-3 bg-amber-50 dark:bg-zinc-800 text-left text-xs font-bold text-zinc-600 dark:text-zinc-300 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-amber-200 dark:divide-gray-600">
                                    <template v-for="file in files.data" :key="file.id">
                                        <tr v-if="!file.is_folder">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input
                                                v-if="isSelectable(file)"
                                                type="checkbox"
                                                :value="file.id"
                                                :aria-label="`Select ${file.filename}`"
                                                v-model="selectedFiles"
                                                class="rounded border-zinc-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                            />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ file.filename }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ file.folder_path || '/' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ formatFileSize(file.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ formatDate(file.uploaded_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200">
                                                {{ file.file_type || 'receipt' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="getStatusClass(file.status)"
                                            >
                                                {{ file.status }}
                                            </span>
                                            <span v-if="file.error_message" class="block text-xs text-red-600 dark:text-red-400 mt-1">
                                                {{ file.error_message }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                v-if="file.receipt_id"
                                                :href="route('receipts.show', file.receipt_id)"
                                                class="text-amber-600 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300 mr-3"
                                            >
                                                View Receipt
                                            </Link>
                                            <Link
                                                v-if="file.document_id"
                                                :href="route('documents.show', file.document_id)"
                                                class="text-amber-600 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300 mr-3"
                                            >
                                                View Document
                                            </Link>
                                            <button
                                                @click="deleteFile(file)"
                                                class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                    </template>
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
                                            ? 'bg-zinc-900 dark:bg-amber-600 text-white'
                                            : 'bg-white dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-amber-50 dark:hover:bg-amber-600',
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
import { useDateFormatter } from '@/Composables/useDateFormatter';
const { formatDate } = useDateFormatter();
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TagSelector from '@/Components/Domain/TagSelector.vue';
import FolderItem from '@/Components/Features/FolderItem.vue';

const props = defineProps({
    scannerImportsEnabled: Boolean,
    initialView: String,
    files: Object,
    tags: Array,
});

// View mode
const viewMode = ref(props.initialView);
const currentPath = ref('');
const folderHierarchy = ref([]);
const currentFolderContents = ref([]);
const loadingFolders = ref(false);
const folderError = ref('');

// Sync state
const syncing = ref(false);
const syncMessage = ref('');
const syncSuccess = ref(false);
const importMessage = ref('');
const importSuccess = ref(false);
const processMessage = ref('');
const processSuccess = ref(false);

// Selection state
const selectedFiles = ref([]);
const selectedFolders = ref([]);
const selectedFileType = ref('receipt');
const processing = ref(false);
const importing = ref(false);
let folderRequest = 0;

// Import options
const importOptions = ref({
    tagIds: [],
    notes: '',
});

// Computed properties
const selectableFiles = computed(() => {
    return props.files.data.filter(file => isSelectable(file) && !file.is_folder);
});

const pathSegments = computed(() => {
    return currentPath.value ? currentPath.value.split('/').filter(s => s) : [];
});

const selectionPath = (item) => item.s3_path || item.metadata?.s3_path || item.path;

const selectedFolderFiles = computed(() => selectedFiles.value.filter(selection => typeof selection === 'object' && selection !== null));
const selectedListFileIds = computed(() => selectedFiles.value.filter(selection => typeof selection === 'string' || typeof selection === 'number'));
const hasFolderSelections = computed(() => selectedFolderFiles.value.length > 0 || selectedFolders.value.length > 0);

const selectedFileCount = computed(() => selectedFolderFiles.value.length);
const selectedFolderCount = computed(() => selectedFolders.value.length);
const selectedListFileCount = computed(() => selectedListFileIds.value.length);

const isFolderFileSelectable = (item) => {
    if (item.is_folder) {
        return false;
    }

    if (!item.status) {
        return true;
    }

    return isSelectable(item);
};

const currentFolderFiles = computed(() => {
    return currentFolderContents.value.filter(isFolderFileSelectable);
});

const areAllFolderFilesSelected = computed(() => {
    if (currentFolderFiles.value.length === 0) {
        return false;
    }
    return currentFolderFiles.value.every(item => isSelected(item));
});

// Methods
const isSelectable = (file) => {
    return ['pending', 'failed'].includes(file.status);
};

const toggleView = () => {
    viewMode.value = viewMode.value === 'list' ? 'folder' : 'list';
    if (viewMode.value === 'folder' && folderHierarchy.value.length === 0) {
        loadFolderStructure();
    }
};

const toggleAll = (event) => {
    if (event.target.checked) {
        selectedFiles.value = [
            ...selectedFolderFiles.value,
            ...selectableFiles.value.map(file => file.id),
        ];
    } else {
        selectedFiles.value = selectedFolderFiles.value;
    }
};

const toggleSelectAllFolderFiles = () => {
    const folderFiles = currentFolderFiles.value;
    if (folderFiles.length === 0) {
        return;
    }

    if (areAllFolderFilesSelected.value) {
        const folderPaths = new Set(folderFiles.map(file => file.s3_path || file.path));
        selectedFiles.value = selectedFiles.value.filter(selection => {
            if (selection && typeof selection === 'object') {
                const selectionPath = selection.s3_path || selection.path;
                return !folderPaths.has(selectionPath);
            }
            return true;
        });
        return;
    }

    const existingPaths = new Set(
        selectedFiles.value
            .filter(selection => selection && typeof selection === 'object')
            .map(selection => selection.s3_path || selection.path)
    );

    const newSelections = folderFiles
        .filter(file => {
            const path = file.s3_path || file.path;
            return !existingPaths.has(path);
        })
        .map(file => ({
            ...file,
            s3_path: file.s3_path || file.path,
        }));

    if (newSelections.length === 0) {
        return;
    }

    selectedFiles.value = [...selectedFiles.value, ...newSelections];
};

const syncFiles = async () => {
    syncing.value = true;
    syncMessage.value = '';
    
    try {
        const response = await fetch(route('pulsedav.sync-folders'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });

        const data = await response.json();
        syncSuccess.value = response.ok;
        syncMessage.value = data.message || (response.ok ? 'Scanner inbox checked.' : 'Scanner inbox could not be checked. Please try again.');

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

const loadFolderStructure = async () => {
    loadingFolders.value = true;
    folderError.value = '';
    
    try {
        const response = await fetch(route('pulsedav.folders'), {
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) throw new Error('Folder request failed');
        const data = await response.json();
        folderHierarchy.value = data.hierarchy;
        navigateToFolder('');
    } catch (error) {
        folderError.value = 'Scanner folders could not be loaded. Please try again.';
    } finally {
        loadingFolders.value = false;
    }
};

const navigateToFolder = async (folderPath) => {
    const request = ++folderRequest;
    currentPath.value = folderPath;
    folderError.value = '';
    
    if (folderPath === '') {
        // Show root level items
        currentFolderContents.value = folderHierarchy.value;
        loadingFolders.value = false;
    } else {
        // Load folder contents
        loadingFolders.value = true;
        
        try {
            const response = await fetch(route('pulsedav.folder-contents', { folder_path: folderPath }), {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) throw new Error('Folder request failed');
            const data = await response.json();
            if (request === folderRequest) currentFolderContents.value = data.contents || [];
        } catch (error) {
            if (request === folderRequest) {
                currentFolderContents.value = [];
                folderError.value = 'This folder could not be loaded. Check your connection and try again.';
            }
        } finally {
            if (request === folderRequest) loadingFolders.value = false;
        }
    }
};

const retryFolderLoad = () => currentPath.value ? navigateToFolder(currentPath.value) : loadFolderStructure();

const isSelected = (item) => {
    if (item.is_folder) {
        const path = selectionPath(item);
        return selectedFolders.value.some(folder => selectionPath(folder) === path);
    } else {
        // In folder view, files might not have ID, use s3_path
        if (item.id) {
            return selectedFiles.value.includes(item.id);
        } else {
            const s3Path = item.s3_path || item.path;
            return selectedFiles.value.some(f => {
                if (typeof f === 'object') {
                    return (f.s3_path || f.path) === s3Path;
                }
                return false;
            });
        }
    }
};

const toggleSelection = (item) => {
    if (item.is_folder) {
        const path = selectionPath(item);
        const index = selectedFolders.value.findIndex(folder => selectionPath(folder) === path);
        if (index > -1) {
            selectedFolders.value.splice(index, 1);
        } else {
            selectedFolders.value.push({ ...item, s3_path: path });
        }
    } else {
        // Handle files - in folder view they might not have ID
        if (item.id) {
            // List view - use ID
            const index = selectedFiles.value.indexOf(item.id);
            if (index > -1) {
                selectedFiles.value.splice(index, 1);
            } else {
                selectedFiles.value.push(item.id);
            }
        } else {
            // Folder view - use s3_path as unique identifier
            const s3Path = item.s3_path || item.path;
            
            const index = selectedFiles.value.findIndex(f => {
                if (typeof f === 'object') {
                    return (f.s3_path || f.path) === s3Path;
                }
                return false;
            });
            
            if (index > -1) {
                selectedFiles.value.splice(index, 1);
            } else {
                selectedFiles.value.push({
                    ...item,
                    s3_path: s3Path // Ensure s3_path is set
                });
            }
        }
    }
};

const clearSelections = () => {
    selectedFiles.value = [];
    selectedFolders.value = [];
    importMessage.value = '';
};

const importSelections = async () => {
    if (!hasFolderSelections.value) {
        importSuccess.value = false;
        importMessage.value = 'Select at least one file or folder to import.';
        return;
    }

    importing.value = true;

    try {
        const selections = [
            ...selectedFolders.value.map(folder => ({ s3_path: selectionPath(folder) })),
            ...selectedFolderFiles.value.map(file => ({ s3_path: selectionPath(file) })),
        ];
        if (selections.length === 0) {
            importSuccess.value = false;
            importMessage.value = 'No selected files could be found. Refresh the scanner inbox and try again.';
            return;
        }

        const requestBody = {
            selections: selections,
            file_type: selectedFileType.value,
            tag_ids: importOptions.value.tagIds,
            notes: importOptions.value.notes,
        };
        
        const response = await fetch(route('pulsedav.import'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody),
        });

        const data = await response.json();
        
        if (response.ok) {
            selectedFiles.value = selectedFiles.value.filter(selection => typeof selection !== 'object' || selection === null);
            selectedFolders.value = [];
            importOptions.value.tagIds = [];
            importOptions.value.notes = '';
            importMessage.value = '';
            importSuccess.value = true;
            importMessage.value = 'Import queued for processing. Opening activity…';
            router.visit(route('files.index'));
        } else {
            console.error('Import failed:', data);
            
            // Build detailed error message
            let errorMessage = data.error || 'Failed to import files';
            
            if (data.errors) {
                const errorDetails = Object.entries(data.errors)
                    .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
                    .join('\n');
                errorMessage += '\n\nValidation errors:\n' + errorDetails;
            }
            
            if (data.invalid_tag_ids) {
                errorMessage += '\n\nInvalid tag IDs: ' + data.invalid_tag_ids.join(', ');
            }
            
            // Log debug info if available
            if (data.debug && import.meta.env.DEV) {
                console.group('Import Debug Information');
                console.log('Request data:', data.debug.request_data);
                if (data.debug.failed_rules) {
                    console.log('Failed validation rules:', data.debug.failed_rules);
                }
                if (data.debug.user_tag_ids) {
                    console.log('User tag IDs:', data.debug.user_tag_ids);
                    console.log('Requested tag IDs:', data.debug.requested_tag_ids);
                }
                console.groupEnd();
            }
            
            importSuccess.value = false;
            importMessage.value = errorMessage;
        }
    } catch (error) {
        console.error('Failed to import files:', error);
        importSuccess.value = false;
        importMessage.value = 'Import failed. Check your connection and try again.';
    } finally {
        importing.value = false;
    }
};

const processSelectedFiles = async () => {
    if (selectedListFileIds.value.length === 0 || processing.value) return;

    processing.value = true;
    processMessage.value = '';

    try {
        const response = await fetch(route('pulsedav.process'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_ids: selectedListFileIds.value,
                file_type: selectedFileType.value,
            }),
        });
        const data = await response.json();
        processSuccess.value = response.ok;
        processMessage.value = data.message || (response.ok ? 'Selected files queued for processing.' : 'Selected files could not be queued. Please try again.');

        if (response.ok) {
            const processedIds = new Set(selectedListFileIds.value);
            selectedFiles.value = selectedFiles.value.filter(selection => !processedIds.has(selection));
            router.reload();
        }
    } catch (error) {
        processSuccess.value = false;
        processMessage.value = 'Selected files could not be queued. Check your connection and try again.';
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

const updateFolderTags = async (folderPath, tagIds) => {
    try {
        const response = await fetch(route('pulsedav.folder-tags'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                folder_path: folderPath,
                tag_ids: tagIds,
            }),
        });

        if (response.ok) {
            // Reload to show updated tags
            if (viewMode.value === 'folder') {
                loadFolderStructure();
            }
        }
    } catch (error) {
        console.error('Failed to update folder tags:', error);
    }
};

// Helper functions
const formatFileSize = (bytes) => {
    if (!bytes) return 'N/A';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
};



onMounted(() => {
    if (viewMode.value === 'folder') loadFolderStructure();
});

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200',
        queued: 'bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200',
        handed_off: 'bg-amber-100 dark:bg-orange-900/50 text-amber-800 dark:text-amber-200',
        processing: 'bg-amber-100 dark:bg-orange-900/50 text-amber-800 dark:text-amber-200',
        completed: 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
        failed: 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200',
        folder: 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200',
    };
    return classes[status] || 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200';
};
</script>
