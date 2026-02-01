<template>
    <div class="relative">
        <div class="flex flex-wrap gap-2 p-2 border rounded-md border-zinc-300 dark:border-zinc-600 min-h-[42px] cursor-text bg-white dark:bg-zinc-700" @click="focusInput">
            <span
                v-for="tag in selectedTags"
                :key="tag.id"
                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium"
                :style="{ backgroundColor: tag.color + '20', color: tag.color }"
            >
                {{ tag.name }}
                <button
                    v-if="!readonly"
                    @click.stop="removeTag(tag.id)"
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
            v-if="showDropdown && (filteredTags.length > 0 || searchQuery)"
            class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-700 rounded-md shadow-lg border border-amber-200 dark:border-amber-600 max-h-48 overflow-y-auto"
        >
            <div
                v-for="tag in filteredTags"
                :key="tag.id"
                @mousedown.prevent="selectTag(tag)"
                class="px-3 py-2 cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-600 flex items-center justify-between"
            >
                <span class="flex items-center">
                    <span
                        class="w-3 h-3 rounded-full mr-2"
                        :style="{ backgroundColor: tag.color }"
                    ></span>
                    {{ tag.name }}
                </span>
                <svg v-if="isSelected(tag.id)" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div
                v-if="searchQuery && !exactMatch && allowCreate"
                @mousedown.prevent="createNewTag"
                class="px-3 py-2 cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-600 border-t border-amber-200 dark:border-amber-600"
            >
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Create new tag:</span>
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
        default: 'Search or create tags...'
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

const emit = defineEmits(['update:modelValue', 'create-tag']);

const input = ref(null);
const searchQuery = ref('');
const showDropdown = ref(false);
const allTags = ref([]);

const selectedTags = computed(() => {
    return allTags.value.filter(tag => props.modelValue.includes(tag.id));
});

const availableTags = computed(() => {
    return allTags.value.filter(tag => !props.modelValue.includes(tag.id));
});

const filteredTags = computed(() => {
    if (!searchQuery.value) return availableTags.value;

    const query = searchQuery.value.toLowerCase();
    return availableTags.value.filter(tag =>
        tag.name.toLowerCase().includes(query)
    );
});

const exactMatch = computed(() => {
    const query = searchQuery.value.toLowerCase();
    return allTags.value.some(tag => tag.name.toLowerCase() === query);
});

const isSelected = (tagId) => {
    return props.modelValue.includes(tagId);
};

const focusInput = () => {
    input.value?.focus();
};

const selectTag = (tag) => {
    emit('update:modelValue', [...props.modelValue, tag.id]);
    searchQuery.value = '';
};

const removeTag = (tagId) => {
    emit('update:modelValue', props.modelValue.filter(id => id !== tagId));
};

const createNewTag = async () => {
    if (!searchQuery.value.trim() || exactMatch.value) return;

    emit('create-tag', searchQuery.value.trim());
    searchQuery.value = '';
};

const handleInput = () => {
    showDropdown.value = true;
};

const handleEnter = () => {
    if (filteredTags.value.length > 0) {
        selectTag(filteredTags.value[0]);
    } else if (searchQuery.value && !exactMatch.value && props.allowCreate) {
        createNewTag();
    }
};

const handleBackspace = () => {
    if (searchQuery.value === '' && props.modelValue.length > 0) {
        removeTag(props.modelValue[props.modelValue.length - 1]);
    }
};

const handleBlur = () => {
    // Delay to allow click events to fire
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};

const loadTags = async () => {
    try {
        const response = await axios.get(route('tags.all'));
        allTags.value = response.data;
    } catch (error) {
        console.error('Failed to load tags:', error);
    }
};

onMounted(() => {
    loadTags();
});

defineExpose({
    loadTags
});
</script>