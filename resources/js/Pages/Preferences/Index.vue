<template>
  <Head :title="__('preferences')" />
  
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-black text-2xl text-zinc-900 dark:text-zinc-200 leading-tight">
        {{ __('preferences') }}
      </h2>
    </template>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
      <nav aria-label="Settings sections" class="flex flex-wrap gap-2 rounded-lg bg-white p-4 shadow dark:bg-zinc-800">
        <a v-for="section in settingsSections" :key="section.id" :href="`#preferences-${section.id}`" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-amber-50 dark:text-zinc-200 dark:hover:bg-zinc-700">{{ section.label }}</a>
      </nav>
      <section aria-labelledby="application-preferences-heading" class="flex flex-col gap-6">
        <header class="px-4 sm:px-0 text-zinc-900 dark:text-zinc-100">
          <h2 id="application-preferences-heading" class="text-lg font-semibold">Application preferences</h2>
          <p class="text-sm text-zinc-600 dark:text-zinc-400">Save application preferences saves language, processing, notifications, display and scanner settings together. Organization choices are saved separately.</p>
          <p aria-live="polite" class="mt-2 text-sm">{{ form.isDirty ? 'Unsaved application preferences' : form.recentlySuccessful ? 'Application preferences saved' : 'No unsaved application preferences' }}</p>
        </header>
      <div class="p-4 sm:p-8 bg-white dark:bg-zinc-800 shadow sm:rounded-lg">
        <section id="preferences-general" class="scroll-mt-24">
          <header>
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
              {{ __('general_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
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
                  class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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
                  class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
                >
                  <option v-if="!timezones.some(timezone => timezone.value === form.timezone)" :value="form.timezone">
                    {{ form.timezone }} (saved value)
                  </option>
                  <option v-for="timezone in timezones" :key="timezone.value" :value="timezone.value">
                    {{ timezone.label }}
                  </option>
                </select>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Effective timezone: {{ page.props.auth.user.preferences.timezone }}</p>
                <InputError class="mt-2" :message="form.errors.timezone" />
              </div>

              <div>
                <InputLabel for="date_format" :value="__('date_format')" />
                <select
                  id="date_format"
                  v-model="form.date_format"
                  class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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
                  class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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

      <div class="p-4 sm:p-8 bg-white dark:bg-zinc-800 shadow sm:rounded-lg">
        <section id="preferences-processing" class="scroll-mt-24">
          <header>
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
              {{ __('receipt_processing') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ __('receipt_processing_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between gap-4">
              <label for="auto_organize_documents" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Organize documents automatically</span>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">New files start in Inbox. Clear property or employer evidence places them in Building or Work folders; uncertain documents need review. Manual placements are kept.</span>
              </label>
              <input id="auto_organize_documents" v-model="form.auto_organize_documents" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500" />
            </div>
            <div class="flex items-center justify-between">
              <label for="auto_categorize" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('auto_categorize') }}</span>
                <span class="text-sm text-zinc-500">{{ __('auto_categorize_description') }}</span>
              </label>
              <input
                id="auto_categorize"
                v-model="form.auto_categorize"
                type="checkbox"
                class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="extract_line_items" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('extract_line_items') }}</span>
                <span class="text-sm text-zinc-500">{{ __('extract_line_items_description') }}</span>
              </label>
              <input
                id="extract_line_items"
                v-model="form.extract_line_items"
                type="checkbox"
                class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
              />
            </div>

            <div>
              <InputLabel for="default_category_id" :value="__('default_category')" />
              <select
                id="default_category_id"
                v-model="form.default_category_id"
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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

      <div class="p-4 sm:p-8 bg-white dark:bg-zinc-800 shadow sm:rounded-lg">
        <section id="preferences-notifications" class="scroll-mt-24">
          <header>
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
              {{ __('notification_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ __('notification_preferences_description') }}
              {{ __('expiry_reminder_window') }}
            </p>
          </header>

          <div class="mt-6 space-y-6">
            <div class="space-y-4">
              <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('in_app_notifications') }}</h3>
              <div class="flex items-center justify-between">
                <label for="notify_weekly_summary_ready" class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('notify_weekly_summary_ready') }}</label>
                <input id="notify_weekly_summary_ready" v-model="form.notify_weekly_summary_ready" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500" />
              </div>
              
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <label for="notify_processing_complete" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_processing_complete') }}
                  </label>
                  <input
                    id="notify_processing_complete"
                    v-model="form.notify_processing_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_processing_failed" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_processing_failed') }}
                  </label>
                  <input
                    id="notify_processing_failed"
                    v-model="form.notify_processing_failed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_bulk_complete" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_bulk_complete') }}
                  </label>
                  <input
                    id="notify_bulk_complete"
                    v-model="form.notify_bulk_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_scanner_import" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_scanner_import') }}
                  </label>
                  <input
                    id="notify_scanner_import"
                    v-model="form.notify_scanner_import"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_voucher_expiring" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_voucher_expiring') }}
                  </label>
                  <input
                    id="notify_voucher_expiring"
                    v-model="form.notify_voucher_expiring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="notify_warranty_expiring" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('notify_warranty_expiring') }}
                  </label>
                  <input
                    id="notify_warranty_expiring"
                    v-model="form.notify_warranty_expiring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>
              </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-amber-200 dark:border-zinc-700">
              <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('email_notifications') }}</h3>
              
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <label for="email_notify_processing_complete" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_processing_complete') }}
                  </label>
                  <input
                    id="email_notify_processing_complete"
                    v-model="form.email_notify_processing_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_processing_failed" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_processing_failed') }}
                  </label>
                  <input
                    id="email_notify_processing_failed"
                    v-model="form.email_notify_processing_failed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_bulk_complete" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_bulk_complete') }}
                  </label>
                  <input
                    id="email_notify_bulk_complete"
                    v-model="form.email_notify_bulk_complete"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_scanner_import" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_scanner_import') }}
                  </label>
                  <input
                    id="email_notify_scanner_import"
                    v-model="form.email_notify_scanner_import"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_voucher_expiring" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_notify_voucher_expiring') }}
                  </label>
                  <input
                    id="email_notify_voucher_expiring"
                    v-model="form.email_notify_voucher_expiring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_warranty_expiring" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_notify_warranty_expiring') }}
                  </label>
                  <input
                    id="email_notify_warranty_expiring"
                    v-model="form.email_notify_warranty_expiring"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div class="flex items-center justify-between">
                  <label for="email_notify_weekly_summary" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('email_notify_weekly_summary') }}
                  </label>
                  <input
                    id="email_notify_weekly_summary"
                    v-model="form.email_notify_weekly_summary"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
                  />
                </div>

                <div v-if="form.email_notify_weekly_summary || form.notify_weekly_summary_ready">
                  <InputLabel for="weekly_summary_day" :value="__('weekly_summary_day')" />
                  <select
                    id="weekly_summary_day"
                    v-model="form.weekly_summary_day"
                    class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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

      <div class="p-4 sm:p-8 bg-white dark:bg-zinc-800 shadow sm:rounded-lg">
        <section id="preferences-display" class="scroll-mt-24">
          <header>
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
              {{ __('display_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ __('display_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-6">
            <div>
              <InputLabel for="receipt_list_view" :value="__('receipt_list_view')" />
              <select
                id="receipt_list_view"
                v-model="form.receipt_list_view"
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
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
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
              >
                <option v-for="(label, value) in options.sort_options" :key="value" :value="value">
                  {{ label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.default_sort" />
            </div>
          </div>
        </section>
      </div>

      <div class="p-4 sm:p-8 bg-white dark:bg-zinc-800 shadow sm:rounded-lg">
        <section id="preferences-scanner" class="scroll-mt-24">
          <header>
            <h2 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">
              {{ __('scanner_preferences') }}
            </h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
              {{ __('scanner_preferences_description') }}
            </p>
          </header>

          <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between">
              <label for="auto_process_scanner_uploads" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('auto_process_scanner_uploads') }}</span>
                <span class="text-sm text-zinc-500">{{ __('auto_process_scanner_uploads_description') }}</span>
              </label>
              <input
                id="auto_process_scanner_uploads"
                v-model="form.auto_process_scanner_uploads"
                type="checkbox"
                class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
              />
            </div>

            <div class="flex items-center justify-between">
              <label for="delete_after_processing" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('delete_after_processing') }}</span>
                <span class="text-sm text-zinc-500">{{ __('delete_after_processing_description') }}</span>
              </label>
              <input
                id="delete_after_processing"
                v-model="form.delete_after_processing"
                type="checkbox"
                class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
              />
            </div>

            <div>
              <InputLabel for="retention_mode" :value="__('retention_mode')" />
              <select
                id="retention_mode"
                v-model="form.retention_mode"
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
              >
                <option value="source_only">{{ __('retention_source_only') }}</option>
                <option value="full_delete">{{ __('retention_full_delete') }}</option>
              </select>
              <InputError class="mt-2" :message="form.errors.retention_mode" />
            </div>

            <div>
              <InputLabel for="file_retention_days" :value="__('file_retention_days')" />
              <input
                id="file_retention_days"
                v-model="form.file_retention_days"
                type="number"
                min="1"
                max="365"
                class="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm dark:bg-zinc-700 dark:border-zinc-600"
              />
              <InputError class="mt-2" :message="form.errors.file_retention_days" />
            </div>

            <div class="flex items-center justify-between">
              <label for="pulsedav_realtime_sync" class="flex flex-col">
                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('pulsedav_realtime_sync') }}</span>
                <span class="text-sm text-zinc-500">{{ __('pulsedav_realtime_sync_description') }}</span>
              </label>
              <input
                id="pulsedav_realtime_sync"
                v-model="form.pulsedav_realtime_sync"
                type="checkbox"
                class="h-4 w-4 rounded border-zinc-300 text-amber-600 focus:ring-amber-500"
              />
            </div>
          </div>
        </section>
      </div>

      <div class="flex items-center gap-4 pb-6">
        <PrimaryButton type="button" dusk="save-application-preferences" :disabled="form.processing" @click="updatePreferences">
          Save application preferences
        </PrimaryButton>

        <SecondaryButton :disabled="form.processing" @click="resetPreferences">
          Reset application preferences
        </SecondaryButton>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('saved') }}
          </p>
        </Transition>
      </div>
      </section>
      <section id="preferences-organization" class="scroll-mt-24 flex flex-col gap-4 rounded-lg bg-white p-6 shadow dark:bg-zinc-800 dark:text-zinc-100">
        <h2 class="text-lg font-medium">Organization choices</h2>
        <p>Saved labels and aliases guide folder placement. Pinned folders and manual placements stay fixed. Declined suggestions remain suppressed until their evidence changes.</p>
        <p aria-live="polite" class="text-sm">{{ organization.isDirty ? 'Unsaved organization choices' : organization.recentlySuccessful ? 'Organization choices saved' : 'No unsaved organization choices' }}</p>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Save organization choices saves only the folder names, template and aliases below.</p>
        <form class="flex flex-col gap-4" @submit.prevent="saveOrganization">
          <label class="flex flex-col gap-2">Building folder name<input v-model="organization.naming_rules.building_root" maxlength="180" class="rounded dark:bg-zinc-700" /></label>
          <label class="flex flex-col gap-2">Work folder name<input v-model="organization.naming_rules.work_root" maxlength="180" class="rounded dark:bg-zinc-700" /></label>
          <label class="flex flex-col gap-2">Work folder template<select v-model="organization.naming_rules.work_structure" class="rounded dark:bg-zinc-700"><option value="role">Work / employer / document role</option><option value="year">Work / employer / year</option></select></label>
          <div v-for="role in ['contracts', 'invoices', 'receipts', 'payslips', 'letters', 'other']" :key="role">
            <label class="flex flex-col gap-2">{{ role }} folder name<input :value="organization.naming_rules.role_labels[role] ?? role[0].toUpperCase() + role.slice(1)" @input="organization.naming_rules.role_labels[role] = $event.target.value" maxlength="180" class="rounded dark:bg-zinc-700" /></label>
          </div>
          <div v-for="(alias, index) in organization.aliases" :key="alias.id ?? index" class="flex flex-wrap items-center gap-3">
            <label>Kind<select v-model="alias.kind" class="rounded dark:bg-zinc-700"><option value="property">Property address</option><option value="employer">Company</option></select></label>
            <label v-if="!alias.id">Recognize<input v-model="alias.alias" maxlength="180" class="rounded dark:bg-zinc-700" /></label>
            <label>Use this label<input v-model="alias.canonical_name" maxlength="180" class="rounded dark:bg-zinc-700" /></label>
            <SecondaryButton type="button" @click="removeAlias(index)">Remove alias</SecondaryButton>
          </div>
          <p v-for="(message, field) in organization.errors" :key="field" role="alert" class="text-red-600 dark:text-red-400">{{ message }}</p>
          <div class="flex flex-wrap gap-3">
            <SecondaryButton type="button" :disabled="organization.aliases.length >= 100" @click="organization.aliases.push({ kind: 'property', alias: '', canonical_name: '' })">Add alias</SecondaryButton>
            <PrimaryButton type="submit" dusk="save-organization-choices" :disabled="organization.processing">Save organization choices</PrimaryButton>
            <SecondaryButton type="button" @click="resetOrganization">Reset labels, aliases and declined suggestions</SecondaryButton>
          </div>
        </form>
      </section>
      <section id="preferences-archive" class="scroll-mt-24 flex flex-col gap-4 rounded-lg bg-white p-6 shadow dark:bg-zinc-800 dark:text-zinc-100">
        <h2 class="text-lg font-medium">Organize an existing archive</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Preview reads your archive without saving settings or moving files. Organize this archive applies the saved organization choices using the limits below.</p>
        <p>Preview first. Saved summaries require no paid extraction. Manual placements and pinned folders are preserved; pending recommendations must be resolved first.</p>
        <label class="flex items-center gap-2"><input v-model="backfill.extract_missing" type="checkbox" />Extract missing grouping evidence from stored text using AI</label>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">With AI off, organizing saved information uses no paid AI requests. With AI on, each document needing evidence can use one paid request. Provider prices vary; this limit caps work, not a monetary charge. The run pauses when a request, text or daily limit is reached.</p>
        <details>
          <summary class="cursor-pointer text-sm font-medium">Advanced AI limits</summary>
          <div class="mt-3 flex flex-col gap-3">
            <label class="flex flex-col gap-2">Maximum provider calls<input v-model.number="backfill.max_calls" type="number" min="1" max="100" class="rounded dark:bg-zinc-700" /></label>
            <label class="flex flex-col gap-2">Maximum reserved tokens<input v-model.number="backfill.max_tokens" type="number" min="10000" max="1000000" class="rounded dark:bg-zinc-700" /></label>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Tokens measure the text sent to and returned by AI. Reservations include the maximum response size, so this is a processing allowance rather than actual billed usage.</p>
          </div>
        </details>
        <p v-if="previewError" role="alert" class="text-red-600 dark:text-red-400">{{ previewError }}</p>
        <p v-for="(message, field) in backfill.errors" :key="field" role="alert" class="text-red-600 dark:text-red-400">{{ message }}</p>
        <div v-if="backfillPreview" class="flex flex-col gap-2">
          <p>{{ backfillPreview.eligible }} eligible documents · {{ backfillPreview.missing_metadata }} need grouping metadata · {{ backfill.extract_missing ? `AI can attempt up to ${Math.min(backfillPreview.maximum_calls_with_extraction, backfill.max_calls)} documents with this request limit; text and daily limits may allow fewer` : '0 paid AI requests' }}</p>
          <ul class="flex flex-col gap-2"><li v-for="file in backfillPreview.sample" :key="file.id">{{ file.name }} · {{ file.current_folder ?? 'Unfiled' }} → {{ file.group }}{{ file.role ? ` / ${file.role}` : '' }}</li></ul>
        </div>
        <div v-if="organizationBackfill" class="flex flex-col gap-2">
          <p>{{ organizationBackfill.status }} · {{ organizationBackfill.processed }} processed · {{ organizationBackfill.skipped }} skipped · {{ organizationBackfill.calls }} provider calls · {{ organizationBackfill.tokens }} reserved tokens</p>
          <p v-if="organizationBackfill.error" role="alert" class="text-amber-700 dark:text-amber-300">{{ organizationBackfill.error }}</p>
        </div>
        <p v-if="organizePrerequisite" id="organize-prerequisite" role="status" class="text-sm text-amber-800 dark:text-amber-300">{{ organizePrerequisite }}</p>
        <div class="flex flex-wrap gap-3">
          <SecondaryButton :disabled="previewBusy" @click="previewBackfill">Preview archive and budget</SecondaryButton>
          <PrimaryButton :disabled="Boolean(organizePrerequisite) || backfill.processing" aria-describedby="organize-prerequisite" class="disabled:opacity-50 disabled:cursor-not-allowed" @click="startBackfill">Organize this archive</PrimaryButton>
          <SecondaryButton v-if="['paused', 'failed'].includes(organizationBackfill?.status)" :disabled="!backfillPreview?.can_start || backfill.processing" @click="resumeBackfill">Resume with this budget</SecondaryButton>
          <SecondaryButton v-if="organizationBackfill" @click="router.reload({ only: ['organizationBackfill'] })">Refresh progress</SecondaryButton>
        </div>
      </section>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/Forms/InputError.vue';
