<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import Pagination from '@/Components/Common/Pagination.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';

const props = defineProps({
    run: Object, recommendations: Object, pending_count: Number,
    changes_waiting: Boolean, can_start: Boolean, enabled: Boolean,
});
const selected = ref([]);
const reason = ref('');
const removeEmpty = ref(false);
const busy = ref(false);
const error = ref('');
const preview = ref(null);
const ready = computed(() => props.run?.status === 'awaiting_decisions');
const pending = computed(() => props.recommendations?.data.filter(item => ['pending', 'conflict'].includes(item.status)) ?? []);
const post = (url, data = {}) => {
    busy.value = true;
    error.value = '';
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
        onError: errors => { error.value = Object.values(errors).flat().join(' '); },
        onFinish: () => { busy.value = false; },
    });
};
const decide = (decision, ids) => post(route('collections.organization.decide'), {
    recommendation_ids: ids, decision, reason: reason.value || null, remove_empty: removeEmpty.value,
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Folder recommendations" />
        <template #header>
            <h2 class="text-2xl font-black text-zinc-900 dark:text-zinc-100">Folder recommendations</h2>
        </template>
        <div class="mx-auto flex max-w-7xl flex-col gap-6 p-6 text-zinc-900 dark:text-zinc-100">
            <Link :href="route('collections.index')" class="text-orange-600 dark:text-orange-400">Back to collections</Link>
            <section class="flex flex-col gap-4 rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
                <p>{{ pending_count }} decisions remaining · {{ run?.status?.replaceAll('_', ' ') ?? 'No recommendations yet' }}</p>
                <p v-if="!enabled">Automatic organization is turned off in preferences.</p>
                <p v-else-if="pending_count">Apply or decline every suggestion before starting another review.</p>
                <p v-if="changes_waiting">New changes are waiting for the next review.</p>
                <p v-if="run?.error" role="alert" class="text-red-600 dark:text-red-400">{{ run.error }}</p>
                <p v-if="error" role="alert" class="text-red-600 dark:text-red-400">{{ error }}</p>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton :disabled="!can_start || busy" @click="post(route('collections.organization.start'))">Generate recommendations</PrimaryButton>
                    <SecondaryButton v-if="['queued', 'running'].includes(run?.status)" :disabled="busy" @click="router.reload({ only: ['run', 'recommendations', 'pending_count', 'changes_waiting', 'can_start'] })">Refresh status</SecondaryButton>
                    <SecondaryButton v-if="run?.status === 'failed' && run.attempts < 3" :disabled="busy" @click="post(route('collections.organization.retry', run.id))">Retry</SecondaryButton>
                    <SecondaryButton v-if="run?.status === 'failed'" :disabled="busy" @click="post(route('collections.organization.dismiss', run.id))">Dismiss failed run</SecondaryButton>
                </div>
            </section>
            <section v-if="pending.length" class="flex flex-col gap-4 rounded-lg bg-white p-6 dark:bg-zinc-800">
                <label class="flex items-center gap-2"><input type="checkbox" :checked="selected.length === pending.length" @change="selected = $event.target.checked ? pending.map(item => item.id) : []" />Select this page</label>
                <label class="flex flex-col gap-2">Reason for declining (optional)<input v-model="reason" maxlength="240" class="rounded border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" /></label>
                <label class="flex items-center gap-2"><input v-model="removeEmpty" type="checkbox" />Remove empty source folders when applying merges</label>
                <div class="flex gap-3">
                    <PrimaryButton :disabled="!ready || !selected.length || busy" @click="decide('apply', selected)">Apply selected</PrimaryButton>
                    <SecondaryButton :disabled="!ready || !selected.length || busy" @click="decide('decline', selected)">Decline selected</SecondaryButton>
                </div>
            </section>
            <article v-for="item in recommendations?.data ?? []" :key="item.id" class="flex flex-col gap-3 rounded-lg bg-white p-6 shadow dark:bg-zinc-800">
                <label class="flex items-center gap-3">
                    <input v-if="['pending', 'conflict'].includes(item.status)" v-model="selected" type="checkbox" :value="item.id" :disabled="!ready || busy" />
                    <span>{{ item.current_paths.join(', ') }} → {{ item.proposed_path }}</span>
                </label>
                <p>{{ item.affected_count }} documents · {{ Math.round(item.confidence * 100) }}% confidence · {{ item.status }}</p>
                <p>{{ item.reason }}</p>
                <p v-if="item.decision_reason" class="text-amber-700 dark:text-amber-300">{{ item.decision_reason }}</p>
                <div class="flex flex-wrap gap-3">
                    <PrimaryButton v-if="item.status === 'pending'" :disabled="!ready || busy" @click="decide('apply', [item.id])">Apply</PrimaryButton>
                    <SecondaryButton v-if="['pending', 'conflict'].includes(item.status)" :disabled="!ready || busy" @click="decide('decline', [item.id])">Decline</SecondaryButton>
                    <SecondaryButton v-if="item.status === 'applied' && !item.undone_at" :disabled="busy" @click="post(route('collections.organization.undo', item.id))">Undo</SecondaryButton>
                    <SecondaryButton v-for="file in item.preview_items" :key="file.id" @click="preview = file">Preview {{ file.title }}</SecondaryButton>
                </div>
            </article>
            <p v-if="!recommendations?.data.length" class="rounded-lg bg-white p-6 dark:bg-zinc-800">{{ ['queued', 'running'].includes(run?.status) ? 'Your recommendations are being prepared.' : 'No folder changes to review.' }}</p>
            <Pagination v-if="recommendations?.last_page > 1" :links="recommendations.links" :from="recommendations.from" :to="recommendations.to" :total="recommendations.total" />
            <FilePreviewModal :show="!!preview" :item="preview" @close="preview = null" />
        </div>
    </AuthenticatedLayout>
</template>
