<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ file: Object, headers: Array, rows: Array, mapping: Object, mapping_error: String });
const fields = [
    ['transaction_date', 'Transaction date'], ['description', 'Description'], ['amount', 'Signed amount'],
    ['debit', 'Money out'], ['credit', 'Money in'], ['balance', 'Balance after transaction'],
    ['posting_date', 'Posting date'], ['reference', 'Reference'], ['counterparty', 'Counterparty'], ['currency', 'Currency'],
];
const form = useForm({ mapping: Object.fromEntries(fields.map(([field]) => [field, props.mapping?.[field] ?? null])) });
form.mapping.date_format = props.mapping?.date_format ?? null;
const submit = () => form.patch(route('bank-statements.csv-mapping.update', props.file.id));
</script>

<template>
    <AuthenticatedLayout>
        <Head title="CSV column mapping" />
        <div class="mx-auto max-w-5xl p-6 flex flex-col gap-6 text-zinc-900 dark:text-zinc-100">
            <h1 class="text-xl font-semibold">Map CSV columns: {{ file.name }}</h1>
            <p>Choose a signed amount column, or separate money out and money in columns. Money out can be positive or negative; money in must be positive.</p>
            <p v-if="mapping_error" class="text-red-600 dark:text-red-400">{{ mapping_error }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr><th v-for="(header, index) in headers" :key="index" class="p-2 text-left">{{ header }}</th></tr></thead>
                    <tbody><tr v-for="(row, index) in rows" :key="index"><td v-for="(value, column) in row" :key="column" class="p-2 whitespace-pre-wrap">{{ value }}</td></tr></tbody>
                </table>
            </div>
            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label v-for="[field, label] in fields" :key="field" class="flex flex-col gap-1">
                        {{ label }}
                        <select v-model="form.mapping[field]" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
                            <option :value="null">Not present</option>
                            <option v-for="(header, index) in headers" :key="index" :value="index">{{ header }} ({{ index + 1 }})</option>
                        </select>
                        <span v-if="form.errors[`mapping.${field}`]" class="text-red-600">{{ form.errors[`mapping.${field}`] }}</span>
                    </label>
                    <label class="flex flex-col gap-1">Date format
                        <select v-model="form.mapping.date_format" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
                            <option :value="null">Detect unambiguous dates</option>
                            <option value="Y-m-d">Year-month-day</option>
                            <option value="d/m/Y">Day/month/year</option>
                            <option value="m/d/Y">Month/day/year</option>
                            <option value="d.m.Y">Day.month.year</option>
                            <option value="d-m-Y">Day-month-year</option>
                        </select>
                    </label>
                </div>
                <p v-if="form.errors.mapping" class="text-red-600 dark:text-red-400">{{ form.errors.mapping }}</p>
                <div class="flex gap-4 items-center">
                    <button :disabled="form.processing" class="rounded-md bg-zinc-900 dark:bg-amber-600 px-4 py-2 text-white disabled:opacity-50">Save and retry import</button>
                    <Link :href="route('files.index')">Back to file</Link>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
