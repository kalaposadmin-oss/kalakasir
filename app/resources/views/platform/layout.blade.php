@extends('layouts.app')

@php
    $showMenu = false;
@endphp

@section('page_title', $pageTitle ?? 'Platform Admin')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light text-slate-900')
@section('main_class', 'flex-1 overflow-y-auto bg-background-light lg:pl-72')

@section('content')
    <div class="min-h-screen bg-background-light">
        @include('platform.partials.sidebar', ['active' => $active ?? 'dashboard'])

        <div class="min-h-screen">
            @include('platform.partials.topbar')

            <div class="space-y-6 px-6 py-6">
                @yield('platform_content')
            </div>
        </div>
    </div>

    <div id="platform-toast" class="fixed bottom-6 right-6 z-50 hidden max-w-sm rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-xl">
        <p class="font-semibold" data-toast-title>Notifikasi</p>
        <p class="mt-1 text-xs text-slate-500" data-toast-message></p>
    </div>
@endsection

@prepend('scripts')
    @once
        <script>
            (() => {
                const toast = document.getElementById('platform-toast');
                const titleEl = toast?.querySelector('[data-toast-title]');
                const messageEl = toast?.querySelector('[data-toast-message]');

                const createRequestId = () => {
                    if (crypto?.randomUUID) {
                        return crypto.randomUUID();
                    }
                    return `req-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                };

                const createIdempotencyKey = (prefix, targetId) => {
                    const seed = crypto?.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                    return `${prefix}-${targetId}-${seed}`;
                };

                let toastTimeoutId = null;

                const showToast = (message, tone = 'success', requestId = null) => {
                    if (!toast || !titleEl || !messageEl) {
                        return;
                    }

                    const titleText = tone === 'error' ? 'Gagal' : 'Berhasil';
                    titleEl.textContent = titleText;
                    messageEl.textContent = requestId ? `${message} (request_id: ${requestId})` : message;

                    toast.classList.remove('hidden', 'border-emerald-200', 'border-rose-200', 'text-emerald-700', 'text-rose-600');
                    if (tone === 'error') {
                        toast.classList.add('border-rose-200', 'text-rose-600');
                    } else {
                        toast.classList.add('border-emerald-200', 'text-emerald-700');
                    }

                    if (toastTimeoutId) {
                        clearTimeout(toastTimeoutId);
                    }
                    toastTimeoutId = setTimeout(() => {
                        toast.classList.add('hidden');
                    }, 4200);
                };

                const apiFetch = async (url, options = {}) => {
                    const requestId = createRequestId();
                    const headers = new Headers(options.headers || {});
                    headers.set('Accept', 'application/json');
                    headers.set('X-Request-Id', requestId);
                    if (!headers.has('Content-Type')) {
                        headers.set('Content-Type', 'application/json');
                    }
                    const tenantId = document.querySelector('meta[name="tenant-id"]')?.getAttribute('content');
                    if (tenantId) {
                        headers.set('X-Tenant-Id', tenantId);
                    }

                    const response = await fetch(url, {
                        ...options,
                        headers,
                    });

                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (error) {
                        payload = null;
                    }

                    return { response, payload, requestId };
                };

                window.PlatformUi = {
                    apiFetch,
                    showToast,
                    createIdempotencyKey,
                };
            })();
        </script>
    @endonce
@endprepend
