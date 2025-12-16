<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                Scanner Imports (PulseDav)
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Sync Section -->
                <div class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Sync Scanner Files</h3>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    Check for new files uploaded by your scanner
                                </p>
                            </div>
                            <div class="flex space-x-2">
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
                                    {{ viewMode === 'list' ? 'Folder View' : 'List View' }}
                                </button>
                                <button
                                    @click="syncFiles"
                                    :disabled="syncing"
                                    class="inline-flex items-center px-4 py-2 bg-zinc-900 dark:bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-800 focus:bg-zinc-800 active:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    <svg v-if="syncing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ syncing ? 'Syncing...' : 'Sync Files' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="syncMessage" class="mt-4 p-4 rounded-md" :class="syncSuccess ? 'bg-green-50 dark:bg-green-900/50 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/50 text-red-800 dark:text-red-200'">
                            {{ syncMessage }}
                        </div>
                    </div>
                </div>

                <!-- Import Controls -->
                <div v-if="hasSelections" class="bg-white dark:bg-zinc-800 overflow-hidden shadow-lg sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Import Selected Items</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">File Type</label>
                                <select
                                    v-model="importOptions.fileType"
                                    class="w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                >
                                    <option value="receipt">Receipt</option>
                                    <option value="document">Document</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Tags</label>
                                <TagSelector
                                    v-model="importOptions.tagIds"
                                    :tags="tags"
                                    @create-tag="createTag"
                                />
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Notes (optional)</label>
                            <textarea
                                v-model="importOptions.notes"
                                rows="2"
                                class="w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                placeholder="Add any notes about this import..."
                            />
                        </div>
                        
                        <div class="mt-4 flex justify-between items-center">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ selectedCount }} item{{ selectedCount > 1 ? 's' : '' }} selected
                            </span>
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
                                    {{ importing ? 'Importing...' : 'Import' }}
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
                        
                        <div v-else class="space-y-2">
                            <FolderItem
                                v-for="item in currentFolderContents"
                                :key="item.s3_path || item.path"
                                :item="item"
                                :selected="isSelected(item)"
                                @toggle-selection="toggleSelection(item)"
                                @navigate="navigateToFolder"
                                @update-tags="updateFolderTags"
                            />
                        </div>
                        
                        <div v-if="!loadingFolders && currentFolderContents.length === 0" class="text-center py-8 text-zinc-500 dark:text-zinc-400">
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
                                <div v-if="selectedFiles.length > 0" class="flex items-center space-x-2">
                                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Process as:</label>
                                    <select
                                        v-model="selectedFileType"
                                        class="rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50 text-sm"
                                    >
                                        <option value="receipt">Receipt</option>
                                        <option value="document">Document</option>
                                    </select>
                                </div>
                                <button
                                    v-if="selectedFiles.length > 0"
                                    @click="processSelectedFiles"
                                    :disabled="processing"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    Process {{ selectedFiles.length }} File{{ selectedFiles.length > 1 ? 's' : '' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="files.data.length === 0" class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            No files found. Click "Sync Files" to check for new scanner uploads.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-amber-200 dark:divide-gray-600">
                                <thead class="bg-amber-50 dark:bg-zinc-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left">
                                            <input
                                                type="checkbox"
                                                @change="toggleAll"
                                                :checked="selectedFiles.length === selectableFiles.length && selectableFiles.length > 0"
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
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TagSelector from '@/Components/Domain/TagSelector.vue';
import FolderItem from '@/Components/Features/FolderItem.vue';

const props = defineProps({
    files: Object,
    tags: Array,
});

// View mode
const viewMode = ref('list');
const currentPath = ref('');
const folderHierarchy = ref([]);
const currentFolderContents = ref([]);
const loadingFolders = ref(false);

// Sync state
const syncing = ref(false);
const syncMessage = ref('');
const syncSuccess = ref(false);

// Selection state
const selectedFiles = ref([]);
const selectedFolders = ref([]);
const selectedFileType = ref('receipt');
const processing = ref(false);
const importing = ref(false);

// Import options
const importOptions = ref({
    fileType: 'receipt',
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

const hasSelections = computed(() => {
    return selectedFiles.value.length > 0 || selectedFolders.value.length > 0;
});

const selectedCount = computed(() => {
    return selectedFiles.value.length + selectedFolders.value.length;
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
        selectedFiles.value = selectableFiles.value.map(file => file.id);
    } else {
        selectedFiles.value = [];
    }
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

const loadFolderStructure = async () => {
    loadingFolders.value = true;
    
    try {
        const response = await fetch(route('pulsedav.folders'), {
            headers: {
                'Accept': 'application/json',
            },
        });

        const data = await response.json();
        folderHierarchy.value = data.hierarchy;
        navigateToFolder('');
    } catch (error) {
        console.error('Failed to load folder structure:', error);
    } finally {
        loadingFolders.value = false;
    }
};

const navigateToFolder = async (folderPath) => {
    currentPath.value = folderPath;
    
    if (folderPath === '') {
        // Show root level items
        currentFolderContents.value = folderHierarchy.value;
    } else {
        // Load folder contents
        loadingFolders.value = true;
        
        try {
            const response = await fetch(route('pulsedav.folder-contents', { folder_path: folderPath }), {
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            currentFolderContents.value = data.contents;
        } catch (error) {
            console.error('Failed to load folder contents:', error);
        } finally {
            loadingFolders.value = false;
        }
    }
};

const isSelected = (item) => {
    if (item.is_folder) {
        return selectedFolders.value.some(f => f.s3_path === item.s3_path);
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
        const index = selectedFolders.value.findIndex(f => f.s3_path === item.s3_path);
        if (index > -1) {
            selectedFolders.value.splice(index, 1);
        } else {
            selectedFolders.value.push(item);
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
};

const importSelections = async () => {
    if (import.meta.env.DEV) console.log('=== PulseDav Import Started ===');
    if (import.meta.env.DEV) console.log('Has selections:', hasSelections.value);
    if (import.meta.env.DEV) console.log('Selected files:', selectedFiles.value);
    if (import.meta.env.DEV) console.log('Selected folders:', selectedFolders.value);
    
    if (!hasSelections.value) {
        alert('Please select at least one file or folder to import.');
        return;
    }

    importing.value = true;

    try {
        // Build selections array
        const fileSelections = selectedFiles.value.map(f => {
            if (typeof f === 'object' && f.s3_path) {
                // File object from folder view
                if (import.meta.env.DEV) console.log('File from folder view:', f);
                return { s3_path: f.s3_path };
            } else if (typeof f === 'string' || typeof f === 'number') {
                // File ID from list view
                const file = props.files.data.find(file => file.id === f);
                if (import.meta.env.DEV) console.log('File from list view:', f, 'found:', file);
                return file ? { s3_path: file.s3_path } : null;
            }
            return null;
        }).filter(Boolean);

        const selections = [
            ...selectedFolders.value.map(f => ({ s3_path: f.s3_path })),
            ...fileSelections
        ];

        if (import.meta.env.DEV) console.log('Built selections:', selections);
        
        // Double-check we have selections
        if (selections.length === 0) {
            alert('No files or folders selected. Please select items to import.');
            importing.value = false;
            return;
        }

        const requestBody = {
            selections: selections,
            file_type: importOptions.value.fileType,
            tag_ids: importOptions.value.tagIds,
            notes: importOptions.value.notes,
        };
        
        if (import.meta.env.DEV) console.log('Request body:', requestBody);

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
        if (import.meta.env.DEV) console.log('Response status:', response.status);
        if (import.meta.env.DEV) console.log('Response data:', data);
        
        if (response.ok) {
            if (import.meta.env.DEV) console.log('Import successful, redirecting to file processing page');
            clearSelections();
            importOptions.value.tagIds = [];
            importOptions.value.notes = '';
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
            
            alert(errorMessage);
        }
    } catch (error) {
        console.error('Failed to import files:', error);
        alert('Failed to import files. Please try again.');
    } finally {
        importing.value = false;
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
                file_type: selectedFileType.value,
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

const createTag = async (tagName) => {
    try {
        const response = await fetch(route('pulsedav.tags.create'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: tagName,
            }),
        });

        const data = await response.json();
        
        if (response.ok) {
            // Add the new tag to the list and select it
            props.tags.push(data.tag);
            importOptions.value.tagIds.push(data.tag.id);
        }
    } catch (error) {
        console.error('Failed to create tag:', error);
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
        pending: 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200',
        processing: 'bg-amber-100 dark:bg-orange-900/50 text-amber-800 dark:text-amber-200',
        completed: 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
        failed: 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200',
        folder: 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200',
    };
    return classes[status] || 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200';
};
</script>
