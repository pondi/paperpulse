<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout title="Verify your email" description="Confirm your email address to finish setting up your account and access PaperPulse.">
        <Head title="Email Verification" />

        <div role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-800 dark:bg-green-950/40 dark:text-green-300" v-if="verificationLinkSent">
            A new verification link has been sent to the email address you provided during registration.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <PrimaryButton class="min-h-11 rounded-lg px-5" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Resend Verification Email
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-zinc-600 underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-zinc-300"
                    >Log Out</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
