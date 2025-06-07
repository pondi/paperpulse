<template>
  <Head :title="__('preferences')" />
  
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ __('preferences') }}
      </h2>
    </template>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('general_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('general_preferences_description') }}
            </p>
          </header>

          <form @submit.prevent="updatePreferences" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <div>
                <InputLabel for="language" :value="__('language')" />
                <select
                  id="language"
                  v-model="form.language"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
                >
                  <option v-for="(label, value) in options.languages" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.language" />
              </div>

              <div>
                <InputLabel for="timezone" :value="__('timezone')" />
                <select
                  id="timezone"
                  v-model="form.timezone"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
                >
                  <option v-for="timezone in timezones" :key="timezone.value" :value="timezone.value">
                    {{ timezone.label }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.timezone" />
              </div>

              <div>
                <InputLabel for="date_format" :value="__('date_format')" />
                <select
                  id="date_format"
                  v-model="form.date_format"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
                >
                  <option v-for="(label, value) in options.date_formats" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.date_format" />
              </div>

              <div>
                <InputLabel for="currency" :value="__('currency')" />
                <select
                  id="currency"
                  v-model="form.currency"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
                >
                  <option v-for="(label, value) in options.currencies" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.currency" />
              </div>
            </div>
          </form>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('receipt_processing') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('receipt_processing_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between">
              <label for="auto_categorize" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('auto_categorize') }}</span>
                <span class="text-sm text-gray-500">{{ __('auto_categorize_description') }}</span>
              </label>
              <input
                id="auto_categorize"
                v-model="form.auto_categorize"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="extract_line_items" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('extract_line_items') }}</span>
                <span class="text-sm text-gray-500">{{ __('extract_line_items_description') }}</span>
              </label>
              <input
                id="extract_line_items"
                v-model="form.extract_line_items"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="ocr_handwritten" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('ocr_handwritten') }}</span>
                <span class="text-sm text-gray-500">{{ __('ocr_handwritten_description') }}</span>
              </label>
              <input
                id="ocr_handwritten"
                v-model="form.ocr_handwritten"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div>
              <InputLabel for="default_category_id" :value="__('default_category')" />
              <select
                id="default_category_id"
                v-model="form.default_category_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option :value="null">{{ __('no_default_category') }}</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.default_category_id" />
            </div>
          </div>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('notification_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('notification_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-6">
            <div class="space-y-4">
              <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('in_app_notifications') }}</h3>
              
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <label for="notify_processing_complete" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('notify_processing_complete') }}
                  </label>
                  <input
                    id="notify_processing_complete"
                    v-model="form.notify_processing_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_processing_failed" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('notify_processing_failed') }}
                  </label>
                  <input
                    id="notify_processing_failed"
                    v-model="form.notify_processing_failed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_bulk_complete" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('notify_bulk_complete') }}
                  </label>
                  <input
                    id="notify_bulk_complete"
                    v-model="form.notify_bulk_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_scanner_import" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('notify_scanner_import') }}
                  </label>
                  <input
                    id="notify_scanner_import"
                    v-model="form.notify_scanner_import"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>
              </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('email_notifications') }}</h3>
              
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <label for="email_notify_processing_complete" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('email_processing_complete') }}
                  </label>
                  <input
                    id="email_notify_processing_complete"
                    v-model="form.email_notify_processing_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_processing_failed" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('email_processing_failed') }}
                  </label>
                  <input
                    id="email_notify_processing_failed"
                    v-model="form.email_notify_processing_failed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_bulk_complete" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('email_bulk_complete') }}
                  </label>
                  <input
                    id="email_notify_bulk_complete"
                    v-model="form.email_notify_bulk_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_scanner_import" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('email_scanner_import') }}
                  </label>
                  <input
                    id="email_notify_scanner_import"
                    v-model="form.email_notify_scanner_import"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_weekly_summary" class="text-sm text-gray-700 dark:text-gray-300">
                    {{ __('email_weekly_summary') }}
                  </label>
                  <input
                    id="email_weekly_summary"
                    v-model="form.email_weekly_summary"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>

                <div v-if="form.email_weekly_summary">
                  <InputLabel for="weekly_summary_day" :value="__('weekly_summary_day')" />
                  <select
                    id="weekly_summary_day"
                    v-model="form.weekly_summary_day"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
                  >
                    <option v-for="(label, value) in options.weekly_summary_days" :key="value" :value="value">
                      {{ label }}
                    </option>
                  </select>
                  <InputError class="mt-2" :message="form.errors.weekly_summary_day" />
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('display_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('display_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-6">
            <div>
              <InputLabel for="receipt_list_view" :value="__('receipt_list_view')" />
              <select
                id="receipt_list_view"
                v-model="form.receipt_list_view"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option v-for="(label, value) in options.list_views" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.receipt_list_view" />
            </div>

            <div>
              <InputLabel for="receipts_per_page" :value="__('receipts_per_page')" />
              <select
                id="receipts_per_page"
                v-model="form.receipts_per_page"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option v-for="value in options.per_page_options" :key="value" :value="value">
                  {{ value }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.receipts_per_page" />
            </div>

            <div>
              <InputLabel for="default_sort" :value="__('default_sort')" />
              <select
                id="default_sort"
                v-model="form.default_sort"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
              >
                <option v-for="(label, value) in options.sort_options" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.default_sort" />
            </div>

            <div class="flex items-center justify-between">
              <label for="show_receipt_preview" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('show_receipt_preview') }}</span>
                <span class="text-sm text-gray-500">{{ __('show_receipt_preview_description') }}</span>
              </label>
              <input
                id="show_receipt_preview"
                v-model="form.show_receipt_preview"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>
          </div>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('scanner_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('scanner_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between">
              <label for="auto_process_scanner_uploads" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('auto_process_scanner_uploads') }}</span>
                <span class="text-sm text-gray-500">{{ __('auto_process_scanner_uploads_description') }}</span>
              </label>
              <input
                id="auto_process_scanner_uploads"
                v-model="form.auto_process_scanner_uploads"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="delete_after_processing" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('delete_after_processing') }}</span>
                <span class="text-sm text-gray-500">{{ __('delete_after_processing_description') }}</span>
              </label>
              <input
                id="delete_after_processing"
                v-model="form.delete_after_processing"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div>
              <InputLabel for="file_retention_days" :value="__('file_retention_days')" />
              <input
                id="file_retention_days"
                v-model="form.file_retention_days"
                type="number"
                min="1"
                max="365"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600"
              />
              <InputError class="mt-2" :message="form.errors.file_retention_days" />
            </div>
          </div>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <section>
          <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              {{ __('privacy_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ __('privacy_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between">
              <label for="analytics_enabled" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('analytics_enabled') }}</span>
                <span class="text-sm text-gray-500">{{ __('analytics_enabled_description') }}</span>
              </label>
              <input
                id="analytics_enabled"
                v-model="form.analytics_enabled"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="share_usage_data" class="flex flex-col">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('share_usage_data') }}</span>
                <span class="text-sm text-gray-500">{{ __('share_usage_data_description') }}</span>
              </label>
              <input
                id="share_usage_data"
                v-model="form.share_usage_data"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              />
            </div>
          </div>
        </section>
      </div>

      <div class="flex items-center gap-4 pb-6">
        <PrimaryButton :disabled="form.processing" @click="updatePreferences">
          {{ __('save') }}
        </PrimaryButton>

        <SecondaryButton :disabled="form.processing" @click="resetPreferences">
          {{ __('reset_to_defaults') }}
        </SecondaryButton>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('saved') }}
          </p>
        </Transition>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
  preferences: Object,
  categories: Array,
  options: Object,
  timezones: Array,
});

