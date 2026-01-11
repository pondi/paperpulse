<template>
    <div class="relative">
        <div class="flex flex-wrap gap-2 p-2 border rounded-md border-zinc-300 dark:border-zinc-600 min-h-[42px] cursor-text bg-white dark:bg-zinc-700" @click="focusInput">
            <span
                v-for="collection in selectedCollections"
                :key="collection.id"
                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                :style="{ backgroundColor: collection.color + '20', color: collection.color }"
            >
                <svg v-if="collection.icon" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getIconPath(collection.icon)" />
                </svg>
                {{ collection.name }}
                <button
                    v-if="!readonly"
                    @click.stop="removeCollection(collection.id)"
                    class="ml-1 hover:text-zinc-700 dark:hover:text-zinc-300"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
            <input
                v-if="!readonly"
                ref="input"
                v-model="searchQuery"
                @input="handleInput"
                @keydown.enter.prevent="handleEnter"
                @keydown.backspace="handleBackspace"
                @focus="showDropdown = true"
                @blur="handleBlur"
                type="text"
                class="flex-1 outline-none text-sm min-w-[100px] bg-transparent text-zinc-900 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-gray-500"
                :placeholder="placeholder"
            />
        </div>

        <!-- Dropdown -->
        <div
            v-if="showDropdown && (filteredCollections.length > 0 || searchQuery)"
            class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-700 rounded-md shadow-lg border border-blue-200 dark:border-blue-600 max-h-48 overflow-y-auto"
        >
            <div
                v-for="collection in filteredCollections"
                :key="collection.id"
                @mousedown.prevent="selectCollection(collection)"
                class="px-3 py-2 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-600 flex items-center justify-between"
            >
                <span class="flex items-center">
                    <span
                        class="w-3 h-3 rounded-full mr-2"
                        :style="{ backgroundColor: collection.color }"
                    ></span>
                    {{ collection.name }}
                    <span v-if="collection.files_count !== undefined" class="ml-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ collection.files_count }} {{ collection.files_count === 1 ? 'file' : 'files' }}
                    </span>
                </span>
                <svg v-if="isSelected(collection.id)" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div
                v-if="searchQuery && !exactMatch && allowCreate"
                @mousedown.prevent="createNewCollection"
                class="px-3 py-2 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-600 border-t border-blue-200 dark:border-blue-600"
            >
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Create new collection:</span>
                <span class="ml-1 font-medium text-zinc-900 dark:text-zinc-100">{{ searchQuery }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Search or create collections...'
    },
    readonly: {
        type: Boolean,
        default: false
    },
    allowCreate: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:modelValue', 'create-collection']);

const input = ref(null);
const searchQuery = ref('');
const showDropdown = ref(false);
const allCollections = ref([]);

const selectedCollections = computed(() => {
    return allCollections.value.filter(collection => props.modelValue.includes(collection.id));
});

const availableCollections = computed(() => {
    return allCollections.value.filter(collection => !props.modelValue.includes(collection.id));
});

const filteredCollections = computed(() => {
    if (!searchQuery.value) return availableCollections.value;

    const query = searchQuery.value.toLowerCase();
    return availableCollections.value.filter(collection =>
        collection.name.toLowerCase().includes(query)
    );
});

const exactMatch = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return allCollections.value.some(collection => collection.name.toLowerCase() === query);
});

const isSelected = (collectionId) => {
    return props.modelValue.includes(collectionId);
};

const focusInput = () => {
    input.value?.focus();
};

const selectCollection = (collection) => {
    emit('update:modelValue', [...props.modelValue, collection.id]);
    searchQuery.value = '';
};

const removeCollection = (collectionId) => {
    emit('update:modelValue', props.modelValue.filter(id => id !== collectionId));
};

const createNewCollection = async () => {
    if (!searchQuery.value.trim() || exactMatch.value) return;

    emit('create-collection', searchQuery.value.trim());
    searchQuery.value = '';
};

const handleInput = () => {
    showDropdown.value = true;
};

const handleEnter = () => {
    if (filteredCollections.value.length > 0) {
        selectCollection(filteredCollections.value[0]);
    } else if (searchQuery.value && !exactMatch.value && props.allowCreate) {
        createNewCollection();
    }
};

const handleBackspace = () => {
    if (searchQuery.value === '' && props.modelValue.length > 0) {
        removeCollection(props.modelValue[props.modelValue.length - 1]);
    }
};

const handleBlur = () => {
    // Delay to allow click events to fire
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};

const getIconPath = (iconName) => {
    // Common Heroicon paths (simplified)
    const icons = {
        'folder': 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'folder-open': 'M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z',
        'briefcase': 'M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z',
        'document': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'home': 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    };
    return icons[iconName] || icons['folder'];
};

const loadCollections = async () => {
    try {
        const response = await axios.get(route('collections.all'));
        allCollections.value = response.data;
    } catch (error) {
        console.error('Failed to load collections:', error);
    }
};

onMounted(() => {
    loadCollections();
});

defineExpose({
    loadCollections
});
</script>
