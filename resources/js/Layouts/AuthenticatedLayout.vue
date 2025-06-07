<template>
    <div>
      <TransitionRoot as="template" :show="sidebarOpen">
        <Dialog class="relative z-50 xl:hidden" @close="sidebarOpen = false">
          <TransitionChild as="template" enter="transition-opacity ease-linear duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity ease-linear duration-300" leave-from="opacity-100" leave-to="opacity-0">
            <div class="fixed inset-0 bg-gray-900/80" />
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
                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-gray-900 px-6 ring-1 ring-white/10">
                  <div class="flex h-16 shrink-0 items-center">
                    <Link :href="route('dashboard')">
                      <ApplicationLogo class="h-8 w-auto fill-current text-gray-200" />
                    </Link>
                  </div>
                  <nav class="flex flex-1 flex-col">
                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                      <li>
                        <ul role="list" class="-mx-2 space-y-1">
                          <li v-for="item in navigation" :key="item.name">
                            <Link :href="item.href" :class="[item.current ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                              <component :is="item.icon" class="size-6 shrink-0" aria-hidden="true" />
                              {{ __(item.name.toLowerCase()) }}
                            </Link>
                            <div v-if="item.children" class="space-y-1 mt-1">
                              <Link
                                v-for="child in item.children"
                                :key="child.name"
                                :href="child.href"
                                :class="[child.current ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white', 'group flex gap-x-3 rounded-md py-1 pl-11 pr-2 text-sm/6 font-medium']"
                              >
                                {{ __(child.name.toLowerCase()) }}
                              </Link>
                            </div>
                          </li>
                        </ul>
                      </li>
                      <li class="-mx-6 mt-auto">
                        <Menu as="div" class="relative w-full">
                          <MenuButton class="flex w-full items-center gap-x-4 px-6 py-3 text-sm/6 font-semibold text-white hover:bg-gray-800">
                            <span class="sr-only">Open user menu</span>
                            <span class="flex items-center gap-x-4">
                              <span class="flex-1">{{ $page.props.auth.user.name }}</span>
                              <ChevronDownIcon class="size-5 text-gray-400" aria-hidden="true" />
                            </span>
                          </MenuButton>
                          <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                            <MenuItems class="absolute bottom-full left-0 right-0 mb-2 w-full origin-bottom-right rounded-md bg-gray-800 py-2 shadow-lg ring-1 ring-gray-700 focus:outline-none">
                              <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                                <Link :href="item.href" :method="item.method" :class="[active ? 'bg-gray-700 text-gray-100' : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100', 'block px-3 py-1 text-sm/6']">
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
        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-black/10 px-6 ring-1 ring-white/5">
          <div class="flex h-16 shrink-0 items-center">
            <Link :href="route('dashboard')">
              <ApplicationLogo class="h-8 w-auto fill-current text-gray-200" />
            </Link>
          </div>
          <nav class="flex flex-1 flex-col">
            <ul role="list" class="flex flex-1 flex-col gap-y-7">
              <li>
                <ul role="list" class="-mx-2 space-y-1">
                  <li v-for="item in navigation" :key="item.name">
                    <Link :href="item.href" :class="[item.current ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white', 'group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold']">
                      <component :is="item.icon" class="size-6 shrink-0" aria-hidden="true" />
                      {{ __(item.name.toLowerCase()) }}
                    </Link>
                    <div v-if="item.children" class="space-y-1 mt-1">
                      <Link
                        v-for="child in item.children"
                        :key="child.name"
                        :href="child.href"
                        :class="[child.current ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white', 'group flex gap-x-3 rounded-md py-1 pl-11 pr-2 text-sm/6 font-medium']"
                      >
                        {{ __(child.name.toLowerCase()) }}
                      </Link>
                    </div>
                  </li>
                </ul>
              </li>
              <li class="-mx-6 mt-auto">
                <Menu as="div" class="relative w-full">
                  <MenuButton class="flex w-full items-center gap-x-4 px-6 py-3 text-sm/6 font-semibold text-white hover:bg-gray-800">
                    <span class="sr-only">Open user menu</span>
                    <span class="flex items-center gap-x-4">
                      <span class="flex-1">{{ $page.props.auth.user.name }}</span>
                      <ChevronDownIcon class="size-5 text-gray-400" aria-hidden="true" />
                    </span>
                  </MenuButton>
                  <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <MenuItems class="absolute bottom-full left-0 right-0 mb-2 w-full origin-bottom-right rounded-md bg-gray-800 py-2 shadow-lg ring-1 ring-gray-700 focus:outline-none">
                      <MenuItem v-for="item in userNavigation" :key="item.name" v-slot="{ active }">
                        <Link :href="item.href" :method="item.method" :class="[active ? 'bg-gray-700 text-gray-100' : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100', 'block px-3 py-1 text-sm/6']">
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

      <div class="xl:pl-72">
        <!-- Sticky search header -->
        <div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-6 border-b border-white/5 bg-gray-900 px-4 shadow-sm sm:px-6 lg:px-8">
          <button type="button" class="-m-2.5 p-2.5 text-white xl:hidden" @click="sidebarOpen = true">
            <span class="sr-only">Open sidebar</span>
            <Bars3Icon class="size-5" aria-hidden="true" />
          </button>

          <div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
            <div class="flex flex-1 items-center">
              <SearchBar />
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
  ChartPieIcon,
  DocumentDuplicateIcon,
  FolderIcon,
  HomeIcon,
  UsersIcon,
  XMarkIcon,
  CloudArrowDownIcon,
} from '@heroicons/vue/24/outline'
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SearchBar from '@/Components/SearchBar.vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false)
const page = usePage();
const __ = (key) => page.props.language.messages[key] || key;

const navigation = [
  { name: 'dashboard', href: route('dashboard'), icon: HomeIcon, current: route().current('dashboard') },
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
  { name: 'upload', href: route('documents.upload'), icon: FolderIcon, current: route().current('documents.upload') },
  { name: 'scanner_imports', href: route('pulsedav.index'), icon: CloudArrowDownIcon, current: route().current('pulsedav.*') },
  { name: 'job_status', href: route('jobs.index'), icon: ChartPieIcon, current: route().current('jobs.index') },
]

const userNavigation = [
  { name: 'profile', href: route('profile.edit') },
  { name: 'logout', href: route('logout'), method: 'post' }
]
</script>
