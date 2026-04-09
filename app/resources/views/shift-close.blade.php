@extends('layouts.app')

@section('page_title', 'Tutup Shift')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
        <x-page-header title="Tutup Shift" backUrl="/pos/dashboard" />
@endsection

@section('content')
        <div class="relative flex h-full flex-col">
            <div class="flex-1 overflow-y-auto pb-20">
                <div class="px-4 pt-6 pb-4">
                    <h3 class="text-slate-900 dark:text-slate-100 text-xl font-bold leading-tight">Konfirmasi Kas Akhir</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Pastikan jumlah uang tunai di laci sesuai dengan catatan sistem.</p>
                    <p class="text-xs text-slate-500 mt-2" id="shift-info">Shift: -</p>
                </div>
                <div class="px-4 pb-4">
                    @include('partials.owner-pos-panel', [
                        'subtitle' => 'Selesaikan tutup shift, lalu gunakan jalur cepat ke katalog, stok, atau pengaturan toko.',
                    ])
                </div>
                <div id="shift-closed-banner" class="mx-4 mb-4 hidden rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-800">Shift belum dibuka atau sudah ditutup.</p>
                    <p class="text-xs text-amber-700 mt-1">Silakan buka shift dulu sebelum menutup.</p>
                    <a href="/pos/shifts/open" class="mt-3 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                        <span class="material-symbols-outlined text-sm">lock_open</span>
                        Buka Shift
                    </a>
                </div>
                <div class="px-4 flex flex-col gap-4">
                    <div class="flex flex-col gap-2 rounded-xl p-5 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-medium uppercase tracking-wider">Ekspektasi Sistem</p>
                        <p class="text-slate-900 dark:text-slate-100 tracking-tight text-3xl font-bold leading-tight" id="expected-cash">Rp 0</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="flex flex-col flex-1">
                            <p class="text-slate-700 dark:text-slate-300 text-sm font-semibold pb-2">Kas Akhir di Laci (Aktual)</p>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-medium">Rp</span>
                                <input id="closing-cash" class="flex w-full rounded-xl text-slate-900 dark:text-slate-100 border border-primary bg-primary/5 focus:ring-2 focus:ring-primary h-16 pl-12 pr-4 text-2xl font-bold" type="text" inputmode="numeric" value="0" />
                            </div>
                        </label>
                        <div id="closing-cash-quick" class="flex gap-2 overflow-x-auto no-scrollbar pt-1"></div>
                    </div>
                    <div id="difference-card" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-500 text-sm" id="difference-icon">info</span>
                                <p class="text-slate-600 dark:text-slate-300 text-sm font-medium leading-normal" id="difference-label">Selisih</p>
                            </div>
                            <p class="text-slate-700 dark:text-slate-200 text-lg font-bold leading-normal text-right" id="difference-value">Rp 0</p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <label class="block text-slate-600 dark:text-slate-400 text-xs font-medium mb-1 px-1">Catatan Selisih (Opsional)</label>
                        <textarea id="close-note" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm focus:ring-primary focus:border-primary" placeholder="Contoh: Salah kembalian pelanggan..." rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="mt-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 pt-2 pb-6 px-0 shadow-2xl">
                <div class="w-full px-4">
                    <div class="w-full mx-auto sm:max-w-[430px]">
                        <div class="grid grid-cols-3 gap-2 mb-4 hidden" id="keypad">
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="1">1</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="2">2</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="3">3</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="4">4</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="5">5</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="6">6</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="7">7</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="8">8</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="9">9</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="000">000</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-value="0">0</button>
                            <button class="key h-12 flex items-center justify-center font-semibold text-lg text-slate-800 dark:text-slate-200 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-sm" data-action="backspace">
                                <span class="material-symbols-outlined">backspace</span>
                            </button>
                        </div>
                        <button id="close-shift" class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl shadow-lg transition-colors">
                            <span class="material-symbols-outlined">task_alt</span>
                            Konfirmasi dan Tutup Shift
                        </button>
                        <p id="close-shift-caption" class="mt-3 text-center text-xs font-medium text-slate-500">Samakan kas aktual dengan fisik laci sebelum menutup shift.</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    expected: document.getElementById('expected-cash'),
                    closing: document.getElementById('closing-cash'),
                    diffCard: document.getElementById('difference-card'),
                    diffLabel: document.getElementById('difference-label'),
                    diffValue: document.getElementById('difference-value'),
                    diffIcon: document.getElementById('difference-icon'),
                    keypad: document.getElementById('keypad'),
                    note: document.getElementById('close-note'),
                    close: document.getElementById('close-shift'),
                    banner: document.getElementById('shift-closed-banner'),
                    info: document.getElementById('shift-info'),
                    quickCash: document.getElementById('closing-cash-quick'),
                    caption: document.getElementById('close-shift-caption'),
                };

                let expectedCash = 0;
                let shiftId = null;
                let inputValue = '';

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const updateDifference = () => {
                    const closingValue = parseFloat(inputValue || '0');
                    const diff = closingValue - expectedCash;
                    const diffLabel = diff === 0 ? 'Selisih' : diff < 0 ? 'Selisih (Kurang)' : 'Selisih (Lebih)';
                    el.diffLabel.textContent = diffLabel;
                    el.diffValue.textContent = `${diff < 0 ? '-' : ''} ${formatCurrency(Math.abs(diff))}`;

                    if (diff === 0) {
                        el.diffCard.className = 'p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-900/30';
                        el.diffIcon.textContent = 'check_circle';
                        el.diffIcon.className = 'material-symbols-outlined text-emerald-600 text-sm';
                    } else if (diff < 0) {
                        el.diffCard.className = 'p-4 rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20';
                        el.diffIcon.textContent = 'error';
                        el.diffIcon.className = 'material-symbols-outlined text-red-600 text-sm';
                    } else {
                        el.diffCard.className = 'p-4 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/20';
                        el.diffIcon.textContent = 'warning';
                        el.diffIcon.className = 'material-symbols-outlined text-amber-600 text-sm';
                    }
                };

                const updateDisplay = () => {
                    const closingValue = parseFloat(inputValue || '0');
                    el.closing.value = new Intl.NumberFormat('id-ID').format(closingValue);
                    updateDifference();
                    const diff = closingValue - expectedCash;
                    el.caption.textContent = diff === 0
                        ? 'Kas sudah sesuai. Shift siap ditutup.'
                        : diff < 0
                            ? 'Kas akhir lebih kecil dari ekspektasi. Tambahkan catatan bila perlu.'
                            : 'Kas akhir lebih besar dari ekspektasi. Tambahkan catatan bila perlu.';
                };

                const renderQuickCash = () => {
                    const values = [
                        Math.max(0, Math.floor(expectedCash || 0)),
                        Math.max(0, Math.floor((expectedCash || 0) - 5000)),
                        Math.max(0, Math.floor((expectedCash || 0) + 5000)),
                    ];
                    const unique = [...new Set(values)];
                    el.quickCash.innerHTML = '';
                    unique.forEach((amount, index) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `whitespace-nowrap rounded-full border px-4 py-2 text-xs font-bold ${index === 0 ? 'border-primary/20 bg-primary/10 text-primary' : 'border-slate-200 bg-white text-slate-600'}`;
                        button.textContent = index === 0
                            ? 'Sesuai Sistem'
                            : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
                        button.addEventListener('click', () => {
                            inputValue = String(amount);
                            updateDisplay();
                        });
                        el.quickCash.appendChild(button);
                    });
                };

                const loadSummary = async () => {
                    const response = await fetch('/api/v1/shifts/summary', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat ringkasan shift.');
                    }
                    return response.json();
                };

                const setBlocked = (message) => {
                    if (el.banner) {
                        el.banner.classList.remove('hidden');
                    }
                    el.closing.value = '0';
                    el.closing.disabled = true;
                    el.note.disabled = true;
                    el.close.disabled = true;
                    el.close.classList.add('opacity-70', 'cursor-not-allowed');
                    el.expected.textContent = 'Rp 0';
                    el.diffLabel.textContent = message || 'Shift belum tersedia.';
                    el.diffValue.textContent = 'Rp 0';
                    el.diffIcon.textContent = 'error';
                    el.keypad.classList.add('hidden');
                    el.caption.textContent = message || 'Shift belum tersedia.';
                };

                const keypadEl = document.getElementById('keypad');

                // show keypad on small screens by default, otherwise hide until input focus
                if (window.innerWidth <= 640) {
                    keypadEl.classList.remove('hidden');
                } else {
                    keypadEl.classList.add('hidden');
                }

                const showKeypad = () => {
                    keypadEl.classList.remove('hidden');
                };

                el.closing.addEventListener('focus', showKeypad);
                el.closing.addEventListener('click', showKeypad);

                // hide keypad when clicking outside (desktop behavior)
                document.addEventListener('click', (ev) => {
                    if (window.innerWidth > 640) {
                        const target = ev.target;
                        if (!keypadEl.contains(target) && target !== el.closing) {
                            keypadEl.classList.add('hidden');
                        }
                    }
                });

                el.keypad.addEventListener('click', (event) => {
                    const button = event.target.closest('.key');
                    if (!button) {
                        return;
                    }
                    const value = button.dataset.value;
                    const action = button.dataset.action;
                    if (action === 'backspace') {
                        inputValue = inputValue.slice(0, -1);
                    } else if (value) {
                        inputValue = `${inputValue}${value}`.replace(/^0+(\d)/, '$1');
                    }
                    updateDisplay();
                });

                el.close.addEventListener('click', async () => {
                    const closingValue = parseFloat(inputValue || '0');
                    if (!shiftId) {
                        alert('Shift tidak ditemukan.');
                        return;
                    }
                    const response = await fetch('/api/v1/shifts/close', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            ...headers,
                        },
                        body: JSON.stringify({
                            shift_id: shiftId,
                            closing_cash: closingValue,
                            close_note: el.note.value.trim() || null,
                        }),
                    });
                    if (!response.ok) {
                        alert('Gagal menutup shift.');
                        return;
                    }
                    el.close.textContent = 'Shift Ditutup';
                    el.close.disabled = true;
                    el.close.classList.add('opacity-70', 'cursor-not-allowed');
                    setTimeout(() => {
                        window.location.href = shiftId
                            ? `/pos/shifts/${encodeURIComponent(shiftId)}/summary?from=${encodeURIComponent('/pos/dashboard')}`
                            : '/pos/shifts/summary';
                    }, 600);
                });

                (async () => {
                    try {
                        const data = await loadSummary();
                        expectedCash = data.expected_cash || 0;
                        shiftId = data.shift?.id || null;
                        if (!shiftId) {
                            setBlocked('Shift belum dibuka atau sudah ditutup.');
                            return;
                        }
                        if (el.info) {
                            const openedAt = data.shift?.opened_at ? new Date(data.shift.opened_at) : null;
                            const openedLabel = openedAt
                                ? openedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                                : '-';
                            const cashierName = data.cashier?.name || '-';
                            el.info.textContent = `Shift: ${cashierName} • Mulai: ${openedLabel}`;
                        }
                        el.expected.textContent = formatCurrency(expectedCash);
                        inputValue = String(Math.floor(expectedCash));
                        renderQuickCash();
                        updateDisplay();
                    } catch (error) {
                        setBlocked('Shift belum dibuka atau sudah ditutup.');
                    }
                })();
            })();
        </script>
    @endsection

