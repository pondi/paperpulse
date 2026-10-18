<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Common/Modal.vue'
import DatePicker from '@/Components/Forms/DatePicker.vue'
import PublicLinkAuditLog from '@/Components/Domain/PublicLinkAuditLog.vue'

const props = defineProps({
    collectionId: {
        type: Number,
        required: true,
    },
    collectionName: {
        type: String,
        default: 'this collection',
    },
    publicLinks: {
        type: Array,
        default: () => [],
    },
    flashPublicLink: {
        type: Object,
        default: null,
    },
})

const showCreateModal = ref(false)
const showSuccessModal = ref(false)
const showAuditModal = ref(false)
const auditLinkId = ref(null)
const processing = ref(false)
const error = ref('')
const copiedUrl = ref(false)
const copiedPassword = ref(false)

const form = reactive({
    label: '',
    is_password_protected: false,
    expiration_preset: 'never',
    expires_at: null,
    max_views: null,
    notify_email: '',
})

const createdLink = ref(null)

watch(() => props.flashPublicLink, (val) => {
    if (val) {
        createdLink.value = val
        showCreateModal.value = false
        showSuccessModal.value = true
    }
}, { immediate: true })

const isCustomExpiration = computed(() => form.expiration_preset === 'custom')

function resetForm() {
    form.label = ''
    form.is_password_protected = false
    form.expiration_preset = 'never'
    form.expires_at = null
    form.max_views = null
    form.notify_email = ''
}

function openCreateModal() {
    resetForm()
    error.value = ''
    showCreateModal.value = true
}

function createLink() {
    if (processing.value) return
    error.value = ''
    processing.value = true
    router.post(route('collections.public-links.store', props.collectionId), form, {
        preserveScroll: true,
        onError: errors => { error.value = Object.values(errors).join(' ') },
        onFinish: () => {
            processing.value = false
        },
    })
}

function revokeLink(linkId) {
    if (!confirm('Revoke this public link? It will immediately stop working.')) return
    error.value = ''
    router.delete(route('collections.public-links.destroy', [props.collectionId, linkId]), {
        preserveScroll: true,
        onError: errors => { error.value = Object.values(errors).join(' ') },
    })
}

function viewLogs(linkId) {
    auditLinkId.value = linkId
    showAuditModal.value = true
}

async function copyToClipboard(text, type) {
    try {
        await navigator.clipboard.writeText(text)
        if (type === 'url') {
            copiedUrl.value = true
            setTimeout(() => copiedUrl.value = false, 2000)
        } else {
            copiedPassword.value = true
            setTimeout(() => copiedPassword.value = false, 2000)
        }
    } catch {
        // Fallback
    }
}

