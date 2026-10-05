<?php

namespace App\Http\Middleware;

use App\Models\File;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'email_verified_at' => $request->user()->email_verified_at,
                    'is_admin' => $request->user()->isAdmin(),
                    'preferences' => $request->user()->formattingPreferences(),
                ] : null,
            ],
            'language' => [
                'messages' => $this->getTranslations(),
            ],
            'navigation' => fn (): array => $request->user() ? [
                'attention_count' => File::query()->where('user_id', $request->user()->id)->whereIn('status', ['needs_review', 'failed'])->count(),
                'saved_views' => SavedSearch::query()->where('user_id', $request->user()->id)->where('is_pinned', true)
                    ->orderBy('name')->limit(8)->get(['id', 'name', 'scope']),
            ] : ['attention_count' => 0, 'saved_views' => []],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'publicLink' => $request->session()->get('publicLink'),
                'upload_results' => $request->session()->get('upload_results'),
            ],
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
        ]);
    }

    /**
     * Get all translations from the language files
     */
    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $translations = Lang::get('messages', [], config('app.fallback_locale'));

        // Load messages translations
        if (Lang::has('messages', $locale)) {
            $translations = array_replace($translations, Lang::get('messages', [], $locale));
        }

        return $translations;
    }
}
