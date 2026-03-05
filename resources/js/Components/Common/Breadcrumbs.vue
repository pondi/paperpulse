<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Crumb {
    label: string;
    href?: string;
}

defineProps<{
    crumbs: Crumb[];
}>();
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-4">
        <ol class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <li v-for="(crumb, i) in crumbs" :key="crumb.label" class="flex items-center gap-2">
                <Link
                    v-if="crumb.href"
                    :href="crumb.href"
                    class="hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors"
                >
                    {{ crumb.label }}
                </Link>
                <span v-else class="text-zinc-900 dark:text-zinc-100 font-medium truncate max-w-[200px]">
                    {{ crumb.label }}
                </span>
                <span v-if="i < crumbs.length - 1" class="text-zinc-400 dark:text-zinc-500">/</span>
            </li>
        </ol>
    </nav>
</template>
