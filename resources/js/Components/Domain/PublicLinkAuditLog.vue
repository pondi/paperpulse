<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    collectionId: {
        type: Number,
        required: true,
    },
    linkId: {
        type: Number,
        required: true,
    },
})

const emit = defineEmits(['close'])

const logs = ref([])
const pagination = ref(null)
const loading = ref(true)
const currentPage = ref(1)

async function fetchLogs(page = 1) {
    loading.value = true
    try {
        const response = await axios.get(
            route('collections.public-links.logs', [props.collectionId, props.linkId]),
            { params: { page } },
        )
        logs.value = response.data.data
        pagination.value = response.data
        currentPage.value = page
    } catch {
        logs.value = []
    } finally {
        loading.value = false
    }
}

onMounted(() => fetchLogs())

function getActionBadge(action) {
    const badges = {
        view: { label: 'View', classes: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
        download_file: { label: 'Download', classes: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
        download_all: { label: 'Download All', classes: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' },
        password_attempt: { label: 'Wrong Password', classes: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
        password_success: { label: 'Authenticated', classes: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    }
    return badges[action] || { label: action, classes: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300' }
}

function formatTimestamp(ts) {
    return new Date(ts).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function truncateUserAgent(ua) {
    if (!ua) return '-'
    return ua.length > 50 ? ua.substring(0, 50) + '...' : ua
}
</script>

<template>
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Access Logs</h3>
            <button @click="emit('close')" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div v-if="loading" class="py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
            Loading logs...
        </div>

        <div v-else-if="logs.length === 0" class="py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
            No access logs yet.
        </div>

        <div v-else>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="py-2 px-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Time</th>
                            <th class="py-2 px-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">IP Address</th>
                            <th class="py-2 px-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Action</th>
                            <th class="py-2 px-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">User Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300 whitespace-nowrap">{{ formatTimestamp(log.accessed_at) }}</td>
                            <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300 font-mono text-xs">{{ log.ip_address }}</td>
                            <td class="py-2 px-3">
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold', getActionBadge(log.action).classes]">
                                    {{ getActionBadge(log.action).label }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-zinc-500 dark:text-zinc-400 text-xs" :title="log.user_agent">{{ truncateUserAgent(log.user_agent) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
                </p>
                <div class="flex gap-1">
                    <button
                        v-for="page in pagination.last_page"
                        :key="page"
                        @click="fetchLogs(page)"
                        :class="[
                            'rounded px-2.5 py-1 text-xs font-medium transition',
                            page === currentPage
                                ? 'bg-amber-600 text-white'
                                : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700',
                        ]"
                    >
                        {{ page }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
