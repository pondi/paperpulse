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
        <ol class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <li v-for="(crumb, i) in crumbs" :key="`${i}-${crumb.label}`" class="flex min-w-0 max-w-full items-center gap-2">
                <Link
                    v-if="crumb.href"
                    :href="crumb.href"
                    class="min-w-0 break-words hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors"
                >
                    {{ crumb.label }}
                </Link>
                <span v-else :aria-current="i === crumbs.length - 1 ? 'page' : undefined" class="min-w-0 break-words font-medium text-zinc-900 dark:text-zinc-100">
                    {{ crumb.label }}
                </span>
                <span v-if="i < crumbs.length - 1" aria-hidden="true" class="shrink-0 text-zinc-400 dark:text-zinc-500">/</span>
            </li>
        </ol>
    </nav>
</template>
