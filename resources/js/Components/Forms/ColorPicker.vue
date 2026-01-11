<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
            {{ label }}
        </label>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="color in colors"
                :key="color.value"
                type="button"
                @click="selectColor(color.value)"
                class="relative flex items-center justify-center w-10 h-10 rounded-lg border-2 transition-all hover:scale-110"
                :class="[
                    modelValue === color.value
                        ? 'border-zinc-900 dark:border-zinc-100 ring-2 ring-offset-2 ring-zinc-400 dark:ring-zinc-600'
                        : 'border-zinc-300 dark:border-zinc-600'
                ]"
                :style="{ backgroundColor: color.value }"
                :title="color.name"
            >
                <div v-if="modelValue === color.value" class="absolute inset-0 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white drop-shadow-lg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: {
        type: String,
        default: '#3B82F6'
    },
    label: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

const colors = [
    { name: 'Red', value: '#EF4444' },
    { name: 'Amber', value: '#F59E0B' },
    { name: 'Emerald', value: '#10B981' },
    { name: 'Blue', value: '#3B82F6' },
    { name: 'Indigo', value: '#6366F1' },
    { name: 'Violet', value: '#8B5CF6' },
    { name: 'Pink', value: '#EC4899' },
    { name: 'Teal', value: '#14B8A6' },
    { name: 'Orange', value: '#F97316' },
    { name: 'Lime', value: '#84CC16' },
];

const selectColor = (color) => {
    emit('update:modelValue', color);
};
</script>
