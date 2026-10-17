<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import FileInspector from '@/Components/Domain/FileInspector.vue';
const props = defineProps({ file: { type: Object, required: true }, extractedEntities: { type: Array, default: () => [] }, hasLegacyData: Boolean });
const dirty = ref(false);
let removeBefore;
const unload = event => { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => { window.addEventListener('beforeunload', unload); removeBefore = router.on('before', event => { if (dirty.value && event.detail.visit.method === 'get' && !confirm('Discard unsaved changes?')) event.preventDefault(); }); });
onBeforeUnmount(() => { removeBefore?.(); window.removeEventListener('beforeunload', unload); });
</script>

<template>
    <AuthenticatedLayout framed document-focus>
        <Head :title="file.name || 'Document workspace'" />
        <FileInspector :file-id="file.id" :initial="{ file, extractedEntities }" framed @dirty="dirty = $event">
            <template #leading><Link dusk="workspace-back" :href="file.back_url || route('library.index')" class="workspace-button shrink-0" :aria-label="file.back_label || 'Back to Library'"><ArrowLeftIcon class="h-4 w-4" aria-hidden="true" /><span class="hidden sm:inline">{{ file.back_label || 'Back to Library' }}</span></Link></template>
        </FileInspector>
    </AuthenticatedLayout>
</template>
