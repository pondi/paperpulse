<script setup>
// Re-export AuthenticatedLayout as AppLayout for convenience
import AuthenticatedLayout from './AuthenticatedLayout.vue'
</script>

<template>
  <AuthenticatedLayout>
    <slot />
  </AuthenticatedLayout>
</template>
