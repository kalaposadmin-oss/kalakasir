@extends('layouts.app')

@section('page_title', 'Ubah Produk')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Ubah Produk" backUrl="/products" :showMenuButton="false">
        <x-slot name="actions">
            <button class="flex size-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" type="button" data-menu-trigger>
                <span class="material-symbols-outlined">menu</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto px-4 py-4 pb-32">
            <section class="bg-white rounded-2xl shadow-xl ring-1 ring-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="font-bold text-slate-800">Ubah Produk</h2>
                    <span class="text-xs bg-slate-200 px-2 py-1 rounded font-medium" id="edit-id">ID: -</span>
                </div>
                <form id="edit-form" class="p-4 space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Nama Produk</label>
                        <input id="edit-name" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" type="text" value="" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase">SKU</label>
                            <input id="edit-sku" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" type="text" value="" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase">Harga (Rp)</label>
                            <input id="edit-price" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary font-mono" type="number" value="" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Kategori</label>
                        <select id="edit-category" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary appearance-none"></select>
                    </div>
                    <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
                    <div class="flex gap-3 pt-2">
                        <button class="flex-1 py-3 px-4 rounded-xl border border-slate-200 text-slate-700 font-bold hover:bg-slate-50" type="button" id="cancel-edit">Batal</button>
                        <button class="flex-1 py-3 px-4 rounded-xl border border-rose-200 text-rose-600 font-bold hover:bg-rose-50" type="button" id="delete-product">Hapus</button>
                        <button class="flex-[2] py-3 px-4 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:opacity-90" type="submit">Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
                const productId = document.querySelector('meta[name="product-id"]')?.content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    editId: document.getElementById('edit-id'),
                    editName: document.getElementById('edit-name'),
                    editSku: document.getElementById('edit-sku'),
                    editPrice: document.getElementById('edit-price'),
                    editCategory: document.getElementById('edit-category'),
                    form: document.getElementById('edit-form'),
                    cancel: document.getElementById('cancel-edit'),
                    error: document.getElementById('form-error'),
                    deleteProduct: document.getElementById('delete-product'),
                };

                let currentProduct = null;

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const goBack = () => {
                    window.location.href = '/products';
                };

                const loadCategories = async () => {
                    const response = await fetch('/api/v1/categories', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.categories || [];
                };

                const loadProduct = async () => {
                    const response = await fetch(`/api/v1/products/${productId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Produk tidak ditemukan.');
                    }
                    const data = await response.json();
                    return data.product;
                };

                const renderProduct = (product) => {
                    currentProduct = product;
                    el.editId.textContent = `ID: ${String(product.id).slice(0, 8).toUpperCase()}`;
                    el.editName.value = product.name || '';
                    el.editSku.value = product.sku || '';
                    el.editPrice.value = product.price || 0;
                    el.editCategory.value = product.category_id || '';
                };

                el.cancel.addEventListener('click', goBack);

                el.form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    showError('');

                    if (!currentProduct) {
                        showError('Produk tidak ditemukan.');
                        return;
                    }

                    const payload = {
                        name: el.editName.value.trim(),
                        sku: el.editSku.value.trim(),
                        price: Number(el.editPrice.value || 0),
                        category_id: el.editCategory.value || null,
                        hpp_average: currentProduct.hpp_average || 0,
                    };

                    if (!payload.name || !payload.sku) {
                        showError('Nama produk dan SKU wajib diisi.');
                        return;
                    }

                    if (payload.price <= 0) {
                        showError('Harga harus lebih dari 0.');
                        return;
                    }

                    try {
                        const response = await fetch(`/api/v1/products/${currentProduct.id}`, {
                            method: 'PUT',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menyimpan perubahan.');
                        }

                        goBack();
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan perubahan.');
                    }
                });

                el.deleteProduct.addEventListener('click', async () => {
                    showError('');

                    if (!currentProduct) {
                        showError('Produk tidak ditemukan.');
                        return;
                    }

                    const confirmed = confirm('Hapus produk ini?');
                    if (!confirmed) {
                        return;
                    }

                    try {
                        const response = await fetch(`/api/v1/products/${currentProduct.id}`, {
                            method: 'DELETE',
                            headers,
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menghapus produk.');
                        }

                        goBack();
                    } catch (error) {
                        showError(error.message || 'Gagal menghapus produk.');
                    }
                });

                (async () => {
                    const [categories, product] = await Promise.all([loadCategories(), loadProduct()]);

                    el.editCategory.innerHTML = '<option value="">Pilih kategori</option>';
                    categories.forEach((category) => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        el.editCategory.appendChild(option);
                    });

                    renderProduct(product);
                })().catch((error) => {
                    showError(error.message || 'Gagal memuat data produk.');
                });
            })();
        </script>
@endsection
