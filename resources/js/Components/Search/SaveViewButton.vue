<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { BookmarkIcon } from '@heroicons/vue/24/outline';
import Modal from '@/Components/Common/Modal.vue';
import { cleanViewFilters } from '@/utils/libraryNavigation';

const props = defineProps({
    filters: { type: Object, required: true },
    scope: { type: String, default: 'library' },
    activeView: { type: Object, default: null },
});
const open = ref(false);
const saving = ref(false);
const errors = ref({});
const form = reactive({ name: '', is_pinned: true, replace: false });
const title = computed(() => form.replace ? 'Update saved view' : 'Save a view');
function show() {
    form.name = props.activeView?.name || '';
    form.is_pinned = props.activeView?.is_pinned ?? true;
    form.replace = Boolean(props.activeView && props.activeView.scope === props.scope);
    errors.value = {};
    open.value = true;
}
function save() {
    if (saving.value) return;
    saving.value = true;
    errors.value = {};
    const data = { name: form.name, scope: props.scope, is_pinned: form.is_pinned, filters: cleanViewFilters(props.filters) };
    router[form.replace ? 'patch' : 'post'](
        form.replace ? route('saved-searches.update', props.activeView.id) : route('saved-searches.store'),
        data,
        { preserveScroll: true, onSuccess: () => { open.value = false; }, onError: value => { errors.value = value; }, onFinish: () => { saving.value = false; } }
    );
}
</script>

<template>
    <div>
        <button type="button" dusk="save-view" @click="show" class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
            <BookmarkIcon class="h-4 w-4" aria-hidden="true" />{{ activeView ? 'Save changes' : 'Save view' }}
        </button>
        <Modal :show="open" max-width="md" @close="!saving && (open = false)">
            <form @submit.prevent="save" class="flex flex-col gap-5 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ title }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Keep these filters for next time. Matching documents update automatically.</p>
                </div>
                <div>
                    <label for="saved-view-name" class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">View name</label>
                    <input id="saved-view-name" dusk="saved-view-name" v-model="form.name" required maxlength="80" autofocus placeholder="e.g. Business receipts this month" :aria-invalid="Boolean(errors.name)" aria-describedby="saved-view-error" class="w-full rounded-lg border-zinc-300 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                </div>
                <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300"><input v-model="form.is_pinned" type="checkbox" class="rounded border-zinc-300 text-amber-600 focus:ring-amber-500" />Pin to navigation</label>
                <label v-if="activeView && activeView.scope === scope" class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300"><input v-model="form.replace" type="checkbox" class="rounded border-zinc-300 text-amber-600 focus:ring-amber-500" />Update “{{ activeView.name }}”</label>
                <p v-if="Object.keys(errors).length" id="saved-view-error" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ Object.values(errors)[0] }}</p>
                <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <button type="button" :disabled="saving" @click="open = false" class="rounded-lg px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
                    <button type="submit" dusk="confirm-save-view" :disabled="saving" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">{{ saving ? 'Saving…' : form.replace ? 'Update view' : 'Save view' }}</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
