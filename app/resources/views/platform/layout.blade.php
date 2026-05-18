@extends('layouts.app')

@php
    $showMenu = false;
@endphp

@section('page_title', $pageTitle ?? 'Platform Admin')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-slate-50 text-slate-900')
@section('main_class', 'flex-1 overflow-y-auto bg-slate-50 lg:pl-72')

@section('content')
    <div class="min-h-screen bg-slate-50">
        @include('platform.partials.sidebar', ['active' => $active ?? 'dashboard'])
        <div data-platform-sidebar-overlay class="fixed inset-0 z-20 hidden bg-slate-950/45 lg:hidden"></div>

        <div class="min-h-screen">
            @include('platform.partials.topbar')

            <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                @yield('platform_content')
            </div>
        </div>
    </div>

    <div id="platform-toast" class="fixed bottom-4 left-4 right-4 z-50 hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-xl sm:left-auto sm:right-6 sm:max-w-sm">
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

                const sidebar = document.querySelector('[data-platform-sidebar]');
                const overlay = document.querySelector('[data-platform-sidebar-overlay]');
                const toggles = document.querySelectorAll('[data-platform-sidebar-toggle]');
                const closeButtons = document.querySelectorAll('[data-platform-sidebar-close]');

                if (!sidebar || !overlay) {
                    return;
                }

                const openSidebar = () => {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                };

                const closeSidebar = () => {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                };

                toggles.forEach((button) => button.addEventListener('click', openSidebar));
                closeButtons.forEach((button) => button.addEventListener('click', closeSidebar));
                overlay.addEventListener('click', closeSidebar);

                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        overlay.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }
                });
            })();
        </script>
    @endonce
@endprepend
