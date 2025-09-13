<?php

namespace App\Http\Controllers\Merchants;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Merchants\LogoCacheService;
use Illuminate\Http\Response;

/**
 * Serves internally generated merchant logos.
 * Single responsibility: Handle logo HTTP requests.
 */
class MerchantLogoController extends Controller
{
    /**
     * Serve merchant logo as SVG.
     */
    public function show(Merchant $merchant): Response
    {
        // Check user has access to this merchant
        $hasAccess = $merchant->receipts()
            ->where('user_id', auth()->id())
            ->exists();

        if (! $hasAccess && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        $svg = LogoCacheService::getOrGenerate($merchant->id, $merchant->name);

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=2592000'); // 30 days
    }

    /**
     * Generate logo for merchant name without ID.
     */
    public function generate(string $name): Response
    {
        // Sanitize name
        $name = substr(trim(urldecode($name)), 0, 100);

        if (empty($name)) {
            $name = 'Unknown';
        }

        $svg = \App\Services\Merchants\LogoGenerator::generateSvg($name);

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400'); // 1 day
    }
}
