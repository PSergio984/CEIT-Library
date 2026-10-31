<?php

namespace App\Imports;

use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;
use App\Support\AiIndexSync;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports the staff catalog workbook: one sheet per department, additive
 * and idempotent. `Documents` and other unmapped sheets are ignored.
 *
 * Usage: `$report = (new CatalogImport)($pathToXlsx);`
 *
 * All writes run in one transaction with AI index dispatches suppressed;
 * one AiIndexRebuildJob is dispatched after the import commits when at
 * least one row was imported.
 * Returns a per-sheet report:
 * `{sheets: {<sheet name>: {imported, errors, duplicates, generated_codes,
 * error, messages: [{row, type: error|duplicate|warning, message}]}}}`.
 */
class CatalogImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    /** Department sheets of the staff workbook, keyed to app department names. */
    public const DEPARTMENT_SHEETS = [
        'Electrical Engineering',
        'Civil Engineering',
        'Information Technology',
    ];

    /** @var array<string, true> lowercased catalog codes taken, in the DB or this run */
    private array $catalogCodes = [];

    /** @var array<string, CatalogSheetImport> */
    private array $sheetImports = [];

    /** @var list<string> */
    private array $missingSheets = [];

    /**
     * @return array{sheets: array<string, array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>}>}
     */
    public function __invoke(string $path): array
    {
        $report = $this->run($path);

        if (collect($report['sheets'])->sum('imported') > 0) {
            AiIndexRebuildJob::dispatch()->delay(now()->addSeconds(60));
        }

        return $report;
    }

    /**
     * Dry run for the preview step: the identical pipeline inside an
     * outer transaction that always rolls back, so the report is the
     * real thing but nothing commits — and no index rebuild is
     * dispatched since nothing was written.
     *
     * @return array{sheets: array<string, array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>}>}
     */
    public function preview(string $path): array
    {
        // A ~3k-row dry run inside one request can outrun the default
        // execution cap; the held write transaction (a DB-wide writer
        // lock under SQLite) for that window is an accepted admin-only
        // cost.
        set_time_limit(300);

        DB::beginTransaction();

        try {
            return $this->run($path);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /**
     * @return array{sheets: array<string, array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>}>}
     */
    private function run(string $path): array
    {
        AiIndexSync::withoutDispatches(function () use ($path): void {
            DB::transaction(function () use ($path): void {
                $this->catalogCodes = array_fill_keys(
                    AcademicPaper::query()->pluck('catalog_code')
                        ->map(fn ($code) => mb_strtolower((string) $code))
                        ->all(),
                    true,
                );

                Excel::import($this, $path);
            });
        });

        return $this->report();
    }

    /**
     * @return array<string, CatalogSheetImport>
     */
    public function sheets(): array
    {
        if ($this->sheetImports === []) {
            foreach (self::DEPARTMENT_SHEETS as $name) {
                $this->sheetImports[$name] = new CatalogSheetImport($name, $this->catalogCodes);
            }
        }

        return $this->sheetImports;
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        $this->missingSheets[] = (string) $sheetName;
    }

    /**
     * Rows that failed validation, keyed by department sheet, in the
     * canonical shape CatalogFailureExport writes back out (#81).
     *
     * @return array<string, list<array{row: int, reason: string, values: array<string, mixed>}>>
     */
    public function failures(): array
    {
        $failures = [];

        foreach (self::DEPARTMENT_SHEETS as $name) {
            $failures[$name] = isset($this->sheetImports[$name]) && ! in_array($name, $this->missingSheets)
                ? $this->sheetImports[$name]->failures()
                : [];
        }

        return $failures;
    }

    /**
     * @return array{sheets: array<string, array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>}>}
     */
    private function report(): array
    {
        $sheets = [];

        foreach (self::DEPARTMENT_SHEETS as $name) {
            $sheets[$name] = isset($this->sheetImports[$name]) && ! in_array($name, $this->missingSheets)
                ? $this->sheetImports[$name]->report()
                : [
                    'imported' => 0,
                    'errors' => 0,
                    'duplicates' => 0,
                    'generated_codes' => 0,
                    'error' => "Sheet '{$name}' was not found in the workbook",
                    'messages' => [],
                ];
        }

        return ['sheets' => $sheets];
    }
}
