<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyWebRedirectService
{
    public function to(string $target, Request $request): RedirectResponse
    {
        return redirect()->to($this->appendRawQuery($target, $request->getQueryString()));
    }

    public function entityOrFallback(Request $request, string $queryKey, callable $detailTarget, string $fallbackTarget): RedirectResponse
    {
        if ($request->filled($queryKey)) {
            $query = $request->query();
            $entityId = trim((string) ($query[$queryKey] ?? ''));
            unset($query[$queryKey]);

            return redirect()->to($this->appendArrayQuery($detailTarget($entityId), $query));
        }

        return redirect()->to($this->appendRawQuery($fallbackTarget, $request->getQueryString()));
    }

    public function fromExplicitQuery(Request $request, string $queryKey, callable $targetResolver): RedirectResponse
    {
        $query = $request->query();
        $value = trim((string) ($query[$queryKey] ?? ''));
        unset($query[$queryKey]);

        return redirect()->to($this->appendArrayQuery($targetResolver($value), $query));
    }

    private function appendRawQuery(string $target, ?string $query): string
    {
        return $query ? $target.'?'.$query : $target;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function appendArrayQuery(string $target, array $query): string
    {
        return $query !== [] ? $target.'?'.http_build_query($query) : $target;
    }
}