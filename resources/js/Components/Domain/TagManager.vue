<template>
    <div class="tag-manager">
        <!-- Tag Input with Autocomplete -->
        <div class="relative">
            <div class="flex flex-wrap gap-2 p-2 border border-zinc-300 dark:border-zinc-600 rounded-md min-h-[42px] bg-white dark:bg-zinc-700">
                <!-- Selected Tags -->
                <TransitionGroup name="tag" tag="div" class="flex flex-wrap gap-2">
                    <span
                        v-for="tag in modelValue"
                        :key="tag.id"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :style="{ backgroundColor: tag.color + '20', color: tag.color }"
                    >
                        {{ tag.name }}
                        <button
                            v-if="!readonly"
                            @click="removeTag(tag)"
                            class="ml-1 hover:opacity-70 focus:outline-none"
                            type="button"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </span>
                </TransitionGroup>

                <!-- Tag Input -->
                <input
                    v-if="!readonly"
                    v-model="search"
                    @input="searchTags"
                    @keydown="handleKeydown"
                    @focus="showSuggestions = true"
                    @blur="hideSuggestions"
                    type="text"
                    :placeholder="modelValue.length === 0 ? placeholder : ''"
                    class="flex-1 min-w-[100px] border-0 bg-transparent p-0 focus:ring-0 text-sm text-zinc-900 dark:text-zinc-100 placeholder-gray-400 dark:placeholder-gray-500"
                    :disabled="disabled"
                />
            </div>

            <!-- Autocomplete Dropdown -->
            <Transition name="dropdown">
                <div
                    v-if="showSuggestions && filteredTags.length > 0 && !readonly"
                    class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-700 shadow-lg rounded-md py-1 text-sm ring-1 ring-black ring-opacity-5 max-h-60 overflow-auto"
                >
                    <button
                        v-for="(tag, index) in filteredTags"
                        :key="tag.id || 'new'"
                        @mousedown.prevent="selectTag(tag)"
                        type="button"
                        class="w-full text-left px-4 py-2 hover:bg-amber-100 dark:hover:bg-amber-600 focus:bg-amber-100 dark:focus:bg-zinc-600 focus:outline-none"
                        :class="{ 'bg-amber-100 dark:bg-zinc-600': index === selectedIndex }"
                    >
                        <span class="flex items-center justify-between">
                            <span class="flex items-center">
                                <span
                                    class="w-3 h-3 rounded-full mr-2"
                                    :style="{ backgroundColor: tag.color }"
                                ></span>
                                {{ tag.name }}
                                <span v-if="tag.isNew" class="ml-2 text-xs text-zinc-500 dark:text-zinc-400">(create new)</span>
                            </span>
                            <span v-if="tag.usage_count !== undefined" class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ tag.usage_count }} {{ tag.usage_count === 1 ? 'use' : 'uses' }}
                            </span>
                        </span>
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Create/Edit Tag Modal -->
        <Modal :show="showTagModal" @close="closeTagModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
                    {{ editingTag ? 'Edit Tag' : 'Create New Tag' }}
                </h2>

                <div class="mt-6">
                    <InputLabel for="tag-name" value="Tag Name" />
                    <TextInput
                        id="tag-name"
                        v-model="tagForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        :error="tagForm.errors.name"
                        @keyup.enter="saveTag"
                    />
                    <InputError :message="tagForm.errors.name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="tag-color" value="Tag Color" />
                    <div class="mt-1 flex items-center gap-2">
                        <input
                            id="tag-color"
                            v-model="tagForm.color"
                            type="color"
                            class="h-10 w-20 rounded cursor-pointer"
                        />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ tagForm.color }}</span>
                    </div>
                    <InputError :message="tagForm.errors.color" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeTagModal">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="saveTag" :disabled="tagForm.processing">
                        {{ editingTag ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Common/Modal.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import InputError from '@/Components/Forms/InputError.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Add tags...',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    allowCreate: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'tag-added', 'tag-removed']);

const search = ref('');
const showSuggestions = ref(false);
const selectedIndex = ref(0);
const allTags = ref([]);
const filteredTags = ref([]);
const showTagModal = ref(false);
const editingTag = ref(null);

const tagForm = useForm({
    name: '',
    color: '#3B82F6',
});

// Load all tags on mount
const loadTags = async () => {
    try {
        const response = await axios.get(route('tags.all'));
        allTags.value = response.data;
    } catch (error) {
        console.error('Failed to load tags:', error);
    }
};

