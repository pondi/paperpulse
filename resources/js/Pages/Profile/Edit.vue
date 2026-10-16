<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">Profile</h2>
        </template>

        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
            <nav aria-label="Profile sections" class="flex flex-wrap gap-2 rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
                <a href="#profile-information" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-zinc-700 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:text-zinc-200 dark:hover:bg-zinc-700">Profile information</a>
                <a href="#profile-password" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-zinc-700 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:text-zinc-200 dark:hover:bg-zinc-700">Password and security</a>
                <a href="#account-deletion" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:text-red-300 dark:hover:bg-red-500/10">Account deletion</a>
                <Link :href="route('preferences.index')" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-amber-700 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:text-amber-400 dark:hover:bg-zinc-700">Application preferences</Link>
            </nav>

            <div id="profile-information" class="scroll-mt-24 rounded-lg bg-white p-4 shadow dark:bg-zinc-800 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div id="profile-password" class="scroll-mt-24 rounded-lg bg-white p-4 shadow dark:bg-zinc-800 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <section id="account-deletion" aria-labelledby="account-deletion-heading" class="scroll-mt-24 rounded-lg border border-red-200 bg-red-50 p-4 shadow dark:border-red-900 dark:bg-red-950/30 sm:p-8">
                <header class="mb-6 max-w-2xl">
                    <h2 id="account-deletion-heading" class="text-lg font-semibold text-red-900 dark:text-red-200">Danger zone</h2>
                    <p class="mt-1 text-sm text-red-800 dark:text-red-300">Account deletion is permanent and separate from profile information and password changes.</p>
                </header>
                <DeleteUserForm class="max-w-xl" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
