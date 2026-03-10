<?php

declare(strict_types=1);

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Value;

class AppPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->addNonce(Directive::SCRIPT)
            ->add(Directive::STYLE, Keyword::SELF)
            ->add(Directive::STYLE_ATTR, Keyword::UNSAFE_INLINE)
            ->addNonce(Directive::STYLE)
            ->add(Directive::IMG, [Keyword::SELF, 'data:', 'blob:'])
            ->add(Directive::FONT, [Keyword::SELF, 'data:'])
            ->add(Directive::CONNECT, [Keyword::SELF, 'data:'])
            ->add(Directive::MEDIA, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::SELF)
            ->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);

        if (app()->environment('local')) {
            $this->configureLocalEnvironment($policy);
        }
    }

    private function configureLocalEnvironment(Policy $policy): void
    {
        $policy->add(Directive::CONNECT, [
            'ws://localhost:*',
            'wss://localhost:*',
            'ws://paperpulse.test:*',
            'wss://paperpulse.test:*',
        ]);

        if (file_exists(public_path('hot'))) {
            $viteUrl = trim(file_get_contents(public_path('hot')));

            $policy
                ->add(Directive::DEFAULT, $viteUrl)
                ->add(Directive::SCRIPT, $viteUrl)
                ->add(Directive::STYLE, $viteUrl)
                ->add(Directive::CONNECT, $viteUrl);
        }
    }
}
