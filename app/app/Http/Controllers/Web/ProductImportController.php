<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ProductImportService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function __construct(private readonly ProductImportService $imports)
    {
    }

    public function showImportForm()
    {
        return view('backoffice.product-import', $this->viewData());
    }

    public function handleImport(Request $request)
    {
        // kept for backward compatibility: act like uploadPreview
        return $this->uploadPreview($request);
    }

    /**
     * Upload file and show preview (first N rows).
     */
    public function uploadPreview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (! in_array($ext, ProductImportService::supportedExtensions(), true)) {
            return back()->withErrors(['file' => 'Tipe file tidak didukung. Gunakan CSV, TXT, XLS, atau XLSX.']);
        }

        $dir = storage_path('app/imports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid('products_import_') . '.' . $ext;
        $saved = $file->move($dir, $filename);
        if (! $saved) {
            return back()->withErrors(['file' => 'Gagal menyimpan file untuk diproses.']);
        }

        $path = $saved->getPathname();

        try {
            $preview = $this->imports->parseFile($path, $ext, 10);
        } catch (\Throwable $e) {
            @unlink($path);

            return back()->withErrors(['file' => 'Gagal membaca file: '.$e->getMessage()]);
        }

        if (($preview['headers'] ?? []) === []) {
            @unlink($path);

            return back()->withErrors(['file' => 'Header file tidak ditemukan. Pastikan baris pertama berisi nama kolom.']);
        }

        session(['product_import_tmp' => $path]);
        session(['product_import_original_name' => $file->getClientOriginalName()]);

        return view('backoffice.product-import-preview', $this->viewData([
            'headers' => $preview['headers'] ?? [],
            'rows' => $preview['rows'] ?? [],
            'totalRows' => $preview['total_rows'] ?? count($preview['rows'] ?? []),
            'originalName' => $file->getClientOriginalName(),
        ]));
    }

    /**
     * Process stored import file (confirmed by user).
     */
    public function processImport(Request $request)
    {
        $tenantId = TenantContext::id() ?: $request->user()?->tenant_id;
        if (! $tenantId) {
            return redirect('/backoffice/products/import')->withErrors(['file' => 'Tenant tidak ditemukan.']);
        }

        $tmp = session('product_import_tmp');
        if (! $tmp || ! file_exists($tmp)) {
            return redirect('/backoffice/products/import')->withErrors(['file' => 'File import tidak ditemukan. Silakan upload ulang.']);
        }

        // read mapping from request
        $mapping = $request->input('mapping', []);

        // validate mapping: require at least 'name' mapped
        $mappedTargets = array_values($mapping);
        if (! in_array('name', $mappedTargets, true)) {
            return redirect('/backoffice/products/import')->withErrors(['mapping' => 'Kolom "name" harus dipetakan.']);
        }

        try {
            $summary = $this->imports->importProducts((string) $tenantId, $tmp, $mapping, auth()->id() ?? null);
        } catch (\Throwable $exception) {
            return redirect('/backoffice/products/import')->withErrors(['file' => 'Import gagal: '.$exception->getMessage()]);
        } finally {
            @unlink($tmp);
            session()->forget(['product_import_tmp', 'product_import_original_name']);
        }

        $message = sprintf(
            'Import selesai: %d dibuat, %d diperbarui, %d dilewati dari %d baris.',
            $summary['created'],
            $summary['updated'],
            $summary['skipped'],
            $summary['total_rows']
        );

        return redirect('/backoffice/products/import')
            ->with('status', $message)
            ->with('import_errors', array_slice($summary['errors'], 0, 8));
    }

    private function viewData(array $data = []): array
    {
        $tenantId = TenantContext::id() ?: auth()->user()?->tenant_id;

        return array_merge([
            'tenantId' => $tenantId,
            'showMenu' => true,
            'fieldLabels' => ProductImportService::fieldLabels(),
            'supportedExtensions' => ProductImportService::supportedExtensions(),
        ], $data);
    }
}
