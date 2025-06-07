<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'language',
        'timezone',
        'date_format',
        'currency',
        'auto_categorize',
        'extract_line_items',
        'ocr_handwritten',
        'default_category_id',
        'notify_processing_complete',
        'notify_processing_failed',
        'notify_bulk_complete',
        'notify_scanner_import',
        'notify_weekly_summary_ready',
        'email_notify_processing_complete',
        'email_notify_processing_failed',
        'email_notify_bulk_complete',
        'email_notify_scanner_import',
        'email_notify_weekly_summary',
        'email_weekly_summary',
        'weekly_summary_day',
        'receipt_list_view',
        'receipts_per_page',
        'default_sort',
        'show_receipt_preview',
        'auto_process_scanner_uploads',
        'delete_after_processing',
        'file_retention_days',
        'analytics_enabled',
        'share_usage_data',
        'pulsedav_realtime_sync',
    ];

    protected $casts = [
        'auto_categorize' => 'boolean',
        'extract_line_items' => 'boolean',
        'ocr_handwritten' => 'boolean',
        'notify_processing_complete' => 'boolean',
        'notify_processing_failed' => 'boolean',
        'notify_bulk_complete' => 'boolean',
        'notify_scanner_import' => 'boolean',
        'notify_weekly_summary_ready' => 'boolean',
        'email_notify_processing_complete' => 'boolean',
        'email_notify_processing_failed' => 'boolean',
        'email_notify_bulk_complete' => 'boolean',
        'email_notify_scanner_import' => 'boolean',
        'email_notify_weekly_summary' => 'boolean',
        'email_weekly_summary' => 'boolean',
        'show_receipt_preview' => 'boolean',
        'auto_process_scanner_uploads' => 'boolean',
        'delete_after_processing' => 'boolean',
        'analytics_enabled' => 'boolean',
        'share_usage_data' => 'boolean',
        'pulsedav_realtime_sync' => 'boolean',
        'receipts_per_page' => 'integer',
        'file_retention_days' => 'integer',
    ];

    /**
     * Get the user that owns the preferences.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the default category.
     */
    public function defaultCategory()
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    /**
     * Get default preferences for a new user.
     */
    public static function defaultPreferences()
    {
        return [
            'language' => 'en',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'currency' => 'NOK',
            'auto_categorize' => true,
            'extract_line_items' => true,
            'ocr_handwritten' => false,
            'notify_processing_complete' => true,
            'notify_processing_failed' => true,
            'notify_bulk_complete' => true,
            'notify_scanner_import' => true,
            'notify_weekly_summary_ready' => true,
            'email_notify_processing_complete' => false,
            'email_notify_processing_failed' => true,
            'email_notify_bulk_complete' => false,
            'email_notify_scanner_import' => false,
            'email_notify_weekly_summary' => false,
            'email_weekly_summary' => false,
            'weekly_summary_day' => 'monday',
            'receipt_list_view' => 'grid',
            'receipts_per_page' => 20,
            'default_sort' => 'date_desc',
            'show_receipt_preview' => true,
            'auto_process_scanner_uploads' => false,
            'delete_after_processing' => false,
            'file_retention_days' => 30,
            'analytics_enabled' => true,
            'share_usage_data' => false,
            'pulsedav_realtime_sync' => false,
        ];
    }

    /**
     * Available options for various settings
     */
    public static function getOptions()
    {
        return [
            'languages' => [
                'en' => 'English',
                'nb' => 'Norsk',
            ],
            'date_formats' => [
                'Y-m-d' => '2024-01-15',
                'd/m/Y' => '15/01/2024',
                'm/d/Y' => '01/15/2024',
                'd.m.Y' => '15.01.2024',
                'F j, Y' => 'January 15, 2024',
                'j F Y' => '15 January 2024',
            ],
            'currencies' => [
                'NOK' => 'Norwegian Krone (NOK)',
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
                'SEK' => 'Swedish Krona (SEK)',
                'DKK' => 'Danish Krone (DKK)',
            ],
            'weekly_summary_days' => [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday',
            ],
            'list_views' => [
                'grid' => 'Grid View',
                'list' => 'List View',
            ],
            'sort_options' => [
                'date_desc' => 'Date (Newest First)',
                'date_asc' => 'Date (Oldest First)',
                'amount_desc' => 'Amount (Highest First)',
                'amount_asc' => 'Amount (Lowest First)',
                'merchant_asc' => 'Merchant (A-Z)',
                'merchant_desc' => 'Merchant (Z-A)',
            ],
            'per_page_options' => [10, 20, 50, 100],
        ];
    }
}
