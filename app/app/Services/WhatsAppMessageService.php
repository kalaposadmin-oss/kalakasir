<?php

namespace App\Services;

use App\Models\OnlineOrder;
use App\Models\PaymentSetting;
use Illuminate\Support\Collection;

class WhatsAppMessageService
{
    public function normalizePhoneNumber(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    public function buildLink(?string $phone, string $message): ?string
    {
        $normalized = $this->normalizePhoneNumber($phone);

        if ($normalized === '') {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    public function buildAdminOrderMessage(OnlineOrder $order, Collection $items, ?PaymentSetting $paymentSetting = null): string
    {
        $lines = [
            'Halo admin, ada pesanan baru:',
            '',
            'Kode Order: '.$order->order_code,
            'Nama: '.$order->customer_name,
            'WA: '.$order->customer_phone,
            'Metode: '.($order->fulfillment_type === 'delivery' ? 'Dikirim' : 'Ambil di toko'),
            'Jam: '.($order->fulfillment_time ?: '-'),
            'Alamat: '.($order->address ?: '-'),
            '',
            'Pesanan:',
        ];

        foreach ($items as $item) {
            $lines[] = '- '.$item->product_name.' x'.$item->qty.' = Rp'.number_format((float) $item->subtotal, 0, ',', '.');
        }

        $lines[] = '';
        $lines[] = 'Subtotal: Rp'.number_format((float) $order->subtotal, 0, ',', '.');
        $lines[] = 'Catatan: '.($order->customer_note ?: '-');
        $lines[] = '';
        $lines[] = 'Link Admin (rekap + PIN + aksi):';
        $lines[] = url('/admin-order/'.$order->order_code);
        $lines[] = '';
        $lines[] = 'Mohon buka rekap, verifikasi PIN, lalu pilih aksi yang diperlukan.';

        if ($paymentSetting?->payment_note) {
            $lines[] = '';
            $lines[] = $paymentSetting->payment_note;
        }

        return implode("\n", $lines);
    }

    public function buildCustomerPaymentMessage(OnlineOrder $order, ?PaymentSetting $paymentSetting = null): string
    {
        $lines = [
            'Halo kak '.$order->customer_name.', ini rekap final pesanan kakak:',
            '',
            'Kode Order: '.$order->order_code,
            '',
            'Pesanan:',
        ];

        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->orderBy('created_at')->get();

        foreach ($items as $item) {
            $lines[] = '- '.$item->product_name.' x'.$item->qty.' = Rp'.number_format((float) $item->subtotal, 0, ',', '.');
        }

        $lines = array_merge($lines, [
            'Subtotal Produk: Rp'.number_format((float) $order->subtotal, 0, ',', '.'),
            'Ongkir'.($order->shipping_provider ? ' '.$order->shipping_provider : '').': Rp'.number_format((float) $order->shipping_cost, 0, ',', '.'),
            'Promo/Diskon Ongkir: -Rp'.number_format((float) $order->shipping_discount, 0, ',', '.'),
            'Biaya Tambahan: Rp'.number_format((float) $order->extra_fee, 0, ',', '.'),
            '',
            'Total Bayar: Rp'.number_format((float) $order->grand_total, 0, ',', '.'),
            '',
            'Pembayaran bisa transfer ke:',
            'Bank: '.($paymentSetting?->bank_name ?: '-'),
            'No. Rekening: '.($paymentSetting?->bank_account_number ?: '-'),
            'Atas Nama: '.($paymentSetting?->bank_account_name ?: '-'),
        ]);

        $qrisImageUrl = trim((string) ($paymentSetting?->qris_image_url ?? ''));
        if ($qrisImageUrl !== '') {
            $lines[] = 'QRIS: '.$qrisImageUrl;
        }

        $lines = array_merge($lines, [
            '',
            'Mohon transfer sesuai total dan kirim bukti pembayaran di chat ini ya kak.',
            'Terima kasih.',
        ]);

        return implode("\n", $lines);
    }
}
