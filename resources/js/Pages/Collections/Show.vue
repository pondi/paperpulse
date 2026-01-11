<template>
    <Head :title="collection.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-x-4">
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: collection.color }"
                    >
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIconPath(collection.icon)" />
                        </svg>
                    </div>
                    <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                        {{ collection.name }}
                        <span v-if="collection.is_archived" class="ml-2 text-sm font-normal text-zinc-500 dark:text-zinc-400">(Archived)</span>
                    </h2>
                </div>
                <div class="flex items-center gap-x-4">
                    <button
                        v-if="isOwner"
                        @click="isEditing = !isEditing"
                        class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-semibold rounded-md"
                        :class="isEditing ? 'text-zinc-900 bg-blue-100 hover:bg-blue-200 dark:text-zinc-100 dark:bg-zinc-700 dark:hover:bg-blue-600' : 'text-zinc-100 bg-zinc-700 hover:bg-blue-600 dark:bg-zinc-600 dark:hover:bg-zinc-500'"
                    >
                        <svg v-if="!isEditing" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <svg v-else class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isEditing ? 'Save Changes' : 'Edit Collection' }}
                    </button>
                    <Link
                        :href="route('collections.index')"
                        class="inline-flex items-center gap-x-2 px-4 py-2 bg-zinc-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-zinc-700"
                    >
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Collections
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Collection Details Card -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-lg border-l-4 p-6 mb-6" :style="{ borderLeftColor: collection.color }">
                    <div class="space-y-6">
                        <!-- Description -->
                        <div v-if="!isEditing && collection.description" class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ collection.description }}
                        </div>
                        <textarea
                            v-if="isEditing"
                            v-model="editedCollection.description"
                            rows="2"
                            placeholder="Collection description..."
                            class="block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        ></textarea>

                        <!-- Icon and Color Pickers (only in edit mode) -->
                        <div v-if="isEditing" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <IconPicker
                                v-model="editedCollection.icon"
                                label="Collection Icon"
                            />
                            <ColorPicker
                                v-model="editedCollection.color"
                                label="Collection Color"
                            />
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Total Files</dt>
                                <dd class="mt-2 text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ stats.total_files }}</dd>
                            </div>
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Documents</dt>
                                <dd class="mt-2 text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ stats.documents_count }}</dd>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
                                <dt class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Receipts</dt>
                                <dd class="mt-2 text-2xl font-black text-zinc-900 dark:text-zinc-100">{{ stats.receipts_count }}</dd>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Files Grid -->
                <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Files in this Collection</h3>
                    </div>

                    <div v-if="collection.files && collection.files.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="file in collection.files"
                            :key="file.id"
                            class="relative group bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 hover:shadow-md transition-shadow border border-zinc-200 dark:border-zinc-600"
                        >
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ file.fileName || file.original_filename || 'Unnamed File' }}
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                        {{ getFileType(file) }}
                                    </p>
                                </div>
                                <button
                                    v-if="isOwner"
                                    @click="removeFile(file.id)"
                                    class="opacity-0 group-hover:opacity-100 transition-opacity text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mt-2">
                                <span>{{ formatDate(file.created_at) }}</span>
                                <Link
                                    :href="getFileUrl(file)"
                                    class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    View
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">No files</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            This collection doesn't have any files yet. Add files by uploading new documents or editing existing ones.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IconPicker from '@/Components/Forms/IconPicker.vue';
import ColorPicker from '@/Components/Forms/ColorPicker.vue';

const props = defineProps({
    collection: {
        type: Object,
        required: true
    },
    stats: {
        type: Object,
        required: true
    },
    isOwner: {
        type: Boolean,
        default: false
    }
});

const isEditing = ref(false);
const editedCollection = ref({
    description: props.collection.description || '',
    icon: props.collection.icon,
    color: props.collection.color
});

// Auto-save when exiting edit mode
watch(isEditing, (newValue) => {
    if (!newValue) {
        // Exiting edit mode - save changes
        router.patch(route('collections.update', props.collection.id), editedCollection.value, {
            preserveScroll: true
        });
    } else {
        // Entering edit mode - reset form
        editedCollection.value = {
            description: props.collection.description || '',
            icon: props.collection.icon,
            color: props.collection.color
        };
    }
});

const getIconPath = (iconName) => {
    const icons = {
        'folder': 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'folder-open': 'M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z',
        'briefcase': 'M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z',
        'document': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'home': 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'heart': 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        'star': 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'tag': 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'archive-box': 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'building-office': 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'shopping-bag': 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        'receipt-refund': 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z',
    };
    return icons[iconName] || icons['folder'];
};

const getFileType = (file) => {
    // Check for primary entity first (invoices, contracts, etc.)
    if (file.primary_entity && file.primary_entity.entity_type) {
        const entityType = file.primary_entity.entity_type.split('\\').pop().toLowerCase();
        return entityType.charAt(0).toUpperCase() + entityType.slice(1);
    }
    // Then check for primary receipt
    if (file.primary_receipt) {
        return 'Receipt';
    }
    // Then check for primary document
    if (file.primary_document) {
        return 'Document';
    }
    // Fallback to file_type if available
    if (file.file_type) {
        return file.file_type.charAt(0).toUpperCase() + file.file_type.slice(1);
    }
    return 'File';
};

const getFileUrl = (file) => {
    // Check for primary entity first (invoices, contracts, etc.)
    if (file.primary_entity && file.primary_entity.entity_type && file.primary_entity.entity_id) {
        const entityType = file.primary_entity.entity_type.split('\\').pop().toLowerCase();
        const pluralType = entityType + 's'; // Simple pluralization
        return route(pluralType + '.show', file.primary_entity.entity_id);
    }
    // Check for primary receipt
    if (file.primary_receipt) {
        return route('receipts.show', file.primary_receipt.id);
    }
    // Check for primary document
    if (file.primary_document) {
        return route('documents.show', file.primary_document.id);
    }
    return '#';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const removeFile = (fileId) => {
    if (confirm('Remove this file from the collection?')) {
        router.delete(route('collections.files.remove', props.collection.id), {
            data: { file_ids: [fileId] },
            preserveScroll: true
        });
    }
};
</script>
