<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\UserPreference;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;

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
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $user = auth()->user();

        // Verify the user owns the category if specified
        if ($request->default_category_id) {
            $category = $user->categories()->find($request->default_category_id);
            if (! $category) {
                return redirect()->back()->withErrors(['default_category_id' => 'Invalid category selected.']);
            }
        }

        // Update or create preferences
        $preferences = $user->preferences()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated()
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
            'Africa' => DateTimeZone::AFRICA,
            'America' => DateTimeZone::AMERICA,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Arctic' => DateTimeZone::ARCTIC,
            'Asia' => DateTimeZone::ASIA,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Australia' => DateTimeZone::AUSTRALIA,
            'Europe' => DateTimeZone::EUROPE,
            'Indian' => DateTimeZone::INDIAN,
            'Pacific' => DateTimeZone::PACIFIC,
        ];

        foreach ($regions as $name => $mask) {
            $zones = DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $timezone) {
                $timezones[] = [
                    'value' => $timezone,
                    'label' => str_replace(['_', '/'], [' ', ' / '], $timezone),
                ];
            }
        }

        return $timezones;
    }
}
