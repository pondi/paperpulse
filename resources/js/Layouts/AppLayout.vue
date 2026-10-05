<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from './AuthenticatedLayout.vue'

defineProps({ title: String });
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="title" />
    <template v-if="$slots.header" #header><slot name="header" /></template>
    <slot />
  </AuthenticatedLayout>
</template>
