<?php

declare(strict_types=1);

namespace App\Support\Csp;

use Illuminate\Support\Facades\Vite;
use Spatie\Csp\Nonce\NonceGenerator;

class ViteNonceGenerator implements NonceGenerator
{
    public function generate(): string
    {
        return Vite::useCspNonce();
    }
}