function formatDate(date) {
    if (!date) return 'Never'
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

function getLinkStatus(link) {
    if (!link.is_active) return { label: 'Revoked', classes: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }
    if (link.expires_at && new Date(link.expires_at) < new Date()) return { label: 'Expired', classes: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }
    if (link.max_views && link.view_count >= link.max_views) return { label: 'Limit Reached', classes: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400' }
    return { label: 'Active', classes: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }
}
</script>

<template>
    <div>
        <div>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Public links</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ publicLinks.length }} {{ publicLinks.length === 1 ? 'link' : 'links' }} for {{ collectionName }}</p>
                </div>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-amber-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                    Create Public Link
                </button>
            </div>

            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                A public link lets anyone with the URL view files directly in {{ collectionName }} without an account. Files in subfolders stay private unless those folders are shared separately.
            </p>

            <p v-if="error && !showCreateModal" role="alert" class="mb-3 text-sm text-red-700 dark:text-red-400">{{ error }}</p>

            <!-- Active Links -->
            <div v-if="publicLinks.length > 0" class="space-y-3">
                <div
                    v-for="link in publicLinks"
                    :key="link.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3"
                >
                    <div class="min-w-0 basis-full sm:flex-1 sm:basis-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ link.label || 'Public Link' }}
                            </span>
                            <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold', getLinkStatus(link).classes]">
                                {{ getLinkStatus(link).label }}
                            </span>
                            <svg v-if="link.is_password_protected" class="h-3.5 w-3.5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <span>Created {{ formatDate(link.created_at) }}</span>
                            <span>{{ link.expires_at ? `Expires ${formatDate(link.expires_at)}` : 'No expiration' }}</span>
                            <span>{{ link.view_count }} {{ link.view_count === 1 ? 'view' : 'views' }}{{ link.max_views ? ` / ${link.max_views}` : '' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:ml-3">
                        <button
                            @click="viewLogs(link.id)"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                            title="View access logs"
                            :aria-label="`View access log for ${link.label || 'public link'}`"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                        </button>
                        <button
                            v-if="link.is_active"
                            @click="revokeLink(link.id)"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded text-red-500 transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                            title="Revoke link"
                            :aria-label="`Revoke ${link.label || 'public link'}`"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-6 text-sm text-zinc-400 dark:text-zinc-500">
                No public links created yet.
            </div>
        </div>

        <!-- Create Link Modal -->
        <Modal :show="showCreateModal" :closeable="!processing" max-width="md" @close="showCreateModal = false" v-slot="{ titleId }">
            <form class="p-6" @submit.prevent="createLink">
                <h3 :id="titleId" class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mb-4">Create Public Link</h3>

                <div class="space-y-4">
                    <div>
                        <label for="public-link-label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Label (optional)</label>
                        <input
                            id="public-link-label"
                            v-model="form.label"
                            type="text"
                            class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                            placeholder="e.g., For accountant 2026"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.is_password_protected"
                            type="checkbox"
                            class="rounded border-zinc-300 dark:border-zinc-600 text-amber-600 shadow-sm focus:ring-amber-500"
                            id="password-protected"
                        />
                        <label for="password-protected" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Password protect this link
                        </label>
                    </div>

                    <div>
                        <label for="public-link-expiration_preset" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Expiration</label>
                        <select
                            id="public-link-expiration_preset"
                            v-model="form.expiration_preset"
                            class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                        >
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                            <option value="90d">90 days</option>
                            <option value="never">No expiration</option>
                            <option value="custom">Custom date</option>
                        </select>
                    </div>

                    <div v-if="isCustomExpiration">
                        <label for="public-link-expires-at" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Expiration Date</label>
                        <DatePicker id="public-link-expires-at" v-model="form.expires_at" placeholder="Select date" />
                    </div>

                    <div>
                        <label for="public-link-max_views" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Max views (optional)</label>
                        <input
                            id="public-link-max_views"
                            v-model.number="form.max_views"
                            type="number"
                            min="1"
                            class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                            placeholder="Leave empty for unlimited"
                        />
                    </div>

                    <div>
                        <label for="public-link-email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            Send link to email (optional)
                        </label>
                        <input
                            id="public-link-email"
                            v-model="form.notify_email"
                            type="email"
                            class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                            placeholder="accountant@example.com"
                        />
                    </div>
                </div>

                <p v-if="error" role="alert" class="mt-4 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
                <div class="flex flex-wrap justify-end gap-3 mt-6">
                    <button
                        type="button"
                        :disabled="processing"
                        @click="showCreateModal = false"
                        class="rounded-lg border border-zinc-300 dark:border-zinc-600 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500 disabled:opacity-50 transition"
                    >
                        {{ processing ? 'Creating...' : 'Create Link' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Success Modal -->
        <Modal :show="showSuccessModal" max-width="md" @close="showSuccessModal = false" v-slot="{ titleId }">
            <div class="p-6" v-if="createdLink">
                <div class="text-center mb-4">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-3">
                        <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h3 :id="titleId" class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Link Created</h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-1">Public URL</label>
                        <div class="flex gap-2">
                            <input
                                :value="createdLink.url"
                                readonly
                                class="flex-1 rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 text-sm bg-zinc-50 dark:bg-zinc-800"
                            />
                            <button
                                @click="copyToClipboard(createdLink.url, 'url')"
                                class="rounded-lg border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:hover:bg-zinc-700 transition"
                                :class="copiedUrl ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-700 dark:text-zinc-300'"
                            >
                                {{ copiedUrl ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="createdLink.password">
                        <label class="block text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-1">Password</label>
                        <div class="flex gap-2">
                            <input
                                :value="createdLink.password"
                                readonly
                                class="flex-1 rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 text-sm font-mono bg-zinc-50 dark:bg-zinc-800"
                            />
                            <button
                                @click="copyToClipboard(createdLink.password, 'password')"
                                class="rounded-lg border border-zinc-300 dark:border-zinc-600 px-3 py-2 text-sm font-medium hover:bg-zinc-50 dark:hover:bg-zinc-700 transition"
                                :class="copiedPassword ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-700 dark:text-zinc-300'"
                            >
                                {{ copiedPassword ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Save this password now. It cannot be retrieved later.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button
                        @click="showSuccessModal = false"
                        class="rounded-lg bg-zinc-900 dark:bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 dark:hover:bg-amber-500 transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Audit Log Modal -->
        <Modal :show="showAuditModal" max-width="lg" @close="showAuditModal = false" v-slot="{ titleId }">
            <PublicLinkAuditLog :title-id="titleId"
                v-if="showAuditModal && auditLinkId"
                :collection-id="collectionId"
                :link-id="auditLinkId"
                @close="showAuditModal = false"
            />
        </Modal>
    </div>
</template>
