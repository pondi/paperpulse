<template>
    <Head title="PaperPulse - Upload files" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Upload files</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="rounded-md bg-green-50 p-4" v-if="uploadSuccess">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <CheckCircleIcon class="h-5 w-5 text-green-400" aria-hidden="true" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">File uploaded successfully</p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button"
                                    class="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 focus:ring-offset-green-50"
                                    @click="uploadSuccess = false">
                                    <span class="sr-only">Dismiss</span>
                                    <XMarkIcon class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Files to upload</h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500">
                            <p>Choose the files you want to upload. <br />
                                They will automatically be processed. You can view the status under Processing.</p>
                        </div>
                        <form class="mt-5 sm:flex sm:items-center" ref="fileUpload" @submit.prevent="submit">
                            <div class="w-full sm:max-w-xs">
                                <input type="file" multiple @input="form.files = $event.target.files"
                                    class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" />
                                <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                                    {{ form.progress.percentage }}%
                                </progress>
                            </div>
                            <button type="submit"
                                class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:ml-3 sm:mt-0 sm:w-auto">Upload</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';

    import {
        useForm
    } from '@inertiajs/vue3';
    import {
        CheckCircleIcon,
        XMarkIcon
    } from '@heroicons/vue/20/solid'
    import {
        ref
    } from 'vue';

    let uploadSuccess = ref(false);
    let fileUpload = ref(null);

    const form = useForm({
        files: null,
    });

    function submit() {
        form.post('/file/store', {
            onSuccess: () => {
                fileUpload.value.reset();
                uploadSuccess.value = true;
                setTimeout(() => {
                    uploadSuccess.value = false;
                }, 5000);
            },
            onProgress: (event) => {
                form.progress = {
                    percentage: Math.round((event.loaded / event.total) * 100),
                };
            },
        });
    }

</script>