import InputLabel from '@/Components/Forms/InputLabel.vue';
import PrimaryButton from '@/Components/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/Components/Buttons/SecondaryButton.vue';

const props = defineProps({
  preferences: Object,
  categories: Array,
  options: Object,
  timezones: Array,
  organizationAliases: Array,
  organizationBackfill: Object,
});

const settingsSections = [
  { id: 'general', label: 'Language & timezone' },
  { id: 'display', label: 'Display' },
  { id: 'notifications', label: 'Notifications' },
  { id: 'processing', label: 'Processing' },
  { id: 'scanner', label: 'Scanner' },
  { id: 'organization', label: 'Organization choices' },
  { id: 'archive', label: 'Existing archive' },
];

const organization = useForm({
  naming_rules: {
    building_root: props.preferences.organization_naming_rules?.building_root ?? 'Building',
    work_root: props.preferences.organization_naming_rules?.work_root ?? 'Work',
    work_structure: props.preferences.organization_naming_rules?.work_structure ?? 'role',
    role_labels: props.preferences.organization_naming_rules?.role_labels ?? {},
  },
  aliases: props.organizationAliases.map(alias => ({ ...alias, alias: null })),
  removed_alias_ids: [],
});
const saveOrganization = () => organization.patch(route('preferences.organization'), {
  preserveScroll: true,
  onSuccess: () => organization.defaults(),
});
const removeAlias = index => {
  const alias = organization.aliases[index];
  if (alias.id) organization.removed_alias_ids.push(alias.id);
  organization.aliases.splice(index, 1);
};
const resetOrganization = () => router.patch(route('preferences.organization'), { reset: true }, {
  preserveScroll: true,
  onSuccess: () => {
    organization.aliases = [];
    organization.removed_alias_ids = [];
    organization.naming_rules = { building_root: 'Building', work_root: 'Work', work_structure: 'role', role_labels: {} };
    organization.defaults();
  },
});