// Search tags
const searchTags = () => {
    const query = search.value.toLowerCase().trim();
    
    if (!query) {
        filteredTags.value = [];
        return;
    }

    // Filter existing tags
    const existingTags = allTags.value.filter(tag => {
        return tag.name.toLowerCase().includes(query) &&
               !props.modelValue.some(selected => selected.id === tag.id);
    });

    // Sort by usage count
    existingTags.sort((a, b) => (b.usage_count || 0) - (a.usage_count || 0));

    // Add "create new" option if allowed and no exact match
    if (props.allowCreate && !existingTags.some(tag => tag.name.toLowerCase() === query)) {
        filteredTags.value = [
            ...existingTags,
            {
                name: search.value,
                color: generateRandomColor(),
                isNew: true,
            }
        ];
    } else {
        filteredTags.value = existingTags;
    }

    selectedIndex.value = 0;
};

// Handle keyboard navigation
const handleKeydown = (event) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            selectedIndex.value = Math.min(selectedIndex.value + 1, filteredTags.value.length - 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
            break;
        case 'Enter':
            event.preventDefault();
            if (filteredTags.value[selectedIndex.value]) {
                selectTag(filteredTags.value[selectedIndex.value]);
            }
            break;
        case 'Escape':
            showSuggestions.value = false;
            break;
        case 'Backspace':
            if (search.value === '' && props.modelValue.length > 0) {
                removeTag(props.modelValue[props.modelValue.length - 1]);
            }
            break;
    }
};

// Select a tag
const selectTag = async (tag) => {
    if (tag.isNew) {
        // Create new tag
        try {
            const response = await axios.post(route('tags.store'), {
                name: tag.name,
                color: tag.color,
            });
            
            const newTag = response.data;
            allTags.value.push(newTag);
            
            const updatedTags = [...props.modelValue, newTag];
            emit('update:modelValue', updatedTags);
            emit('tag-added', newTag);
        } catch (error) {
            console.error('Failed to create tag:', error);
        }
    } else {
        const updatedTags = [...props.modelValue, tag];
        emit('update:modelValue', updatedTags);
        emit('tag-added', tag);
    }
    
    search.value = '';
    filteredTags.value = [];
};

// Remove a tag
const removeTag = (tag) => {
    const updatedTags = props.modelValue.filter(t => t.id !== tag.id);
    emit('update:modelValue', updatedTags);
    emit('tag-removed', tag);
};

// Hide suggestions after a delay to allow click events
const hideSuggestions = () => {
    setTimeout(() => {
        showSuggestions.value = false;
    }, 200);
};

// Generate random color
const generateRandomColor = () => {
    const colors = [
        '#EF4444', // red
        '#F59E0B', // amber
        '#10B981', // emerald
        '#3B82F6', // blue
        '#6366F1', // indigo
        '#8B5CF6', // violet
        '#EC4899', // pink
        '#14B8A6', // teal
        '#F97316', // orange
        '#84CC16', // lime
    ];
    return colors[Math.floor(Math.random() * colors.length)];
};

// Modal functions
const openCreateModal = () => {
    editingTag.value = null;
    tagForm.reset();
    tagForm.color = generateRandomColor();
    showTagModal.value = true;
};

const openEditModal = (tag) => {
    editingTag.value = tag;
    tagForm.name = tag.name;
    tagForm.color = tag.color;
    showTagModal.value = true;
};

const closeTagModal = () => {
    showTagModal.value = false;
    editingTag.value = null;
    tagForm.reset();
};

const saveTag = () => {
    if (editingTag.value) {
        tagForm.patch(route('tags.update', editingTag.value), {
            preserveScroll: true,
            onSuccess: () => {
                closeTagModal();
                loadTags();
            },
        });
    } else {
        tagForm.post(route('tags.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeTagModal();
                loadTags();
            },
        });
    }
};

// Load tags on mount
loadTags();

// Expose methods for parent components
defineExpose({
    openCreateModal,
    openEditModal,
    loadTags,
});
</script>

<style scoped>
/* Tag transition */
.tag-enter-active,
.tag-leave-active {
    transition: all 0.3s ease;
}

.tag-enter-from {
    opacity: 0;
    transform: scale(0.8);
}

.tag-leave-to {
    opacity: 0;
    transform: scale(0.8);
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>