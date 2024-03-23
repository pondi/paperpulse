<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Merchant;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = Merchant::has('receipts')->get();
        return view('merchants', compact('merchants'));
    }
}
