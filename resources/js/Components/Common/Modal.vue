<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const isMounted = ref(false);
const modalRef = ref(null);
const previousActiveElement = ref(null);

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            previousActiveElement.value = document.activeElement;
            document.body.style.overflow = 'hidden';
            nextTick(() => {
                trapFocus();
            });
        } else {
            document.body.style.overflow = null;
            if (previousActiveElement.value && typeof previousActiveElement.value.focus === 'function') {
                nextTick(() => {
                    previousActiveElement.value.focus();
                    previousActiveElement.value = null;
                });
            }
        }
    }
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const getFocusableElements = () => {
    if (!modalRef.value) return [];
    return Array.from(
        modalRef.value.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
    );
};

const trapFocus = () => {
    const focusable = getFocusableElements();
    if (focusable.length > 0) {
        focusable[0].focus();
    }
};

const handleTabKey = (e) => {
    if (!props.show || !modalRef.value) return;

    const focusable = getFocusableElements();
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey) {
        if (document.activeElement === first) {
            e.preventDefault();
            last.focus();
        }
    } else {
        if (document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
    if (e.key === 'Tab' && props.show) {
        handleTabKey(e);
    }
};

onMounted(() => {
    isMounted.value = true;
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <Transition leave-active-class="duration-200">
            <div v-show="show" ref="modalRef" class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" role="dialog" aria-modal="true" scroll-region>
                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-show="show" class="fixed inset-0 transform transition-all" @click="close">
                        <div class="absolute inset-0 bg-zinc-500 dark:bg-zinc-900 opacity-75" />
                    </div>
                </Transition>

                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <div
                        v-show="show"
                        class="mb-6 bg-white dark:bg-zinc-800 rounded-lg overflow-hidden shadow-2xl border-2 border-amber-200 dark:border-zinc-700 transform transition-all sm:w-full sm:mx-auto"
                        :class="maxWidthClass"
                    >
                        <slot v-if="show" />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
