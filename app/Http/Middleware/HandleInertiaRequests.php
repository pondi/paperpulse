<?php

namespace App\Http\Middleware;

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
                    'timezone' => $request->user()->timezone ?? 'UTC',
                    'preferences' => $request->user()->preferences ? [
                        'language' => $request->user()->preferences->language,
                        'timezone' => $request->user()->preferences->timezone,
                        'date_format' => $request->user()->preferences->date_format,
                        'currency' => $request->user()->preferences->currency,
                    ] : null,
                ] : null,
            ],
            'language' => [
                'messages' => $this->getTranslations(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ]);
    }

    /**
     * Get all translations from the language files
     */
    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $translations = [];

        // Load messages translations
        if (Lang::has('messages', $locale)) {
            $translations = Lang::get('messages', [], $locale);
        }

        return $translations;
    }
}
