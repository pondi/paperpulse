<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';

defineProps({ show: Boolean, title: { type: String, default: 'Document preview' } });
defineEmits(['close', 'after-leave']);
</script>

<template>
    <TransitionRoot as="template" :show="show" @after-leave="$emit('after-leave')">
        <Dialog class="relative z-50" @close="$emit('close')">
            <TransitionChild as="template" enter="transition-opacity duration-200" enter-from="opacity-0" enter-to="opacity-100" leave="transition-opacity duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-zinc-950/25" />
            </TransitionChild>
            <div class="pointer-events-none fixed inset-0 overflow-hidden">
                <TransitionChild as="template" enter="transform transition ease-out duration-300" enter-from="translate-x-full" enter-to="translate-x-0" leave="transform transition ease-in duration-200" leave-from="translate-x-0" leave-to="translate-x-full">
                    <DialogPanel class="pointer-events-auto absolute inset-y-0 right-0 flex w-full min-w-0 flex-col overflow-hidden border-l border-zinc-200 bg-white shadow-2xl sm:w-[calc(100%-3rem)] dark:border-zinc-700 dark:bg-zinc-900" aria-label="Selected document">
                        <DialogTitle class="sr-only">{{ title }}</DialogTitle>
                        <slot />
                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
