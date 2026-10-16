<script setup>
defineProps({
    id: {
        type: String,
        default: undefined,
    },
    message: {
        type: String,
    },
});
</script>

<template>
    <div :id="id" v-show="message" role="alert">
        <p class="text-sm text-red-700 dark:text-red-400">
            {{ message }}
        </p>
    </div>
</template>
