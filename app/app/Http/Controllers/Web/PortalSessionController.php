<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PortalSwitchService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalSessionController extends Controller
{
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        TenantContext::clear();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function switch(Request $request, string $portal, PortalSwitchService $service)
    {
        $targetUrl = $service->switch($request, $portal, Auth::user());

        return redirect()->to($targetUrl);
    }
}