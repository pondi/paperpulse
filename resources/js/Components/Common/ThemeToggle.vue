<template>
  <button
    type="button"
    :aria-label="`Switch to ${nextLabel} mode`"
    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
    @click="toggleTheme"
  >
    <span v-if="mode === 'dark'">🌙</span>
    <span v-else-if="mode === 'light'">☀️</span>
    <span v-else>🖥️</span>
    <span class="hidden sm:inline">{{ label }}</span>
  </button>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

// Modes: 'light' | 'dark' | 'system'
const mode = ref('system');

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
    } else if (saved === 'light' || saved === 'dark') {
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
  } catch {}
});
</script>

