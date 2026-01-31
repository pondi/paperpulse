<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';
import DangerButton from '@/Components/Buttons/DangerButton.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import InputError from '@/Components/Forms/InputError.vue';
import Modal from '@/Components/Common/Modal.vue';
import { 
    FolderIcon,
    PlusIcon,
    TrashIcon,
    PencilIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';

interface Category {
    id: number;
    name: string;
    color: string;
    document_count: number;
    can_edit: boolean;
}

interface Props {
    categories: Category[];
}

const props = defineProps<Props>();

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedCategory = ref<Category | null>(null);

const createForm = useForm({
    name: '',
    color: '#3B82F6', // Default blue
});

const editForm = useForm({
    id: 0,
    name: '',
    color: '',
});


const colors = [
    '#EF4444', // red
    '#F59E0B', // amber
    '#10B981', // emerald
    '#3B82F6', // blue
    '#8B5CF6', // violet
    '#EC4899', // pink
    '#6B7280', // gray
    '#059669', // green
    '#7C3AED', // purple
    '#DC2626', // red-600
];

const createCategory = () => {
    createForm.post(route('categories.store'), {
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        }
    });
};

const updateCategory = () => {
    editForm.put(route('categories.update', editForm.id), {
        onSuccess: () => {
            editForm.reset();
            showEditModal.value = false;
        }
    });
};

const deleteCategory = () => {
    if (!selectedCategory.value) return;
    
    router.delete(route('categories.destroy', selectedCategory.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedCategory.value = null;
        }
    });
};


const openEditModal = (category: Category) => {
    selectedCategory.value = category;
    editForm.id = category.id;
    editForm.name = category.name;
    editForm.color = category.color;
    showEditModal.value = true;
};

const openDeleteModal = (category: Category) => {
    selectedCategory.value = category;
    showDeleteModal.value = true;
};

</script>

<template>
    <Head title="Document Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
                    Document Categories
                </h2>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center px-4 py-2 bg-zinc-900 dark:bg-orange-600 border border-transparent rounded-md font-bold text-sm text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <PlusIcon class="h-4 w-4 mr-2" />
                    New Category
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-lg sm:rounded-lg border-t-4 border-orange-600 dark:border-orange-500">
                    <div class="p-6">
                        <div v-if="categories.length === 0" class="text-center py-12">
                            <FolderIcon class="mx-auto h-16 w-16 text-zinc-400 dark:text-zinc-600" />
                            <h3 class="mt-4 text-lg font-black text-zinc-900 dark:text-zinc-100">No categories</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Get started by creating a new category.
                            </p>
                            <div class="mt-8">
                                <button
                                    @click="showCreateModal = true"
                                    class="inline-flex items-center px-6 py-3 bg-zinc-900 dark:bg-orange-600 border border-transparent rounded-md font-bold text-sm text-white shadow-sm hover:shadow hover:bg-zinc-800 dark:hover:bg-orange-700 transition-all duration-200"
                                >
                                    <PlusIcon class="h-4 w-4 mr-2" />
                                    New Category
                                </button>
                            </div>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="category in categories"
                                :key="category.id"
                                class="relative group bg-white dark:bg-zinc-900 border border-amber-200 dark:border-zinc-700 rounded-lg p-4 shadow-lg hover:shadow-xl transition-all duration-200"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center">
                                        <div
                                            class="w-10 h-10 rounded-lg flex items-center justify-center"
                                            :style="{ backgroundColor: category.color + '20' }"
                                        >
                                            <FolderIcon 
                                                class="h-6 w-6" 
                                                :style="{ color: category.color }"
                                            />
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                                {{ category.name }}
                                            </h3>
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ category.document_count }} document{{ category.document_count !== 1 ? 's' : '' }}
                                            </p>
                                        </div>
                                    </div>
                                    
                                </div>

                                <div class="mt-4 flex items-center justify-between">
                                    <Link
                                        :href="route('documents.index', { category: category.id })"
                                        class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 text-sm"
                                    >
                                        View documents →
                                    </Link>
                                    
                                    <div v-if="category.can_edit" class="flex items-center space-x-2">
                                        <button
                                            @click="openEditModal(category)"
                                            class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                        >
                                            <PencilIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            @click="openDeleteModal(category)"
                                            class="text-red-400 hover:text-red-600 dark:hover:text-red-300"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Category Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-100 mb-4">
                    Create New Category
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Category Name" />
                        <TextInput
                            id="name"
                            v-model="createForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Work Documents"
                        />
                        <InputError :message="createForm.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="color" value="Color" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="color in colors"
                                :key="color"
                                @click="createForm.color = color"
                                class="w-10 h-10 rounded-lg border-2 transition-all"
                                :style="{
                                    backgroundColor: color,
                                    borderColor: createForm.color === color ? color : 'transparent'
                                }"
                                :class="{ 'ring-2 ring-offset-2': createForm.color === color }"
                            />
                        </div>
                        <InputError :message="createForm.errors.color" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showCreateModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="createCategory" :disabled="createForm.processing">
                        Create Category
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Edit Category Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-100 mb-4">
                    Edit Category
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <InputLabel for="edit-name" value="Category Name" />
                        <TextInput
                            id="edit-name"
                            v-model="editForm.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="editForm.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="edit-color" value="Color" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="color in colors"
                                :key="color"
                                @click="editForm.color = color"
                                class="w-10 h-10 rounded-lg border-2 transition-all"
                                :style="{
                                    backgroundColor: color,
                                    borderColor: editForm.color === color ? color : 'transparent'
                                }"
                                :class="{ 'ring-2 ring-offset-2': editForm.color === color }"
                            />
                        </div>
                        <InputError :message="editForm.errors.color" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showEditModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="updateCategory" :disabled="editForm.processing">
                        Save Changes
                    </PrimaryButton>
                </div>
            </div>
        </Modal>


        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-black text-zinc-900 dark:text-zinc-100">
                    Delete Category
                </h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Are you sure you want to delete "{{ selectedCategory?.name }}"? 
                    All documents in this category will be uncategorized.
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Cancel
                    </SecondaryButton>
                    <DangerButton @click="deleteCategory">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>