<?php

namespace App\Exports;

use App\Imports\CatalogImport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Blank staff catalog template: exactly the three department sheets,
 * canonical headers only — no example rows and no Documents sheet.
 */
class CatalogTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return array_map(
            fn (string $name) => new CatalogSheetExport($name, []),
            CatalogImport::DEPARTMENT_SHEETS,
        );
    }
}
