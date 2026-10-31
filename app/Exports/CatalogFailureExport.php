<?php

namespace App\Exports;

use App\Imports\CatalogImport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import failure report in the same staff-workbook format: each
 * department sheet holds the rows that failed to import, with two
 * appended columns identifying the source row and the reason. Fixes
 * can be made directly in this file and re-uploaded — it is already
 * a valid catalog workbook.
 */
class CatalogFailureExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, list<array{row: int, reason: string, values: array<string, mixed>}>>  $failures
     */
    public function __construct(private readonly array $failures) {}

    public function sheets(): array
    {
        return array_map(function (string $name) {
            $rows = array_map(fn (array $failure) => [
                $failure['values']['year'],
                $failure['values']['paper_type'],
                $failure['values']['catalog'],
                $failure['values']['title'],
                $failure['values']['copies'],
                ...array_pad(array_slice($failure['values']['members'], 0, 7), 7, null),
                $failure['values']['technical_adviser'],
                $failure['values']['research_adviser'],
                $failure['values']['department'],
                $failure['values']['dean'],
                $failure['row'],
                $failure['reason'],
            ], $this->failures[$name] ?? []);

            return new CatalogSheetExport($name, $rows, ['FAILED ROW', 'FAILURE REASON']);
        }, CatalogImport::DEPARTMENT_SHEETS);
    }
}
