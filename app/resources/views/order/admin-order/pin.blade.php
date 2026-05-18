@extends('layouts.app')

@section('page_title', 'Verifikasi PIN Admin')

@section('ui_mode', 'backoffice')

@section('body_class', 'bg-background-light dark:bg-background-dark')

@section('header')
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order') }}" class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100 transition">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-sm font-bold">Verifikasi PIN Admin</h1>
            <p class="text-[11px] text-[var(--app-muted)]">Order #{{ $code }}</p>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto')

@section('content')
<div class="p-4 max-w-md mx-auto">
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-6 space-y-6">
        <div class="text-center">
            <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-50">
                <span class="material-symbols-outlined text-[28px] text-orange-500">lock</span>
            </span>
            <h2 class="mt-4 text-lg font-bold">Masukkan PIN Admin</h2>
            <p class="mt-1 text-sm text-[var(--app-muted)]">Diperlukan untuk melanjutkan proses order</p>
        </div>

        <form method="POST" action="{{ url('/admin-order/' . $code . '/verify-pin') }}" class="space-y-4">
            @csrf
            <div class="relative">
                <label class="text-[11px] text-[var(--app-muted)]">PIN Admin</label>
                <input type="password" name="admin_pin" required maxlength="6" minlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       class="w-full h-12 text-center text-xl font-bold tracking-widest rounded-lg bg-slate-50 border border-[var(--app-border)] outline-none focus:ring-2 focus:ring-orange-300"
                       placeholder="123456" />
            </div>

            @error('admin_pin')
                <p class="text-xs text-red-500 bg-red-50 px-3 py-2 rounded-lg">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="w-full h-12 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 transition-colors active:scale-95">
                Verifikasi PIN
            </button>
        </form>

        <div class="text-center">
            <p class="text-xs text-[var(--app-muted)]">
                Belum punya PIN? <a href="{{ url('/settings/admin-pin') }}" class="text-orange-500 font-semibold hover:underline">Atur PIN</a>
            </p>
        </div>
    </div>
</div>
@endsection
