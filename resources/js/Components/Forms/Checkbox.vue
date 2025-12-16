<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="rounded dark:bg-zinc-900 border-zinc-300 dark:border-zinc-700 text-amber-600 shadow-sm focus:ring-amber-500 dark:focus:ring-amber-600 dark:focus:ring-offset-zinc-900 transition-colors duration-200"
    />
</template>
