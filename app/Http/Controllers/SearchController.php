<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\LineItem;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $searchTerm = $request->input('searchTerm');

        $receipts = Receipt::search($searchTerm)->get();
        $lineItems = LineItem::search($searchTerm)->get();



        return view('search', ['receipts' => $receipts, 'lineItems' => $lineItems]);
    }

}
