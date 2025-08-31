<template>
    <Head title="PaperPulse - Upload Debug" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Upload Debug Test</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Native Form Upload Test</h3>
                    
                    <!-- Native HTML form -->
                    <form action="/documents/store" method="POST" enctype="multipart/form-data" @submit="logFormData">
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input type="hidden" name="file_type" value="document" />
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Select Multiple Files:</label>
                            <input 
                                type="file" 
                                name="files[]" 
                                multiple 
                                accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50"
                            />
                        </div>
                        
                        <button 
                            type="submit"
                            class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        >
                            Upload Files (Native Form)
                        </button>
                    </form>
                    
                    <hr class="my-8" />
                    
                    <h3 class="text-lg font-semibold mb-4">Inertia Form Upload Test</h3>
                    
                    <!-- Inertia form -->
                    <div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">Select Multiple Files:</label>
                            <input 
                                type="file" 
                                multiple 
                                @change="handleFileSelect"
                                accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50"
                            />
                        </div>
                        
                        <div v-if="selectedFiles.length > 0" class="mb-4">
                            <p class="text-sm text-gray-600">Selected files:</p>
                            <ul class="list-disc list-inside">
                                <li v-for="(file, index) in selectedFiles" :key="index" class="text-sm">
                                    {{ file.name }} ({{ formatFileSize(file.size) }})
                                </li>
                            </ul>
                        </div>
                        
                        <button 
                            @click="uploadWithInertia"
                            :disabled="selectedFiles.length === 0"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:bg-gray-400"
                        >
                            Upload Files (Inertia)
                        </button>
                    </div>
                    
                    <!-- Debug output -->
                    <div v-if="debugInfo" class="mt-8 p-4 bg-gray-100 dark:bg-gray-900 rounded">
                        <h4 class="font-semibold mb-2">Debug Information:</h4>
                        <pre class="text-xs overflow-x-auto">{{ debugInfo }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'));

const selectedFiles = ref<File[]>([]);
const debugInfo = ref<any>(null);

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files) {
        selectedFiles.value = Array.from(input.files);
        debugInfo.value = {
            fileCount: selectedFiles.value.length,
            files: selectedFiles.value.map(f => ({
                name: f.name,
                size: f.size,
                type: f.type,
            })),
        };
    }
}

function logFormData(event: Event) {
    const form = event.target as HTMLFormElement;
    const formData = new FormData(form);
    
    const files = formData.getAll('files[]');
    debugInfo.value = {
        method: 'Native Form Submit',
        fileCount: files.length,
        files: files.map((f: any) => ({
            name: f.name,
            size: f.size,
            type: f.type,
        })),
    };
    
    console.log('Native form data:', formData);
}

function uploadWithInertia() {
    const form = useForm({
        files: selectedFiles.value,
        file_type: 'document',
    });
    
    debugInfo.value = {
        method: 'Inertia Form Submit',
        fileCount: selectedFiles.value.length,
        formData: {
            files: selectedFiles.value.map(f => ({
                name: f.name,
                size: f.size,
                type: f.type,
            })),
            file_type: 'document',
        },
    };
    
    form.post('/documents/store', {
        onSuccess: () => {
            selectedFiles.value = [];
            debugInfo.value = { ...debugInfo.value, result: 'Success!' };
        },
        onError: (errors) => {
            debugInfo.value = { ...debugInfo.value, errors };
        },
        onProgress: (progress) => {
            debugInfo.value = { ...debugInfo.value, progress };
        },
    });
}
</script>