<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\App;

class PreferencesController extends Controller
{
    /**
     * Display the user's preferences.
     */
    public function index()
    {
        $user = auth()->user();
        $preferences = $user->preferences ?? UserPreference::defaultPreferences();
        $categories = $user->categories()->active()->ordered()->get();
        
        return Inertia::render('Preferences/Index', [
            'preferences' => $preferences,
            'categories' => $categories,
            'options' => UserPreference::getOptions(),
            'timezones' => $this->getTimezones(),
        ]);
    }

    /**
     * Update the user's preferences.
     */
    public function update(Request $request)
    {
        $request->validate([
            // General preferences
            'language' => 'required|string|in:en,nb',
            'timezone' => 'required|string|timezone',
            'date_format' => 'required|string',
            'currency' => 'required|string|in:NOK,USD,EUR,GBP,SEK,DKK',
            
            // Receipt processing preferences
            'auto_categorize' => 'boolean',
            'extract_line_items' => 'boolean',
            'ocr_handwritten' => 'boolean',
            'default_category_id' => 'nullable|exists:categories,id',
            
            // Notification preferences
            'email_processing_complete' => 'boolean',
            'email_processing_failed' => 'boolean',
            'email_weekly_summary' => 'boolean',
            'weekly_summary_day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            
            // Display preferences
            'receipt_list_view' => 'required|string|in:grid,list',
            'receipts_per_page' => 'required|integer|in:10,20,50,100',
            'default_sort' => 'required|string',
            'show_receipt_preview' => 'boolean',
            
            // Scanner/Import preferences
            'auto_process_scanner_uploads' => 'boolean',
            'delete_after_processing' => 'boolean',
            'file_retention_days' => 'required|integer|min:1|max:365',
            
            // Privacy preferences
            'analytics_enabled' => 'boolean',
            'share_usage_data' => 'boolean',
        ]);

        $user = auth()->user();
        
        // Verify the user owns the category if specified
        if ($request->default_category_id) {
            $category = $user->categories()->find($request->default_category_id);
            if (!$category) {
                return redirect()->back()->withErrors(['default_category_id' => 'Invalid category selected.']);
            }
        }

        // Update or create preferences
        $preferences = $user->preferences()->updateOrCreate(
            ['user_id' => $user->id],
            $request->all()
        );

        // Update application locale if language changed
        if ($request->language !== App::getLocale()) {
            session(['locale' => $request->language]);
            App::setLocale($request->language);
        }

        return redirect()->back()->with('success', 'Preferences updated successfully.');
    }

    /**
     * Reset preferences to default values.
     */
    public function reset()
    {
        $user = auth()->user();
        
        if ($user->preferences) {
            $user->preferences->delete();
        }

        // Reset locale to default
        session()->forget('locale');
        App::setLocale(config('app.locale'));

        return redirect()->back()->with('success', 'Preferences reset to default values.');
    }

    /**
     * Get list of timezones grouped by region.
     */
    private function getTimezones()
    {
        $timezones = [];
        $regions = [
            'Africa' => \DateTimeZone::AFRICA,
            'America' => \DateTimeZone::AMERICA,
            'Antarctica' => \DateTimeZone::ANTARCTICA,
            'Arctic' => \DateTimeZone::ARCTIC,
            'Asia' => \DateTimeZone::ASIA,
            'Atlantic' => \DateTimeZone::ATLANTIC,
            'Australia' => \DateTimeZone::AUSTRALIA,
            'Europe' => \DateTimeZone::EUROPE,
            'Indian' => \DateTimeZone::INDIAN,
            'Pacific' => \DateTimeZone::PACIFIC,
        ];

        foreach ($regions as $name => $mask) {
            $zones = \DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $timezone) {
                $timezones[$name][] = $timezone;
            }
        }

        return $timezones;
    }
}