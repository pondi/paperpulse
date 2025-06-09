# User Preferences Usage Report

## Summary
This report documents where user preferences are actually being used in the PaperPulse application versus what preferences are defined in the database schema.

## Defined Preferences (from UserPreference model)

### Localization
- **language** (default: 'en') - ✅ USED in SetLocale middleware
- **timezone** (default: 'UTC') - ❌ NOT USED (only stored on User model)
- **date_format** (default: 'Y-m-d') - ❌ NOT USED
- **currency** (default: 'NOK') - ❌ NOT USED

### Processing Options
- **auto_categorize** (default: true) - ❌ NOT USED
- **extract_line_items** (default: true) - ❌ NOT USED
- **ocr_handwritten** (default: false) - ❌ NOT USED

### Notification Settings
- **notify_processing_complete** (default: true) - ❌ NOT USED (uses non-existent method)
- **notify_processing_failed** (default: true) - ❌ NOT USED (uses non-existent method)
- **notify_scanner_imports** (default: true) - ❌ NOT USED (uses non-existent method)
- **email_notify_processing_complete** (default: false) - ❌ NOT USED (uses non-existent method)
- **email_notify_processing_failed** (default: true) - ❌ NOT USED (uses non-existent method)
- **email_scanner_imports** (default: false) - ❌ NOT USED

### Display Preferences
- **receipts_per_page** (default: 20) - ❌ NOT USED
- **default_sort** (default: 'date_desc') - ❌ NOT USED
- **default_view** (default: 'grid') - ❌ NOT USED

### Scanner Settings
- **pulsedav_enabled** (default: false) - ❌ NOT USED directly (but pulsedav_realtime_sync is)
- **pulsedav_realtime_sync** (default: false) - ✅ USED in SyncPulseDavFilesRealtime job

### Privacy Settings
- **auto_delete_after_days** (default: null) - ❌ NOT USED
- **share_analytics** (default: false) - ❌ NOT USED

## Issues Found

### 1. Missing `preference()` method
The notifications and jobs are calling `$user->preference()` but the User model only has:
- `preferences()` - relationship method
- `getPreference()` - accessor method

### 2. Timezone handling
- User model has a `timezone` field but it's not being used
- Date formatting doesn't respect user timezone preferences
- All dates are displayed in server timezone

### 3. Currency preference not enforced
- Currency is hardcoded as 'NOK' in exports
- User's currency preference is ignored

### 4. Display preferences ignored
- Pagination is not using `receipts_per_page`
- Sort order is hardcoded as 'desc' by receipt_date
- View type (grid/list) is not persisted

### 5. Processing preferences not implemented
- Auto-categorization setting is ignored
- Line item extraction setting is ignored
- Handwritten OCR setting is ignored

### 6. Privacy settings not implemented
- Auto-deletion is not scheduled
- Analytics sharing preference is not checked

## Actual Usage Locations

### SetLocale Middleware
```php
// Uses language preference correctly
$locale = auth()->user()->preferences->language;
App::setLocale($locale);
```

### SyncPulseDavFilesRealtime Job
```php
// Correctly queries for users with realtime sync enabled
User::whereHas('preference', function ($query) {
    $query->where('pulsedav_realtime_sync', true);
})->get();
```

### Notification Attempts (but failing)
- ReceiptProcessed notification tries to use `$notifiable->preference('email_notify_processing_complete')`
- ScannerFilesImported notification tries to use `$notifiable->preference('notify_scanner_imports')`
- BulkOperationCompleted notification tries to use preference method

## Recommendations

1. **Add missing `preference()` method to User model** to match usage in notifications
2. **Implement timezone support** throughout the application
3. **Use display preferences** in controllers for pagination and sorting
4. **Implement processing preferences** in receipt/document analysis services
5. **Add scheduled job** for auto-deletion based on privacy preferences
6. **Use currency preference** in all monetary displays and exports
7. **Fix date formatting** to use user's date_format preference