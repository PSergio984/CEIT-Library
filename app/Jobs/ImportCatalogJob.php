<?php

namespace App\Jobs;

use App\Exports\CatalogFailureExport;
use App\Imports\CatalogImport;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Queued catalog import: re-parses the stored upload so a large file
 * never depends on one HTTP request, then notifies the uploader with
 * the ok/skipped/error summary and deletes the workbook.
 */
class ImportCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // A killed run must not be retried silently: the importer commits
    // in a single transaction, so a retry would re-import every row
    // as a reported duplicate.
    public int $tries = 1;

    // The ~3k-row staff workbook far outruns the 60s worker default.
    public int $timeout = 600;

    /**
     * @param  string  $path  workbook path on the private `local` disk
     * @param  int  $userId  uploader to notify on completion
     */
    public function __construct(
        public string $path,
        public int $userId,
    ) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->path;
    }

    public function handle(): void
    {
        try {
            $import = new CatalogImport;
            $report = $import(Storage::disk('local')->path($this->path));

            $sheets = collect($report['sheets']);
            $imported = (int) $sheets->sum('imported');
            $skipped = (int) $sheets->sum('duplicates');
            $errors = (int) $sheets->sum('errors');

            $this->notifyUploader(
                'Catalog import complete',
                "{$imported} imported, {$skipped} skipped, {$errors} errors.",
                [
                    'sheets' => $sheets->map(fn (array $s) => [
                        'imported' => $s['imported'],
                        'errors' => $s['errors'],
                        'duplicates' => $s['duplicates'],
                        'generated_codes' => $s['generated_codes'],
                        'error' => $s['error'],
                    ])->all(),
                    'failure_report_path' => $this->storeFailureReport($import),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Catalog import job failed', [
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);

            $this->notifyUploader(
                'Catalog import failed',
                'The uploaded workbook could not be processed. Check the file and try again.',
                ['failure_report_path' => null],
            );
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }

    /**
     * Persist the failed rows as a downloadable catalog-format workbook
     * on the private disk; null when nothing failed. The file stays
     * under catalog-imports/failures for the admin-gated download route.
     */
    private function storeFailureReport(CatalogImport $import): ?string
    {
        $failures = $import->failures();

        if (array_sum(array_map('count', $failures)) === 0) {
            return null;
        }

        $path = 'catalog-imports/failures/'.Str::uuid().'.xlsx';
        Excel::store(new CatalogFailureExport($failures), $path, 'local');

        return $path;
    }

    /**
     * Reached when the worker kills the job (timeout, crash) — handle()
     * never got to clean up or report, so do both here.
     */
    public function failed(?\Throwable $e): void
    {
        Storage::disk('local')->delete($this->path);

        $this->notifyUploader(
            'Catalog import failed',
            'The import was interrupted before it finished. Upload the workbook again to retry.',
            ['failure_report_path' => null],
        );
    }

    private function notifyUploader(string $title, string $message, array $data): void
    {
        $user = User::find($this->userId);

        if ($user) {
            app(NotificationService::class)->notify($user, 'catalog_import', $title, $message, $data);
        }
    }
}
