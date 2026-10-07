<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileInspector from '@/Components/Domain/FileInspector.vue';
const props = defineProps({ file: { type: Object, required: true }, extractedEntities: { type: Array, default: () => [] }, hasLegacyData: Boolean });
const dirty = ref(false);
let removeBefore;
const unload = event => { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => { window.addEventListener('beforeunload', unload); removeBefore = router.on('before', event => { if (dirty.value && event.detail.visit.method === 'get' && !confirm('Discard unsaved changes?')) event.preventDefault(); }); });
onBeforeUnmount(() => { removeBefore?.(); window.removeEventListener('beforeunload', unload); });
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="file.name || 'Document workspace'" />
        <template #header><div class="flex flex-col gap-2"><Link dusk="workspace-back" :href="file.back_url || route('library.index')" class="self-start text-xs text-zinc-500">← {{ file.back_label || 'Back to Library' }}</Link><h1 class="break-words text-xl font-semibold">{{ file.name }}</h1></div></template>
        <FileInspector :file-id="file.id" :initial="{ file, extractedEntities }" @dirty="dirty = $event" />
    </AuthenticatedLayout>
</template>
