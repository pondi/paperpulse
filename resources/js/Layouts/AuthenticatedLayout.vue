<template>
    <div>
      <TransitionRoot as="template" :show="sidebarOpen">
        <Dialog class="relative z-50 xl:hidden" @close="sidebarOpen = false">
          <TransitionChild as="template" enter="transition-opacity ease-linear duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity ease-linear duration-300" leave-from="opacity-100" leave-to="opacity-0">
            <div class="fixed inset-0 bg-zinc-900/80" />
          </TransitionChild>

          <div class="fixed inset-0 flex">
            <TransitionChild as="template" enter="transition ease-in-out duration-300 transform" enter-from="-translate-x-full" enter-to="translate-x-0" leave="transition ease-in-out duration-300 transform" leave-from="translate-x-0" leave-to="-translate-x-full">
              <DialogPanel class="relative mr-16 flex w-full max-w-xs flex-1">
                <TransitionChild as="template" enter="ease-in-out duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in-out duration-300" leave-from="opacity-100" leave-to="opacity-0">
                  <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button type="button" class="-m-2.5 p-2.5" @click="sidebarOpen = false">
                      <span class="sr-only">Close sidebar</span>
                      <XMarkIcon class="size-6 text-white" aria-hidden="true" />
                    </button>
                  </div>
                </TransitionChild>
                <!-- Sidebar component, swap this element with another sidebar if you like -->
                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white dark:bg-zinc-900 px-6 ring-1 ring-zinc-900/5 dark:ring-white/10 border-r-4 border-amber-600 dark:border-amber-500">
                  <div class="flex h-16 shrink-0 items-center">
                    <Link :href="route('dashboard')" class="flex items-center gap-2">
                      <ApplicationLogo class="h-10 w-10" />
                      <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">PaperPulse</span>
                    </Link>
                  </div>
                  <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                      <li>
                        <ul role="list" class="-mx-2 space-y-1">
                          <li v-for="item in navigation" :key="item.name">
                            <Link :href="item.href" :class="[item.current ? 'bg-amber-100 text-zinc-900 dark:bg-amber-600 dark:text-white border-l-4 border-amber-600 dark:border-amber-400' : 'text-zinc-600 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white border-l-4 border-transparent', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold transition-all duration-200']">
                              <component :is="item.icon" class="size-6 shrink-0" aria-hidden="true" />
                              {{ __(item.name.toLowerCase()) }}
                            </Link>
                            <div v-if="item.children" class="space-y-1 mt-1">
                              <Link
                                v-for="child in item.children"
                                :key="child.name"
                                :href="child.href"
                                :class="[child.current ? 'bg-amber-100 text-zinc-900 dark:bg-amber-600 dark:text-white' : 'text-zinc-600 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white', 'group flex gap-x-3 rounded-md py-1 pl-11 pr-2 text-sm/6 font-medium transition-all duration-200']"
                              >
                                {{ __(child.name.toLowerCase()) }}
                              </Link>
                            </div>
                          </li>
                        </ul>
                      </li>
                      <li class="-mx-6 mt-auto">
                        <Menu as="div" class="relative w-full">
                          <MenuButton class="flex w-full items-center gap-x-4 px-6 py-3 text-sm/6 font-semibold text-zinc-700 hover:bg-amber-50 dark:text-white dark:hover:bg-zinc-800 transition-all duration-200">
                            <span class="sr-only">Open user menu</span>
                            <span class="flex items-center gap-x-4">
                              <span class="flex-1">{{ $page.props.auth.user.name }}</span>
                              <ChevronDownIcon class="size-5 text-zinc-400" aria-hidden="true" />
                            </span>
                          </MenuButton>
                          <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                            <MenuItems class="absolute bottom-full left-0 right-0 mb-2 w-full origin-bottom-right rounded-md bg-white dark:bg-zinc-800 py-2 shadow-xl ring-1 ring-zinc-900/5 dark:ring-zinc-700 focus:outline-none">
                              <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                                <Link :href="item.href" :method="item.method" :as="item.method ? 'button' : 'a'" :class="[active ? 'bg-amber-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-700 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700 dark:hover:text-zinc-100', 'block px-3 py-1 text-sm/6 transition-all duration-200', item.method ? 'w-full text-left' : '']">
                                  {{ __(item.name.toLowerCase()) }}
                                </Link>
                              </MenuItem>
                            </MenuItems>
                          </transition>
                        </Menu>
                      </li>
                    </ul>
                  </nav>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </Dialog>
      </TransitionRoot>

      <!-- Static sidebar for desktop -->
      <div class="hidden xl:fixed xl:inset-y-0 xl:z-50 xl:flex xl:w-72 xl:flex-col">
        <!-- Sidebar component, swap this element with another sidebar if you like -->
        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white dark:bg-zinc-900 px-6 ring-1 ring-zinc-900/5 dark:ring-white/10 border-r-4 border-amber-600 dark:border-amber-500">
          <div class="flex h-16 shrink-0 items-center">
            <Link :href="route('dashboard')" class="flex items-center gap-2">
              <ApplicationLogo class="h-10 w-10" />
              <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">PaperPulse</span>
            </Link>
          </div>
          <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
              <li>
                <ul role="list" class="-mx-2 space-y-1">
                  <li v-for="item in navigation" :key="item.name">
                    <Link :href="item.href" :class="[item.current ? 'bg-amber-100 text-zinc-900 dark:bg-amber-600 dark:text-white border-l-4 border-amber-600 dark:border-amber-400' : 'text-zinc-600 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white border-l-4 border-transparent', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold transition-all duration-200']">
                      <component :is="item.icon" class="size-6 shrink-0" aria-hidden="true" />
                      {{ __(item.name.toLowerCase()) }}
                    </Link>
                    <div v-if="item.children" class="space-y-1 mt-1">
                      <Link
                        v-for="child in item.children"
                        :key="child.name"
                        :href="child.href"
                        :class="[child.current ? 'bg-amber-100 text-zinc-900 dark:bg-amber-600 dark:text-white' : 'text-zinc-600 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white', 'group flex gap-x-3 rounded-md py-1 pl-11 pr-2 text-sm/6 font-medium transition-all duration-200']"
                      >
                        {{ __(child.name.toLowerCase()) }}
                      </Link>
                    </div>
                  </li>
                </ul>
              </li>
              <li class="-mx-6 mt-auto">
                <Menu as="div" class="relative w-full">
                  <MenuButton class="flex w-full items-center gap-x-4 px-6 py-3 text-sm/6 font-semibold text-zinc-700 hover:bg-amber-50 dark:text-white dark:hover:bg-zinc-800 transition-all duration-200">
                    <span class="sr-only">Open user menu</span>
                    <span class="flex items-center gap-x-4">
                      <span class="flex-1">{{ $page.props.auth.user.name }}</span>
                      <ChevronDownIcon class="size-5 text-zinc-400" aria-hidden="true" />
                    </span>
                  </MenuButton>
                  <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <MenuItems class="absolute bottom-full left-0 right-0 mb-2 w-full origin-bottom-right rounded-md bg-white dark:bg-zinc-800 py-2 shadow-xl ring-1 ring-zinc-900/5 dark:ring-zinc-700 focus:outline-none">
                      <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                        <Link :href="item.href" :method="item.method" :as="item.method ? 'button' : 'a'" :class="[active ? 'bg-amber-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-700 hover:bg-amber-50 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-700 dark:hover:text-zinc-100', 'block px-3 py-1 text-sm/6 transition-all duration-200', item.method ? 'w-full text-left' : '']">
                          {{ __(item.name.toLowerCase()) }}
                        </Link>
                      </MenuItem>
                    </MenuItems>
                  </transition>
                </Menu>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      <div class="xl:pl-72 bg-amber-50 dark:bg-zinc-900">
        <!-- Sticky search header -->
        <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-6 border-b-2 border-amber-200 bg-white/90 backdrop-blur-sm px-4 shadow-lg sm:px-6 lg:px-8 dark:border-zinc-700/50 dark:bg-zinc-900/90">
          <button type="button" class="-m-2.5 p-2.5 text-zinc-700 xl:hidden dark:text-white hover:text-amber-600 dark:hover:text-amber-500 transition-colors duration-200" @click="sidebarOpen = true">
            <span class="sr-only">Open sidebar</span>
            <Bars3Icon class="size-5" aria-hidden="true" />
          </button>

          <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
            <div class="flex flex-1 items-center">
              <SearchBar @preview="openPreview" />
            </div>
            <div class="flex items-center gap-x-4 lg:gap-x-6">
              <NotificationBell />
              <ThemeToggle />
              <!-- Profile dropdown -->
              <Menu as="div" class="relative">
                <MenuButton class="-m-1.5 flex items-center p-1.5">
                  <span class="sr-only">Open user menu</span>
                  <div class="flex items-center">
                    <span class="hidden lg:flex lg:items-center">
                      <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300" aria-hidden="true">{{ $page.props.auth.user.name }}</span>
                      <ChevronDownIcon class="ml-2 h-5 w-5 text-zinc-400" aria-hidden="true" />
                    </span>
                    <span class="lg:hidden">
                      <UserCircleIcon class="h-6 w-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
                    </span>
                  </div>
                </MenuButton>
                <transition
                  enter-active-class="transition ease-out duration-100"
                  enter-from-class="transform opacity-0 scale-95"
                  enter-to-class="transform opacity-100 scale-100"
                  leave-active-class="transition ease-in duration-75"
                  leave-from-class="transform opacity-100 scale-100"
                  leave-to-class="transform opacity-0 scale-95"
                >
                  <MenuItems class="absolute right-0 z-10 mt-2.5 w-32 origin-top-right rounded-md bg-white dark:bg-zinc-800 py-2 shadow-xl ring-1 ring-zinc-900/5 dark:ring-zinc-700 focus:outline-none">
                    <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                      <Link
                        :href="item.href"
                        :method="item.method"
                        :as="item.method ? 'button' : 'a'"
                        :class="[active ? 'bg-amber-50 dark:bg-zinc-700' : '', 'block px-3 py-1 text-sm text-zinc-900 dark:text-zinc-100 transition-all duration-200', item.method ? 'w-full text-left' : '']"
                      >
                        {{ __(item.name.toLowerCase()) }}
                      </Link>
                    </MenuItem>
                  </MenuItems>
                </transition>
              </Menu>
            </div>
          </div>
        </div>

        <main class="py-10">
          <!-- Page Heading -->
          <header v-if="$slots.header" class="mb-8 px-4 sm:px-6 lg:px-8">
            <slot name="header" />
          </header>
          
          <!-- Page Content -->
          <div class="px-4 sm:px-6 lg:px-8">
            <slot />
          </div>
        </main>
      </div>

      <!-- Global Preview Modal -->
      <FilePreviewModal
        :show="showPreviewModal"
        :item="previewItem"
        @close="closePreview"
      />

      <!-- Global Toast Notifications -->
      <Toast />
    </div>
