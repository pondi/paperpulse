<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cookies while on the site domain, then reset to blank page
        foreach (static::$browsers as $browser) {
            $browser->visit('/');
            $browser->driver->manage()->deleteAllCookies();
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--ignore-certificate-errors',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Login as a user in the browser using Dusk's built-in session auth.
     */
    protected function loginAs(Browser $browser, ?User $user = null): Browser
    {
        $user ??= User::factory()->create();

        return $browser
            ->loginAs($user)
            ->visit('/dashboard')
            ->waitForText('Dashboard', 10);
    }

    /**
     * Login via the actual login form (for auth flow tests).
     */
    protected function loginViaForm(Browser $browser, ?User $user = null): Browser
    {
        $user ??= User::factory()->create();

        return $browser
            ->visit('/login')
            ->waitFor('#email', 10)
            ->type('#email', $user->email)
            ->type('#password', 'password')
            ->click('form button')
            ->waitForLocation('/dashboard', 10);
    }

    /**
     * Create a verified test user.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'dusk-' . uniqid() . '@example.com',
        ], $attributes));
    }
}
