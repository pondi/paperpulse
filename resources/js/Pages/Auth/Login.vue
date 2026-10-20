<script setup>
import Checkbox from '@/Components/Forms/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout title="Sign in" description="Access your saved documents, receipts and household records.">
        <Head title="Log in" />

        <div v-if="status" role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-800 dark:bg-green-950/40 dark:text-green-300">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    :aria-invalid="!!form.errors.email"
                    :aria-describedby="form.errors.email ? 'email-error' : undefined"
                />

                <InputError id="email-error" class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    :aria-invalid="!!form.errors.password"
                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                />

                <InputError id="password-error" class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-zinc-600 dark:text-zinc-400">Remember me</span>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-amber-800 underline-offset-4 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-amber-300"
                >
                    Forgot your password?
                </Link>

                <PrimaryButton class="min-h-11 rounded-lg px-5" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Log in
                </PrimaryButton>
            </div>
        </form>

        <p v-if="!canResetPassword" class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">Need access? Ask your administrator for help with your account.</p>
    </GuestLayout>
</template>
