@extends('layouts.app')

@section('page_title', 'Pilih Metode &amp; Split Bayar')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .numpad-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem 0;
                font-size: 1.25rem;
                font-weight: 700;
                background-color: #ffffff;
                border: 1px solid #f1f5f9;
                border-radius: 0.75rem;
                transition: background-color 0.2s ease;
            }
            .numpad-btn:active {
                background-color: #f1f5f9;
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Pilih Pembayaran" backUrl="/kasir" :showMenuButton="false">
            <x-slot name="actions">
                <button class="px-3 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-bold">SPLIT BILL</button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex h-full flex-col">
            <div class="flex-1 flex flex-col overflow-y-auto no-scrollbar pb-24">
                <div class="px-6 py-8 bg-slate-50 border-b border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Total Tagihan</p>
                    <h2 id="total-amount" class="text-4xl font-black text-slate-900">Rp 209.790</h2>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <span id="payment-status" class="px-3 py-1 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold">BELUM LUNAS</span>
                        <p class="text-xs text-slate-500">Sisa: <span id="remaining-amount" class="font-bold">Rp 209.790</span></p>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <p class="text-xs font-bold text-slate-400 uppercase ml-1">Metode Pembayaran</p>
                    <div class="border-2 border-slate-200 rounded-2xl overflow-hidden" data-payment-method="cash">
                        <div class="flex items-center justify-between p-4 bg-white">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined">payments</span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Tunai / Cash</p>
                                    <p class="text-xs text-slate-500">Bayar dengan uang fisik</p>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-primary hidden" data-method-active="cash">check_circle</span>
                            <span class="material-symbols-outlined text-slate-300" data-method-inactive="cash">chevron_right</span>
                        </div>
                        <div class="bg-slate-50 p-4 border-t border-slate-100">
                            <div class="bg-white border border-slate-200 rounded-xl p-3 mb-4">
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Jumlah Bayar</p>
                                <div class="flex justify-between items-center">
                                    <span id="pay-amount" class="text-lg font-black text-slate-900">Rp 210.000</span>
                                    <button id="reset-pay" class="text-primary text-xs font-bold" type="button">RESET</button>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mb-4 px-1">
                                <p class="text-sm font-medium text-slate-600">Kembalian:</p>
                                <p id="change-amount" class="text-lg font-bold text-emerald-600">Rp 210</p>
                            </div>
                            <p id="payment-error" class="text-xs text-red-500 font-semibold hidden"></p>
                            <div class="grid grid-cols-3 gap-2">
                                <button class="numpad-btn">1</button>
                                <button class="numpad-btn">2</button>
                                <button class="numpad-btn">3</button>
                                <button class="numpad-btn">4</button>
                                <button class="numpad-btn">5</button>
                                <button class="numpad-btn">6</button>
                                <button class="numpad-btn">7</button>
                                <button class="numpad-btn">8</button>
                                <button class="numpad-btn">9</button>
                                <button class="numpad-btn">000</button>
                                <button class="numpad-btn">0</button>
                                <button class="numpad-btn bg-slate-50 text-slate-400">
                                    <span class="material-symbols-outlined">backspace</span>
                                </button>
                            </div>
                            <div id="quick-amount-list" class="flex gap-2 mt-4 overflow-x-auto no-scrollbar pb-1"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border-2 border-slate-200 rounded-2xl active:bg-slate-50" data-payment-method="qris">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl">qr_code_2</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">QRIS / E-Wallet</p>
                                <p class="text-xs text-slate-500">Gopay, OVO, Dana, ShopeePay</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary hidden" data-method-active="qris">check_circle</span>
                        <span class="material-symbols-outlined text-slate-300" data-method-inactive="qris">chevron_right</span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border-2 border-slate-200 rounded-2xl active:bg-slate-50" data-payment-method="bank">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                                <span class="material-symbols-outlined">credit_card</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">Bank Transfer / EDC</p>
                                <p class="text-xs text-slate-500">BCA, Mandiri, BRI, BNI</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary hidden" data-method-active="bank">check_circle</span>
                        <span class="material-symbols-outlined text-slate-300" data-method-inactive="bank">chevron_right</span>
                    </div>
                    <div id="bank-account-picker" class="hidden mt-3 p-4 bg-white border border-slate-200 rounded-2xl">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-bold text-slate-400 uppercase">Pilih Akun Bank</p>
                            <a id="bank-manage-link" class="text-xs font-bold text-primary" href="/settings/chart-of-accounts">Kelola</a>
                        </div>
                        <div id="bank-account-list" class="space-y-2"></div>
                        <p id="bank-account-empty" class="text-xs text-slate-400 hidden">Belum ada akun bank. Tambahkan di COA.</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mt-6">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-sm mt-0.5">info</span>
                            <p class="text-xs text-blue-800 leading-relaxed">
                                Anda dapat menggabungkan beberapa metode pembayaran. Pilih satu metode lalu masukkan nominal yang ingin dibayarkan sebagian.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-slate-100 shadow-[0_-10px_20px_rgba(0,0,0,0.02)] mb-20">
                <button id="confirm-payment" class="w-full h-14 bg-emerald-500 text-white font-black rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-emerald-500/20 active:scale-95 transition-transform" type="button">
                    <span class="material-symbols-outlined font-bold">check_circle</span>
                    KONFIRMASI PEMBAYARAN
                </button>
                <div class="mt-4 flex justify-around">
                    <div class="flex flex-col items-center gap-1 opacity-40">
                        <span class="material-symbols-outlined">receipt</span>
                        <span class="text-[10px] font-bold">Simpan Pesanan</span>
                    </div>
                    <div class="flex flex-col items-center gap-1 opacity-40">
                        <span class="material-symbols-outlined">share</span>
                        <span class="text-[10px] font-bold">Bagikan Tagihan</span>
                    </div>
                </div>
            </div>
        </div>
        @include('partials.ui-helpers')
        <script>
            (() => {
                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const els = {
                    total: document.getElementById('total-amount'),
                    remaining: document.getElementById('remaining-amount'),
                    pay: document.getElementById('pay-amount'),
                    change: document.getElementById('change-amount'),
                    status: document.getElementById('payment-status'),
                    reset: document.getElementById('reset-pay'),
                    error: document.getElementById('payment-error'),
                    confirm: document.getElementById('confirm-payment'),
                    quickAmounts: document.getElementById('quick-amount-list'),
                    bankPicker: document.getElementById('bank-account-picker'),
                    bankList: document.getElementById('bank-account-list'),
                    bankEmpty: document.getElementById('bank-account-empty'),
                };

                const loadSnapshot = () => {
                    try {
                        const raw = sessionStorage.getItem('pos_payment');
                        return raw ? JSON.parse(raw) : null;
                    } catch (error) {
                        return null;
                    }
                };

                const snapshot = loadSnapshot();
                const state = {
                    total: snapshot?.total || 0,
                    paidInput: '',
                    method: 'cash',
                    bankAccounts: [],
                    selectedBankId: null,
                };

                const preloadedBanks = @json($bankAccounts ?? []);
                if (Array.isArray(preloadedBanks) && preloadedBanks.length) {
                    state.bankAccounts = preloadedBanks;
                    state.selectedBankId = state.bankAccounts[0]?.id || null;
                }

                const methodConfig = {
                    cash: { accountKey: 'cash_account_id', method: 'cash' },
                    bank: { accountKey: 'bank_account_id', method: 'bank' },
                    qris: { accountKey: 'qris_account_id', method: 'qris' },
                };

                const setMethod = (method) => {
                    if (!methodConfig[method]) {
                        return;
                    }
                    state.method = method;
                    document.querySelectorAll('[data-payment-method]').forEach((card) => {
                        const active = card.dataset.paymentMethod === method;
                        card.classList.toggle('border-primary', active);
                        card.classList.toggle('bg-primary/5', active);
                        card.classList.toggle('shadow-sm', active);
                        card.classList.toggle('border-slate-200', !active);
                    });
                    document.querySelectorAll('[data-method-active]').forEach((icon) => {
                        icon.classList.toggle('hidden', icon.dataset.methodActive !== method);
                    });
                    document.querySelectorAll('[data-method-inactive]').forEach((icon) => {
                        icon.classList.toggle('hidden', icon.dataset.methodInactive === method);
                    });
                    els.bankPicker.classList.toggle('hidden', method !== 'bank');
                };

                const loadBankAccounts = async () => {
                    const response = await fetch('/api/v1/bank-accounts', { headers });
                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload?.message || 'Gagal memuat akun bank.');
                    }
                    return response.json();
                };

                const fallbackLoadBankAccounts = async () => {
                    const url = new URL('/api/v1/chart-of-accounts', window.location.origin);
                    url.searchParams.set('type', 'asset');
                    const response = await fetch(url, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat akun bank.');
                    }
                    const data = await response.json();
                    const accounts = data.accounts || [];
                    const parent = accounts.find((account) => account.code === '1102' && !account.parent_id)
                        || accounts.find((account) => !account.parent_id && String(account.name || '').toLowerCase().includes('bank'));
                    if (!parent) {
                        return [];
                    }
                    const children = accounts.filter((account) => account.parent_id === parent.id);
                    return children.length ? children : [parent];
                };

                const renderBankAccounts = () => {
                    els.bankList.innerHTML = '';
                    if (!state.bankAccounts.length) {
                        els.bankEmpty.classList.remove('hidden');
                        return;
                    }

                    els.bankEmpty.classList.add('hidden');
                    state.bankAccounts.forEach((account) => {
                        const isActive = state.selectedBankId === account.id;
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `w-full flex items-center justify-between gap-3 p-3 rounded-xl border ${isActive ? 'border-primary bg-primary/5' : 'border-slate-200'} transition`;
                        button.innerHTML = `
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-900">${account.name}</p>
                                <p class="text-xs text-slate-500">${account.code}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a class="text-[11px] font-bold text-primary" href="/settings/bank-accounts/${account.id}" onclick="event.stopPropagation()">Riwayat</a>
                                <span class="material-symbols-outlined text-primary ${isActive ? '' : 'hidden'}">check_circle</span>
                            </div>
                        `;
                        button.addEventListener('click', () => {
                            state.selectedBankId = account.id;
                            renderBankAccounts();
                        });
                        els.bankList.appendChild(button);
                    });
                };

                const showError = (message) => {
                    if (!message) {
                        els.error.classList.add('hidden');
                        els.error.textContent = '';
                        return;
                    }
                    els.error.textContent = message;
                    els.error.classList.remove('hidden');
                };

                const setStatus = (isPaid) => {
                    if (isPaid) {
                        els.status.textContent = 'LUNAS';
                        els.status.className = 'px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold';
                    } else {
                        els.status.textContent = 'BELUM LUNAS';
                        els.status.className = 'px-3 py-1 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold';
                    }
                };

                const updateAmounts = () => {
                    const paid = Number(state.paidInput || 0);
                    const total = state.total || 0;
                    const remaining = Math.max(total - paid, 0);
                    const change = Math.max(paid - total, 0);
                    els.total.textContent = formatCurrency(total);
                    els.remaining.textContent = formatCurrency(remaining);
                    els.pay.textContent = formatCurrency(paid);
                    els.change.textContent = formatCurrency(change);
                    setStatus(paid >= total && total > 0);
                };

                const appendDigit = (value) => {
                    state.paidInput = `${state.paidInput}${value}`.replace(/^0+(\d)/, '$1');
                    updateAmounts();
                };

                const backspace = () => {
                    state.paidInput = state.paidInput.slice(0, -1);
                    updateAmounts();
                };

                const setPaid = (value) => {
                    state.paidInput = String(Math.max(0, Math.floor(value)));
                    updateAmounts();
                };

                const formatQuickAmountLabel = (value) => {
                    if (value >= 1000000) {
                        return `Rp ${Math.round(value / 1000000)}jt`;
                    }

                    if (value >= 1000) {
                        return `Rp ${Math.round(value / 1000)}rb`;
                    }

                    return formatCurrency(value);
                };

                const renderQuickAmounts = () => {
                    const total = Math.max(0, Math.floor(state.total || 0));
                    const candidates = [
                        50000,
                        100000,
                        200000,
                        500000,
                        Math.ceil(total / 50000) * 50000,
                        Math.ceil(total / 100000) * 100000,
                    ].filter((value) => Number.isFinite(value) && value > 0);

                    const unique = [...new Set(candidates)];
                    const sorted = unique.sort((a, b) => a - b);

                    els.quickAmounts.innerHTML = '';

                    sorted.forEach((amount) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'quick-amount px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 whitespace-nowrap';
                        button.dataset.amount = String(amount);
                        button.textContent = formatQuickAmountLabel(amount);
                        button.addEventListener('click', () => setPaid(amount));
                        els.quickAmounts.appendChild(button);
                    });

                    const exactButton = document.createElement('button');
                    exactButton.type = 'button';
                    exactButton.className = 'quick-amount px-4 py-2 bg-primary/10 border border-primary/20 rounded-lg text-xs font-bold text-primary whitespace-nowrap';
                    exactButton.dataset.amount = 'exact';
                    exactButton.textContent = 'Uang Pas';
                    exactButton.addEventListener('click', () => setPaid(total));
                    els.quickAmounts.appendChild(exactButton);
                };

                if (snapshot) {
                    setPaid(state.total);
                } else {
                    updateAmounts();
                }

                renderQuickAmounts();

                document.querySelectorAll('[data-payment-method]').forEach((card) => {
                    card.addEventListener('click', () => {
                        setMethod(card.dataset.paymentMethod);
                    });
                });

                if (state.bankAccounts.length) {
                    renderBankAccounts();
                } else {
                    loadBankAccounts()
                        .then((data) => {
                            state.bankAccounts = data.accounts || [];
                            state.selectedBankId = state.bankAccounts[0]?.id || null;
                            renderBankAccounts();
                        })
                        .catch(() => {
                            fallbackLoadBankAccounts()
                                .then((accounts) => {
                                    state.bankAccounts = accounts || [];
                                    state.selectedBankId = state.bankAccounts[0]?.id || null;
                                    renderBankAccounts();
                                })
                                .catch(() => {
                                    els.bankEmpty.textContent = 'Gagal memuat akun bank.';
                                    els.bankEmpty.classList.remove('hidden');
                                });
                        });
                }

                document.querySelectorAll('.numpad-btn').forEach((button) => {
                    button.addEventListener('click', () => {
                        const label = button.textContent.trim();
                        if (label === '') {
                            return;
                        }
                        if (label === 'backspace') {
                            backspace();
                            return;
                        }
                        if (label === '000') {
                            appendDigit('000');
                            return;
                        }
                        if (/^\d$/.test(label)) {
                            appendDigit(label);
                        }
                    });
                });

                els.reset.addEventListener('click', () => {
                    state.paidInput = '';
                    updateAmounts();
                });

                els.confirm.addEventListener('click', async () => {
                    showError('');
                    if (!snapshot) {
                        showError('Data pembayaran tidak ditemukan.');
                        return;
                    }

                    if (state.method === 'qris') {
                        showError('Metode QRIS belum tersedia.');
                        return;
                    }

                    const paid = Number(state.paidInput || 0);
                    if (paid < state.total) {
                        showError('Jumlah bayar kurang dari total.');
                        return;
                    }

                    const selectedConfig = methodConfig[state.method] || methodConfig.cash;
                    const accountId = state.method === 'bank'
                        ? (state.selectedBankId || snapshot.account_mapping?.bank_account_id)
                        : snapshot.account_mapping?.[selectedConfig.accountKey];
                    if (!accountId) {
                        showError(state.method === 'bank' ? 'Akun bank belum disiapkan.' : 'Akun kas belum disiapkan.');
                        return;
                    }

                    const allowed = window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Pembayaran',
                        message: 'Pastikan nominal bayar dan kembalian sudah benar.',
                    });
                    if (!allowed) {
                        return;
                    }

                    const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                    const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                    const payload = {
                        idempotency_key: snapshot.idempotency_key || `${Date.now()}-${Math.random()}`,
                        branch_id: snapshot.branch_id,
                        shift_id: snapshot.shift_id,
                        customer_id: snapshot.customer_id || null,
                        subtotal: snapshot.subtotal,
                        discount: snapshot.discount || 0,
                        tax: snapshot.tax,
                        total: snapshot.total,
                        items: snapshot.checkout_items || [],
                        payments: [{
                            account_id: accountId,
                            method: selectedConfig.method,
                            amount: snapshot.total,
                        }],
                    };

                    await window.PosUi.runWithSubmitGuard(els.confirm, async () => {
                        try {
                            const response = await window.PosUi.fetchWithTimeout('/api/v1/checkout', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify(payload),
                            }, 15000);

                            if (!response.ok) {
                                const mapped = await window.PosUi.mapApiError(response, 'Checkout gagal.');
                                throw new Error(mapped.message);
                            }

                            const result = await response.json().catch(() => ({}));
                            const saleId = result?.sale_id || null;
                            const receipt = {
                                ...snapshot,
                                method: selectedConfig.method,
                                paid,
                                change: Math.max(paid - snapshot.total, 0),
                                invoice: saleId,
                                createdAt: new Date().toISOString(),
                            };
                            sessionStorage.setItem('pos_receipt', JSON.stringify(receipt));
                            sessionStorage.removeItem('pos_payment');
                            window.location.href = saleId ? `/receipt-preview?sale_id=${saleId}` : '/receipt-preview';
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                showError('Permintaan timeout. Cek riwayat transaksi sebelum ulang checkout.');
                                return;
                            }
                            showError(error.message || 'Checkout gagal.');
                        }
                    });
                });

                setMethod(state.method);
            })();
        </script>
@endsection
