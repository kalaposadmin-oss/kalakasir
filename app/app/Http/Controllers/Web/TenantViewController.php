<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WebRouteContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantViewController extends Controller
{
    public function __construct(private readonly WebRouteContextService $routeContext)
    {
    }

    public function show(Request $request): View
    {
        $view = (string) $request->route('view');
        $data = $request->route('data', []);

        abort_unless($view !== '', 404);

        return view($view, $this->routeContext->withTenant(is_array($data) ? $data : []));
    }

    public function showWithRouteParam(Request $request): View
    {
        $view = (string) $request->route('view');
        $routeParam = (string) $request->route('routeParam');
        $viewDataKey = (string) $request->route('viewDataKey');
        $data = $request->route('data', []);

        abort_unless($view !== '' && $routeParam !== '' && $viewDataKey !== '', 404);

        $payload = is_array($data) ? $data : [];
        $payload[$viewDataKey] = $request->route($routeParam);

        return view($view, $this->routeContext->withTenant($payload));
    }
}