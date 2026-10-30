<?php

namespace App\Jobs;

use App\Imports\CatalogImport;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued catalog import: re-parses the stored upload so a large file
 * never depends on one HTTP request, then notifies the uploader with
 * the ok/skipped/error summary and deletes the workbook.
 */
class ImportCatalogJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $path  workbook path on the private `local` disk
     * @param  int  $userId  uploader to notify on completion
     */
    public function __construct(
        public string $path,
        public int $userId,
    ) {}

    public function handle(): void
    {
        try {
            $report = (new CatalogImport)(Storage::disk('local')->path($this->path));

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
                    // stub: the failure-report file lands here in #81
                    'failure_report_path' => null,
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

    private function notifyUploader(string $title, string $message, array $data): void
    {
        $user = User::find($this->userId);

        if ($user) {
            app(NotificationService::class)->notify($user, 'catalog_import', $title, $message, $data);
        }
    }
}
