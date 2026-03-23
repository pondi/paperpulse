<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

const props = defineProps({
    collectionName: {
        type: String,
        default: 'Shared Collection',
    },
    token: {
        type: String,
        required: true,
    },
})

const page = usePage()

const form = reactive({
    password: '',
})

const processing = ref(false)

function submit() {
    processing.value = true
    router.post(route('shared.collections.verify', props.token), form, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
        },
    })
}
</script>

<template>
    <Head title="Enter Password" />

    <div class="min-h-screen flex items-center justify-center bg-amber-50 dark:bg-zinc-900 px-4">
        <div class="max-w-md w-full bg-white dark:bg-zinc-800 rounded-xl shadow-lg p-8 border border-amber-200 dark:border-zinc-700">
            <div class="text-center mb-6">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30 mb-4">
                    <svg class="h-7 w-7 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>

                <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ collectionName }}
                </h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                    This collection is password protected.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                        Password
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        placeholder="Enter the password"
                        autofocus
                        required
                    />
                    <p
                        v-if="page.props.errors?.password"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ page.props.errors.password }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="processing || !form.password"
                    class="w-full inline-flex justify-center items-center rounded-lg bg-zinc-900 dark:bg-amber-600 px-4 py-2.5 text-sm font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-zinc-800 dark:hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-800 disabled:opacity-50 transition duration-200"
                >
                    <span v-if="processing">Verifying...</span>
                    <span v-else>Unlock Collection</span>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-amber-200 dark:border-zinc-700 text-center">
                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                    Shared via PaperPulse
                </p>
            </div>
        </div>
    </div>
</template>
