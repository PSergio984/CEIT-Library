<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the stored import failure-report workbook linked from the
 * "catalog import complete" notification. Admin-only via the route's
 * manage-academic-papers middleware; the {file} segment is constrained
 * to a bare .xlsx filename so it cannot escape the failures directory.
 */
class CatalogImportFailureReportController extends Controller
{
    public function download(string $file): StreamedResponse
    {
        $path = 'catalog-imports/failures/'.$file;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'catalog-import-failures.xlsx');
    }
}
