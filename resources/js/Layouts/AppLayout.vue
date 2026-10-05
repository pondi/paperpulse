<script setup>
// Re-export AuthenticatedLayout as AppLayout for convenience
import AuthenticatedLayout from './AuthenticatedLayout.vue'
</script>

<template>
  <AuthenticatedLayout>
    <template v-if="$slots.header" #header><slot name="header" /></template>
    <slot />
  </AuthenticatedLayout>
</template>
