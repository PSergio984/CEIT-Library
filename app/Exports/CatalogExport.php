<?php

namespace App\Exports;

use App\Imports\CatalogImport;
use App\Models\AcademicPaper;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Full catalog export in the staff workbook format: one sheet per
 * department, papers written back under the canonical headers so a
 * freshly exported file round-trips through CatalogImport — every
 * row's catalog code is already known and skipped as a duplicate.
 *
 * Papers whose department is not one of the three catalog sheets are
 * not exported (the catalog only has those sheets).
 */
class CatalogExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        $papers = AcademicPaper::with([
            'authors' => fn ($query) => $query->orderBy('academic_paper_authors.id'),
            'technicalAdviser:id,name',
            'researchAdviser:id,name',
            'dean:id,name',
        ])
            ->withCount('copies')
            ->orderBy('catalog_code')
            ->get()
            ->groupBy('department');

        return array_map(
            fn (string $name) => new CatalogSheetExport(
                $name,
                ($papers[$name] ?? collect())->map(fn (AcademicPaper $paper) => self::row($paper))->all(),
            ),
            CatalogImport::DEPARTMENT_SHEETS,
        );
    }

    /**
     * @return list<mixed>
     */
    private static function row(AcademicPaper $paper): array
    {
        // The sheet format stops at MEMBER 7; keep the first seven in
        // attach order. Re-import dedupes on catalog code, so the rest
        // are recoverable from the record itself.
        $members = $paper->authors->pluck('name')->take(7)->values()->all();

        return [
            $paper->publication_year,
            $paper->paper_type,
            $paper->catalog_code,
            $paper->title,
            $paper->copies_count,
            ...array_pad($members, 7, null),
            $paper->technicalAdviser?->name,
            $paper->researchAdviser?->name,
            $paper->department,
            $paper->dean?->name,
        ];
    }
}