const backfill = useForm({ extract_missing: false, max_calls: 10, max_tokens: 160000 });
const backfillPreview = ref(null);
const previewError = ref('');
const previewBusy = ref(false);
const organizePrerequisite = computed(() => {
  if (!backfillPreview.value) return 'Preview the archive before organizing it.';
  if (!props.preferences.auto_organize_documents) return 'Enable automatic organization and save application preferences first.';
  if (!backfillPreview.value.can_start) return 'Finish pending folder recommendations before organizing the archive.';
  if (['queued', 'running'].includes(props.organizationBackfill?.status)) return 'An archive organization run is already in progress.';
  if (['paused', 'failed'].includes(props.organizationBackfill?.status)) return 'Use Resume with this budget to continue the existing run.';
  return '';
});
const previewBackfill = async () => {
  previewBusy.value = true;
  previewError.value = '';
  try {
    const response = await axios.get(route('preferences.backfill.preview'));
    backfillPreview.value = response.data;
  } catch {
    backfillPreview.value = null;
    previewError.value = 'The archive preview could not be loaded. Try again.';
  } finally {
    previewBusy.value = false;
  }
};
const startBackfill = () => backfill.post(route('preferences.backfill.start'), { preserveScroll: true });
const resumeBackfill = () => backfill.post(route('preferences.backfill.resume', props.organizationBackfill.id), { preserveScroll: true });

