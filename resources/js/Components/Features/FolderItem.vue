<template>
    <div
        class="flex items-center p-3 hover:bg-amber-50 dark:hover:bg-zinc-700 rounded-lg cursor-pointer border border-transparent hover:border-amber-200 dark:hover:border-zinc-600"
        @click="handleClick"
    >
        <input
            v-if="!item.is_folder || item.status === 'pending'"
            type="checkbox"
            :checked="selected"
            @click.stop="$emit('toggle-selection', item)"
            class="mr-3 rounded border-zinc-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
        />
        
        <div class="flex-1 flex items-center">
            <!-- Icon -->
            <div class="mr-3">
                <svg v-if="item.is_folder" class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                </svg>
                <svg v-else class="w-6 h-6 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            
            <!-- Name and tags -->
            <div class="flex-1">
                <div class="flex items-center">
                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ item.name || item.filename }}</span>
                    <div v-if="item.folder_tags && item.folder_tags.length > 0" class="ml-2 flex gap-1">
                        <span
                            v-for="tag in item.folder_tags"
                            :key="tag.id"
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                            :style="{ backgroundColor: tag.color + '20', color: tag.color }"
                        >
                            {{ tag.name }}
                        </span>
                    </div>
                </div>
                <div v-if="item.folder_path" class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    {{ item.folder_path }}
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="flex items-center space-x-4 text-sm text-zinc-500 dark:text-zinc-400">
                <span v-if="!item.is_folder">{{ formatFileSize(item.size) }}</span>
                <span v-if="item.uploaded_at">{{ formatDate(item.uploaded_at) }}</span>
                <span v-if="item.status && !item.is_folder" :class="getStatusClass(item.status)" class="px-2 py-0.5 rounded text-xs font-medium">
                    {{ item.status }}
                </span>
            </div>
            
            <!-- Folder actions -->
            <div v-if="item.is_folder" class="ml-4">
                <button
                    @click.stop="showTagModal = true"
                    class="text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300"
                    title="Manage folder tags"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tag Management Modal -->
    <Modal v-if="showTagModal" @close="showTagModal = false">
        <template #title>
            Manage Folder Tags: {{ item.name || item.filename }}
        </template>
        
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Tags applied to this folder will be inherited by all files within it during import.
            </p>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Folder Tags</label>
                <TagSelector
                    v-model="folderTagIds"
                    :tags="availableTags"
                    @create-tag="createTag"
                />
            </div>
            
            <div class="flex justify-end space-x-2 pt-4">
                <button
                    @click="showTagModal = false"
                    class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-amber-50 dark:hover:bg-amber-600"
                >
                    Cancel
                </button>
                <button
                    @click="saveFolderTags"
                    class="px-4 py-2 text-sm font-medium text-white bg-zinc-900 border border-transparent rounded-md hover:bg-zinc-800"
                >
                    Save Tags
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue';
import Modal from '../Common/Modal.vue';
import TagSelector from '../Domain/TagSelector.vue';

const props = defineProps({
    item: {
        type: Object,
        required: true
    },
    selected: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle-selection', 'navigate', 'update-tags']);

const showTagModal = ref(false);
const folderTagIds = ref(props.item.folder_tag_ids || []);

// This would normally come from props or be fetched
const availableTags = ref([]);

const handleClick = () => {
    if (props.item.is_folder) {
        emit('navigate', props.item.folder_path || props.item.path);
    }
};

const saveFolderTags = () => {
    emit('update-tags', props.item.folder_path || props.item.path, folderTagIds.value);
    showTagModal.value = false;
};

const createTag = (tagName) => {
    // This would be handled by parent component
    if (import.meta.env.DEV) console.log('Create tag:', tagName);
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
    });
};

const getStatusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200',
        processing: 'bg-amber-100 dark:bg-orange-900/50 text-amber-800 dark:text-amber-200',
        completed: 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
        failed: 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200',
    };
    return classes[status] || 'bg-amber-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-200';
};
</script>
