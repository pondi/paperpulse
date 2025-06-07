<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Lang;

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
    public function version(Request $request): string|null
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
                'user' => $request->user(),
            ],
            'language' => [
                'messages' => $this->getTranslations(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
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