const page = usePage();
const __ = (key) => {
  const messages = page.props?.language?.messages || {};
  return messages[key] || key;
};

const form = useForm({
  language: props.preferences.language || 'en',
  timezone: props.preferences.timezone || 'UTC',
  date_format: props.preferences.date_format || 'Y-m-d',
  currency: props.preferences.currency || 'NOK',
  auto_categorize: props.preferences.auto_categorize ?? true,
  auto_organize_documents: props.preferences.auto_organize_documents ?? true,
  extract_line_items: props.preferences.extract_line_items ?? true,
  default_category_id: props.preferences.default_category_id || null,
  notify_processing_complete: props.preferences.notify_processing_complete ?? true,
  notify_processing_failed: props.preferences.notify_processing_failed ?? true,
  notify_bulk_complete: props.preferences.notify_bulk_complete ?? true,
  notify_scanner_import: props.preferences.notify_scanner_import ?? true,
  notify_weekly_summary_ready: props.preferences.notify_weekly_summary_ready ?? true,
  notify_voucher_expiring: props.preferences.notify_voucher_expiring ?? false,
  notify_warranty_expiring: props.preferences.notify_warranty_expiring ?? false,
  email_notify_voucher_expiring: props.preferences.email_notify_voucher_expiring ?? false,
  email_notify_warranty_expiring: props.preferences.email_notify_warranty_expiring ?? false,

  email_notify_processing_complete: props.preferences.email_notify_processing_complete ?? false,
  email_notify_processing_failed: props.preferences.email_notify_processing_failed ?? true,
  email_notify_bulk_complete: props.preferences.email_notify_bulk_complete ?? false,
  email_notify_scanner_import: props.preferences.email_notify_scanner_import ?? false,
  email_notify_weekly_summary: props.preferences.email_notify_weekly_summary ?? false,
  weekly_summary_day: props.preferences.weekly_summary_day || 'monday',
  receipt_list_view: props.preferences.receipt_list_view || 'grid',
  receipts_per_page: props.preferences.receipts_per_page || 20,
  default_sort: props.preferences.default_sort || 'date_desc',
  auto_process_scanner_uploads: props.preferences.auto_process_scanner_uploads ?? false,
  delete_after_processing: props.preferences.delete_after_processing ?? false,
  file_retention_days: props.preferences.file_retention_days || 30,
  retention_mode: props.preferences.retention_mode || 'source_only',
  pulsedav_realtime_sync: props.preferences.pulsedav_realtime_sync ?? false,
});

const updatePreferences = () => {
  form.patch(route('preferences.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.defaults();
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
