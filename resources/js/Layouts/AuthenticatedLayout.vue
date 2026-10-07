<script setup>
import { ref } from 'vue';
import { Dialog, DialogPanel, Menu, MenuButton, MenuItem, MenuItems, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { Bars3Icon, XMarkIcon, PlusIcon, ArrowUpTrayIcon, CameraIcon, ArrowDownTrayIcon, UserCircleIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/Components/Navigation/AppSidebar.vue';
import WorkspaceNavigation from '@/Components/Navigation/WorkspaceNavigation.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import NotificationBell from '@/Components/Features/NotificationBell.vue';
import ThemeToggle from '@/Components/Common/ThemeToggle.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
import Toast from '@/Components/Common/Toast.vue';

const page = usePage();
const sidebarOpen = ref(false);
const showPreviewModal = ref(false);
const previewItem = ref(null);
const openPreview = item => { previewItem.value = item; showPreviewModal.value = true; };
const clearScannerCache = async () => {
    if ('caches' in window) {
        const names = await caches.keys();
        await Promise.all(names.filter(name => name.startsWith('paperpulse-scanner-')).map(name => caches.delete(name)));
    }
};
const logout = async () => {
    try {
        await clearScannerCache();
    } finally {
        router.post(route('logout'));
    }
};
const addActions = [
    { label: 'Upload files', description: 'PDFs, images and Office files', href: route('documents.upload'), icon: ArrowUpTrayIcon },
    { label: 'Scan a document', description: 'Capture with your camera', href: route('scanner'), icon: CameraIcon },
    { label: 'Import from scanner', description: 'Browse your connected files', href: route('pulsedav.index'), icon: ArrowDownTrayIcon },
];
</script>

<template>
    <div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        <TransitionRoot as="template" :show="sidebarOpen">
            <Dialog class="relative z-50 lg:hidden" @close="sidebarOpen = false">
                <TransitionChild as="template" enter="transition-opacity duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity duration-200" leave-from="opacity-100" leave-to="opacity-0">
                    <div class="fixed inset-0 bg-zinc-950/60" />
                </TransitionChild>
                <div class="fixed inset-0 flex">
                    <TransitionChild as="template" enter="transition duration-200" enter-from="-translate-x-full" enter-to="translate-x-0" leave="transition duration-200" leave-from="translate-x-0" leave-to="-translate-x-full">
                        <DialogPanel class="relative w-72 max-w-[85vw]">
                            <button type="button" aria-label="Close navigation" class="absolute right-3 top-5 z-10 rounded p-1 text-zinc-500 hover:text-zinc-900 dark:hover:text-white" @click="sidebarOpen = false"><XMarkIcon class="h-5 w-5" /></button>
                            <AppSidebar @navigate="sidebarOpen = false" />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-48 lg:block"><AppSidebar /></aside>

        <div class="lg:pl-48">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-zinc-200 bg-white px-4  sm:gap-5 sm:px-6 lg:px-5 dark:border-zinc-800 dark:bg-zinc-900">
                <button type="button" aria-label="Open navigation" class="rounded p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden dark:text-zinc-300 dark:hover:bg-zinc-800" @click="sidebarOpen = true"><Bars3Icon class="h-5 w-5" /></button>
                <div class="flex min-w-0 flex-1 items-center"><SearchBar v-if="page.url.split('?')[0] !== '/library'" @preview="openPreview" /></div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <Menu as="div" class="relative">
                        <MenuButton dusk="add-document" class="inline-flex items-center gap-2 rounded bg-orange-600 px-3 py-2 text-sm font-semibold text-white  hover:bg-orange-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600">
                            <PlusIcon class="h-4 w-4" aria-hidden="true" /><span class="hidden sm:inline">Add document</span><span class="sr-only sm:hidden">Add document</span><ChevronDownIcon class="hidden h-3.5 w-3.5 sm:block" aria-hidden="true" />
                        </MenuButton>
                        <MenuItems class="absolute right-0 mt-2 w-72 rounded border border-zinc-200 bg-white p-1.5 shadow-lg focus:outline-none dark:border-zinc-700 dark:bg-zinc-900">
                            <MenuItem v-for="action in addActions" :key="action.label" v-slot="{ active }">
                                <Link :href="action.href" :class="[active ? 'bg-zinc-100 dark:bg-zinc-800' : '', 'flex gap-3 rounded px-3 py-3']">
                                    <component :is="action.icon" class="mt-0.5 h-5 w-5 text-orange-600 dark:text-orange-400" aria-hidden="true" />
                                    <span><span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ action.label }}</span><span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ action.description }}</span></span>
                                </Link>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                    <ThemeToggle />
                    <NotificationBell />
                    <Menu as="div" class="relative">
                        <MenuButton aria-label="Account menu" class="flex items-center gap-2 rounded p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"><UserCircleIcon class="h-7 w-7" /><span class="hidden text-sm text-zinc-700 xl:inline dark:text-zinc-300">{{ page.props.auth.user.name }}</span></MenuButton>
                        <MenuItems class="absolute right-0 mt-2 w-48 rounded border border-zinc-200 bg-white p-1.5 shadow-lg focus:outline-none dark:border-zinc-700 dark:bg-zinc-900">
                            <MenuItem v-slot="{ active }"><Link :href="route('profile.edit')" :class="[active ? 'bg-zinc-100 dark:bg-zinc-800' : '', 'block rounded px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300']">Your profile</Link></MenuItem>
                            <MenuItem v-slot="{ active }"><Link :href="route('preferences.index')" :class="[active ? 'bg-zinc-100 dark:bg-zinc-800' : '', 'block rounded px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300']">Settings</Link></MenuItem>
                            <MenuItem v-slot="{ active }"><button type="button" @click="logout" :class="[active ? 'bg-zinc-100 dark:bg-zinc-800' : '', 'block w-full rounded px-3 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300']">Log out</button></MenuItem>
                        </MenuItems>
                    </Menu>
                </div>
            </header>
            <WorkspaceNavigation />
            <main class="mx-auto max-w-none px-4 py-6 sm:px-6 lg:px-5">
                <header v-if="$slots.header" class="mb-4"><slot name="header" /></header>
                <div class="workspace-page"><slot /></div>
            </main>
        </div>
        <FilePreviewModal :show="showPreviewModal" :item="previewItem" @close="showPreviewModal = false" />
        <Toast />
    </div>
</template>
