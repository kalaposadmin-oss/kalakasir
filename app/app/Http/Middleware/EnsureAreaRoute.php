<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAreaRoute
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $path = '/'.ltrim($request->path(), '/');

        abort_unless($path === '/'.$area || str_starts_with($path, '/'.$area.'/'), 500, 'Route area mismatch.');

        return $next($request);
    }
}
