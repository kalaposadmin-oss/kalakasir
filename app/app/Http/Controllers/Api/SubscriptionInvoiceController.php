<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionInvoiceController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $base = DB::table('invoices')
            ->where('tenant_id', $tenant->id)
            ->when(
                ! empty($validated['status']),
                static fn ($query) => $query->where('status', $validated['status'])
            );

        $total = (clone $base)->count();
        $rows = $base
            ->orderByDesc('period_end')
            ->offset($offset)
            ->limit($perPage)
            ->get([
                'id',
                'number',
                'period_start',
                'period_end',
                'amount_due',
                'amount_paid',
                'status',
                'issued_at',
                'due_at',
                'paid_at',
            ]);

        return ApiResponse::paginated([
            'invoices' => $rows,
        ], $page, $perPage, $total);
    }

    public function show(string $invoiceId): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $invoice = DB::table('invoices')
            ->where('tenant_id', $tenant->id)
            ->where('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return response()->json([
                'code' => 'INVOICE_NOT_FOUND',
                'message' => 'Invoice tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        $items = DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get(['id', 'description', 'qty', 'unit_price', 'total_price']);

        $payments = DB::table('subscription_payments')
            ->where('tenant_id', $tenant->id)
            ->where('invoice_id', $invoice->id)
            ->orderByDesc('paid_at')
            ->get(['id', 'provider', 'reference', 'status', 'amount', 'paid_at', 'created_at']);

        return response()->json([
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments,
        ]);
    }

    public function download(string $invoiceId)
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $invoice = DB::table('invoices')
            ->where('tenant_id', $tenant->id)
            ->where('id', $invoiceId)
            ->first();

        if (! $invoice) {
            return response()->json([
                'code' => 'INVOICE_NOT_FOUND',
                'message' => 'Invoice tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        $items = DB::table('invoice_items')
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get(['description', 'qty', 'unit_price', 'total_price']);

        $lines = [
            'Invoice: '.$invoice->number,
            'Periode: '.$invoice->period_start.' s/d '.$invoice->period_end,
            'Status: '.$invoice->status,
            'Due: '.($invoice->due_at ?? '-'),
            '----------------------------------------',
        ];

        foreach ($items as $item) {
            $lines[] = sprintf('%s x%s @%s = %s', (string) $item->description, (string) $item->qty, (string) $item->unit_price, (string) $item->total_price);
        }

        $lines[] = '----------------------------------------';
        $lines[] = 'Total: '.(string) $invoice->amount_due;
        $lines[] = 'Terbayar: '.(string) $invoice->amount_paid;

        $pdf = $this->buildSimplePdf($lines);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->number.'.pdf"',
        ]);
    }

    public function markPaid(Request $request, string $invoiceId): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:40'],
            'reference' => ['required', 'string', 'min:4', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $idempotencyKey = (string) $request->header('X-Idempotency-Key', '');
        if ($idempotencyKey !== '') {
            $cached = DB::table('subscription_operations')
                ->where('tenant_id', $tenant->id)
                ->where('operation', 'mark_invoice_paid')
                ->where('idempotency_key', $idempotencyKey)
                ->value('response_payload');

            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    $decoded['idempotent_replay'] = true;

                    return response()->json($decoded);
                }
            }
        }

        $result = DB::transaction(function () use ($tenant, $invoiceId, $validated, $request): array {
            $invoice = DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->where('id', $invoiceId)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                abort(response()->json([
                    'code' => 'INVOICE_NOT_FOUND',
                    'message' => 'Invoice tidak ditemukan.',
                    'details' => [],
                ], 404));
            }

            if ($invoice->status === 'paid') {
                return [
                    'status' => 'ok',
                    'invoice_id' => $invoiceId,
                    'invoice_status' => 'paid',
                    'already_paid' => true,
                ];
            }

            $provider = (string) ($validated['provider'] ?? 'manual');
            $reference = (string) $validated['reference'];
            $amount = (float) ($validated['amount'] ?? $invoice->amount_due);

            DB::table('subscription_payments')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'invoice_id' => $invoice->id,
                'provider' => $provider,
                'reference' => $reference,
                'status' => 'paid',
                'amount' => $amount,
                'paid_at' => now('UTC'),
                'idempotency_key' => (string) $request->header('X-Idempotency-Key', ''),
                'payload' => json_encode([
                    'request_id' => $request->header('X-Request-Id'),
                    'source' => 'manual_api',
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->where('id', $invoice->id)
                ->update([
                    'status' => 'paid',
                    'amount_paid' => $amount,
                    'paid_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);

            $stillOverdue = DB::table('invoices')
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now('UTC'))
                ->exists();

            if (! $stillOverdue) {
                DB::table('subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_current', true)
                    ->whereIn('status', ['past_due', 'suspended'])
                    ->update([
                        'status' => 'active',
                        'grace_ends_at' => null,
                        'updated_at' => now('UTC'),
                    ]);
            }

            return [
                'status' => 'ok',
                'invoice_id' => $invoice->id,
                'invoice_status' => 'paid',
                'already_paid' => false,
            ];
        }, 3);

        if ($idempotencyKey !== '') {
            DB::table('subscription_operations')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'operation' => 'mark_invoice_paid',
                'idempotency_key' => $idempotencyKey,
                'response_payload' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'created_at' => now('UTC'),
            ]);
        }

        Log::info('subscription.invoice.mark_paid', [
            'tenant_id' => (string) $tenant->id,
            'invoice_id' => $invoiceId,
            'provider' => (string) ($validated['provider'] ?? 'manual'),
            'reference' => (string) ($validated['reference'] ?? ''),
            'idempotency_key' => $idempotencyKey,
            'already_paid' => (bool) ($result['already_paid'] ?? false),
            'request_id' => (string) $request->header('X-Request-Id', ''),
            'source' => 'api',
        ]);

        return response()->json($result);
    }

    private function buildSimplePdf(array $lines): string
    {
        $escaped = array_map(static function (string $line): string {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        }, $lines);

        $content = "BT\n/F1 11 Tf\n56 770 Td\n";
        foreach ($escaped as $index => $line) {
            if ($index > 0) {
                $content .= "0 -14 Td\n";
            }
            $content .= "($line) Tj\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $offsets = [];
        $pdf = "%PDF-1.4\n";
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