const __ = (key) => {
  const messages = window.page?.props?.language?.messages || {};
  return messages[key] || key;
};

const form = useForm({
  language: props.preferences.language || 'en',
  timezone: props.preferences.timezone || 'UTC',
  date_format: props.preferences.date_format || 'Y-m-d',
  currency: props.preferences.currency || 'NOK',
  auto_categorize: props.preferences.auto_categorize ?? true,
  extract_line_items: props.preferences.extract_line_items ?? true,
  ocr_handwritten: props.preferences.ocr_handwritten ?? false,
  default_category_id: props.preferences.default_category_id || null,
  notify_processing_complete: props.preferences.notify_processing_complete ?? true,
  notify_processing_failed: props.preferences.notify_processing_failed ?? true,
  notify_bulk_complete: props.preferences.notify_bulk_complete ?? true,
  notify_scanner_import: props.preferences.notify_scanner_import ?? true,
  notify_weekly_summary_ready: props.preferences.notify_weekly_summary_ready ?? true,
  email_notify_processing_complete: props.preferences.email_notify_processing_complete ?? false,
  email_notify_processing_failed: props.preferences.email_notify_processing_failed ?? true,
  email_notify_bulk_complete: props.preferences.email_notify_bulk_complete ?? false,
  email_notify_scanner_import: props.preferences.email_notify_scanner_import ?? false,
  email_notify_weekly_summary: props.preferences.email_notify_weekly_summary ?? false,
  email_weekly_summary: props.preferences.email_weekly_summary ?? false,
  weekly_summary_day: props.preferences.weekly_summary_day || 'monday',
  receipt_list_view: props.preferences.receipt_list_view || 'grid',
  receipts_per_page: props.preferences.receipts_per_page || 20,
  default_sort: props.preferences.default_sort || 'date_desc',
  show_receipt_preview: props.preferences.show_receipt_preview ?? true,
  auto_process_scanner_uploads: props.preferences.auto_process_scanner_uploads ?? false,
  delete_after_processing: props.preferences.delete_after_processing ?? false,
  file_retention_days: props.preferences.file_retention_days || 30,
  analytics_enabled: props.preferences.analytics_enabled ?? true,
  share_usage_data: props.preferences.share_usage_data ?? false,
});

const updatePreferences = () => {
  form.patch(route('preferences.update'), {
    preserveScroll: true,
    onSuccess: () => {
      if (form.language !== props.preferences.language) {
        router.reload();
      }
    },
  });
};

const resetPreferences = () => {
  if (confirm(__('reset_preferences_confirm'))) {
    router.post(route('preferences.reset'), {}, {
      preserveScroll: true,
      onSuccess: () => {
        router.reload();
      },
    });
  }
};
</script>