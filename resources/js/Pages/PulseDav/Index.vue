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
                            <div class="flex space-x-2">
                                <button
                                    @click="toggleView"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
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
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    <svg v-if="syncing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ syncing ? 'Syncing...' : 'Sync Files' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="syncMessage" class="mt-4 p-4 rounded-md" :class="syncSuccess ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
                            {{ syncMessage }}
                        </div>
                    </div>
                </div>

                <!-- Import Controls -->
                <div v-if="hasSelections" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Import Selected Items</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">File Type</label>
                                <select
                                    v-model="importOptions.fileType"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                >
                                    <option value="receipt">Receipt</option>
                                    <option value="document">Document</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                                <TagSelector
                                    v-model="importOptions.tagIds"
                                    :tags="tags"
                                    @create-tag="createTag"
                                />
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea
                                v-model="importOptions.notes"
                                rows="2"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                placeholder="Add any notes about this import..."
                            />
                        </div>
                        
                        <div class="mt-4 flex justify-between items-center">
                            <span class="text-sm text-gray-600">
                                {{ selectedCount }} item{{ selectedCount > 1 ? 's' : '' }} selected
                            </span>
                            <div class="flex space-x-2">
                                <button
                                    @click="clearSelections"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
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
                <div v-if="viewMode === 'folder'" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Folder Structure</h3>
                        
                        <!-- Breadcrumb -->
                        <div v-if="currentPath" class="mb-4 flex items-center text-sm">
                            <button @click="navigateToFolder('')" class="text-indigo-600 hover:text-indigo-800">
                                Root
                            </button>
                            <span v-for="(segment, index) in pathSegments" :key="index" class="flex items-center">
                                <svg class="w-4 h-4 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <button
                                    @click="navigateToFolder(pathSegments.slice(0, index + 1).join('/'))"
                                    class="text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ segment }}
                                </button>
                            </span>
                        </div>
                        
                        <div v-if="loadingFolders" class="text-center py-8">
                            <svg class="animate-spin h-8 w-8 mx-auto text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-gray-600">Loading folder structure...</p>
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
                        
                        <div v-if="!loadingFolders && currentFolderContents.length === 0" class="text-center py-8 text-gray-500">
                            No files or folders found in this location.
                        </div>
                    </div>
                </div>

                <!-- List View (Original Table) -->
                <div v-else class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Scanner Files</h3>
                            <div class="flex items-center space-x-4">
                                <div v-if="selectedFiles.length > 0" class="flex items-center space-x-2">
                                    <label class="text-sm font-medium text-gray-700">Process as:</label>
                                    <select
                                        v-model="selectedFileType"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm"
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
                                            Folder
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Size
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Uploaded
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
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
                                    <tr v-for="file in files.data" :key="file.id" v-if="!file.is_folder">
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
                                            {{ file.folder_path || '/' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatFileSize(file.size) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(file.uploaded_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
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
                                            <Link
                                                v-if="file.document_id"
                                                :href="route('documents.show', file.document_id)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3"
                                            >
                                                View Document
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
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TagSelector from '@/Components/TagSelector.vue';
import FolderItem from '@/Components/FolderItem.vue';

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
        return selectedFiles.value.includes(item.id);
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
        const index = selectedFiles.value.indexOf(item.id);
        if (index > -1) {
            selectedFiles.value.splice(index, 1);
        } else {
            selectedFiles.value.push(item.id);
        }
    }
};

const clearSelections = () => {
    selectedFiles.value = [];
    selectedFolders.value = [];
};

const importSelections = async () => {
    if (!hasSelections.value) return;

    importing.value = true;

    try {
        // Build selections array
        const selections = [
            ...selectedFolders.value.map(f => ({ s3_path: f.s3_path })),
            ...props.files.data.filter(f => selectedFiles.value.includes(f.id)).map(f => ({ s3_path: f.s3_path }))
        ];

        const response = await fetch(route('pulsedav.import'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                selections: selections,
                file_type: importOptions.value.fileType,
                tag_ids: importOptions.value.tagIds,
                notes: importOptions.value.notes,
            }),
        });

        const data = await response.json();
        
        if (response.ok) {
            clearSelections();
            importOptions.value.tagIds = [];
            importOptions.value.notes = '';
            router.visit(route('jobs.index'));
        } else {
            alert(data.error || 'Failed to import files');
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
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        folder: 'bg-gray-100 text-gray-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>