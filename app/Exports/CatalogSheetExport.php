<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One department sheet of a catalog workbook: the canonical staff
 * header row followed by pre-built data rows. Extra trailing columns
 * (e.g. the failure report's FAILED ROW / FAILURE REASON) can be
 * appended after DEAN.
 */
class CatalogSheetExport implements FromArray, WithTitle
{
    /** Canonical staff workbook headers, in column order. */
    public const HEADERS = [
        'YEAR',
        'TYPE OF ACADEMIC PAPER',
        'CATALOG NUMBER',
        'APPROVED OFFICIAL LONG TITLE',
        'COPIES',
        'MEMBER 1',
        'MEMBER 2',
        'MEMBER 3',
        'MEMBER 4',
        'MEMBER 5',
        'MEMBER 6',
        'MEMBER 7',
        'TECHNICAL ADVISER',
        'RESEARCH PROJECT ADVISER',
        'DEPARTMENT',
        'DEAN',
    ];

    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<string>  $trailingHeaders
     */
    public function __construct(
        private readonly string $sheetName,
        private readonly array $rows,
        private readonly array $trailingHeaders = [],
    ) {}

    public function array(): array
    {
        return [
            [...self::HEADERS, ...$this->trailingHeaders],
            ...array_map(
                fn (array $row) => array_map(self::escapeCell(...), $row),
                $this->rows,
            ),
        ];
    }

    /**
     * A stored value beginning with =, +, - or @ opens as a live
     * formula in spreadsheet apps; a leading quote keeps it inert text.
     */
    private static function escapeCell(mixed $value): mixed
    {
        if (is_string($value) && in_array(mb_substr($value, 0, 1), ['=', '+', '-', '@'], true)) {
            return "'{$value}";
        }

        return $value;
    }

    public function title(): string
    {
        return $this->sheetName;
    }
}
