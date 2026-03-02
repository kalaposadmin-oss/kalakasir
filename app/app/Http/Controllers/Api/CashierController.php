<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class CashierController extends Controller
{
    public function index()
    {
        $cashiers = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'cashiers' => $cashiers,
        ]);
    }
}
