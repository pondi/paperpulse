<template>
  <button
    type="button"
    :aria-label="`${label} theme. Switch to ${nextLabel} mode`"
    :title="`${label} theme`"
    class="relative flex rounded-md p-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
    @click="toggleTheme"
  >
    <span class="sr-only">Toggle theme</span>
    <MoonIcon v-if="mode === 'dark'" class="h-6 w-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
    <SunIcon v-else-if="mode === 'light'" class="h-6 w-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
    <ComputerDesktopIcon v-else class="h-6 w-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
  </button>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { MoonIcon, SunIcon, ComputerDesktopIcon } from '@heroicons/vue/24/outline';

// Modes: 'light' | 'dark' | 'system'
const mode = ref('system');
let removeMediaListener;

const applyMode = (m) => {
  if (m === 'system') {
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', prefersDark);
  } else {
    document.documentElement.classList.toggle('dark', m === 'dark');
  }
};

const persist = (m) => {
  try {
    localStorage.setItem('theme', m);
  } catch {}
};

const load = () => {
  try {
    const saved = localStorage.getItem('theme');
    if (saved === 'light' || saved === 'dark' || saved === 'system') {
      mode.value = saved;
    } else if (saved) {
      // Back-compat for older values
      mode.value = saved === 'dark' ? 'dark' : 'light';
    }
  } catch {}
};

const label = computed(() => {
  if (mode.value === 'dark') return 'Dark';
  if (mode.value === 'light') return 'Light';
  return 'System';
});

const nextLabel = computed(() => {
  if (mode.value === 'dark') return 'light';
  if (mode.value === 'light') return 'system';
  return 'dark';
});

const toggleTheme = () => {
  if (mode.value === 'dark') mode.value = 'light';
  else if (mode.value === 'light') mode.value = 'system';
  else mode.value = 'dark';
  persist(mode.value);
  applyMode(mode.value);
};

onMounted(() => {
  load();
  applyMode(mode.value);
  // Watch system changes if using system mode
  try {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = () => {
      if (mode.value === 'system') applyMode('system');
    };
    media.addEventListener?.('change', handler);
    removeMediaListener = () => media.removeEventListener?.('change', handler);
  } catch {}
});
onBeforeUnmount(() => removeMediaListener?.());
</script>
