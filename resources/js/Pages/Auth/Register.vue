<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    invitation: Object,
});

const form = useForm({
    name: '',
    email: props.invitation?.email || '',
    password: '',
    password_confirmation: '',
    invitation_token: props.invitation?.token || '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout title="Create your account" description="Set up your PaperPulse account using your personal invitation link.">
        <Head title="Register" />

        <form @submit.prevent="submit">
            <div v-if="invitation" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md">
                <p role="status" class="text-sm text-green-800">
                    You've been invited to register with the email: <strong>{{ invitation.email }}</strong>
                </p>
            </div>
            
            <div v-if="!invitation" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-md">
                <p role="status" class="text-sm text-amber-900 dark:text-amber-200">
                    Registration requires an invitation. Please contact an administrator for an invitation link.
                </p>
            </div>

            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    :aria-invalid="!!form.errors.name"
                    :aria-describedby="form.errors.name ? 'name-error' : undefined"
                />

                <InputError id="name-error" class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    :disabled="!!invitation"
                    required
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
                    autocomplete="new-password"
                    :aria-invalid="!!form.errors.password"
                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                />

                <InputError id="password-error" class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    :aria-invalid="!!form.errors.password_confirmation"
                    :aria-describedby="form.errors.password_confirmation ? 'password-confirmation-error' : undefined"
                />

                <InputError id="password-confirmation-error" class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <InputError id="invitation-error" class="mt-2" :message="form.errors.invitation_token" />

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="route('login')"
                    class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-amber-800 underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-amber-300"
                >
                    Already registered?
                </Link>

                <PrimaryButton class="min-h-11 rounded-lg px-5" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
