@extends('layouts.app')

@section('page_title', 'Logout')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@push('head')
    <style>
        .logout-spinner {
            width: 2.25rem;
            height: 2.25rem;
            border: 3px solid rgba(123, 174, 158, 0.25);
            border-top-color: #7bae9e;
            border-radius: 9999px;
            animation: logout-spin 700ms linear infinite;
        }
        @keyframes logout-spin {
            to { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12 text-center">
        <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
            <span class="material-symbols-outlined text-primary text-[36px]">logout</span>
        </div>
        <h1 class="text-xl font-bold">Mengeluarkan akun...</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Mohon tunggu sebentar.</p>
        <div class="mt-5 logout-spinner" aria-hidden="true"></div>

        <form id="logout-form" method="POST" action="/logout" class="hidden">
            @csrf
        </form>
        <noscript>
            <form method="POST" action="/logout" class="mt-6">
                @csrf
                <button type="submit" class="rounded-xl bg-primary px-5 py-2 text-sm font-bold text-white">
                    Logout
                </button>
            </form>
        </noscript>
    </div>

    <script>
        document.getElementById('logout-form').submit();
    </script>
@endsection
