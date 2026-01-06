<?php

namespace App\Http\Controllers;

use App\Models\DuplicateFlag;
use App\Services\Duplicates\DuplicateFlagTransformer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DuplicateController extends Controller
{
    public function index(Request $request)
    {
        $duplicates = DuplicateFlag::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->with([
                'file.primaryReceipt.merchant',
                'file.invoices',
                'duplicateFile.primaryReceipt.merchant',
                'duplicateFile.invoices',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DuplicateFlag $flag) => DuplicateFlagTransformer::forIndex($flag));

        return Inertia::render('Duplicates/Index', [
            'duplicates' => $duplicates,
        ]);
    }
}