</template>

<script setup>
import { ref } from 'vue'
import {
  Dialog,
  DialogPanel,
  Menu,
  MenuButton,
  MenuItem,
  MenuItems,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'
import {
  Bars3Icon,
  MagnifyingGlassIcon,
  ChartPieIcon,
  DocumentDuplicateIcon,
  FolderIcon,
  HomeIcon,
  UsersIcon,
  XMarkIcon,
  CloudArrowDownIcon,
  CloudArrowUpIcon,
  ChartBarIcon,
  UserCircleIcon,
  TagIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import ApplicationLogo from '@/Components/Common/ApplicationLogo.vue';
import SearchBar from '@/Components/Features/SearchBar.vue';
import NotificationBell from '@/Components/Features/NotificationBell.vue';
import ThemeToggle from '@/Components/Common/ThemeToggle.vue';
import FilePreviewModal from '@/Components/Common/FilePreviewModal.vue';
import Toast from '@/Components/Common/Toast.vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false)
const showPreviewModal = ref(false)
const previewItem = ref(null)
const page = usePage();
const __ = (key) => page.props.language.messages[key] || key;

const openPreview = (item) => {
  previewItem.value = item
  showPreviewModal.value = true
}

const closePreview = () => {
  showPreviewModal.value = false
  previewItem.value = null
}

const navigationItems = [
  { name: 'dashboard', href: route('dashboard'), icon: HomeIcon, current: route().current('dashboard') },
  { name: 'search', href: route('search'), icon: MagnifyingGlassIcon, current: route().current('search') },
  {
    name: 'receipts',
    href: route('receipts.index'),
    icon: DocumentDuplicateIcon,
    current: route().current('receipts.*') || route().current('merchants.*') || route().current('vendors.*'),
    children: [
      { name: 'all_receipts', href: route('receipts.index'), current: route().current('receipts.index') },
      { name: 'merchants', href: route('merchants.index'), current: route().current('merchants.*') },
      { name: 'vendors', href: route('vendors.index'), current: route().current('vendors.index') },
    ]
  },
  {
    name: 'documents',
    href: route('documents.index'),
    icon: FolderIcon,
    current: route().current('documents.*') && !route().current('documents.upload'),
    children: [
      { name: 'all_documents', href: route('documents.index'), current: route().current('documents.index') },
      { name: 'shared_with_me', href: route('documents.shared'), current: route().current('documents.shared') },
      { name: 'categories', href: route('documents.categories'), current: route().current('documents.categories') },
    ]
  },
  { name: 'analytics', href: route('analytics.index'), icon: ChartBarIcon, current: route().current('analytics.*') },
  { name: 'tags', href: route('tags.index'), icon: TagIcon, current: route().current('tags.*') },
  { name: 'upload', href: route('documents.upload'), icon: CloudArrowUpIcon, current: route().current('documents.upload') },
  { name: 'scanner_imports', href: route('pulsedav.index'), icon: CloudArrowDownIcon, current: route().current('pulsedav.*') },
  { name: 'job_status', href: route('jobs.index'), icon: ChartPieIcon, current: route().current('jobs.index') },
  { name: 'file_processing', href: route('files.index'), icon: DocumentTextIcon, current: route().current('files.*') }
];

const navigation = navigationItems;

const userNavigation = [
  { name: 'profile', href: route('profile.edit') },
  { name: 'preferences', href: route('preferences.index') },
  { name: 'logout', href: route('logout'), method: 'post' }
]
</script>
