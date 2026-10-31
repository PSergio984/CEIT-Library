<?php

namespace App\Imports;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Imports one department sheet of the staff catalog workbook.
 *
 * Header positions drift between sheets, so data columns are resolved by
 * normalized header name (trim + collapsed whitespace + case-insensitive);
 * the year and paper-type columns keep positional fallbacks (A and B)
 * because some sheets leave those two headers blank. `MEMBER *` headers
 * may repeat — every one is an author column.
 *
 * Rows are additive and idempotent: existing or repeated catalog codes
 * are skipped, never updated. Per-row failures are collected in the
 * report instead of aborting the sheet.
 */
class CatalogSheetImport implements ToArray
{
    /** Canonical paper_type values keyed by their lowercase sheet spelling. */
    private const PAPER_TYPES = [
        'thesis' => 'Thesis',
        'capstone' => 'Capstone',
        'feasib' => 'Feasib',
        'research' => 'Research',
        'practicum' => 'Practicum',
        'report' => 'Report',
    ];

    /** @var array<string, true> */
    private array $catalogCodes;

    /** @var array<class-string<Model>, array<string, int>> normalized name => id, per lookup table */
    private array $peopleIds = [];

    /** @var array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>} */
    private array $report = [
        'imported' => 0,
        'errors' => 0,
        'duplicates' => 0,
        'generated_codes' => 0,
        'error' => null,
        'messages' => [],
    ];

    /** @var list<array{row: int, reason: string, values: array<string, mixed>}> */
    private array $failures = [];

    /**
     * @param  array<string, true>  $catalogCodes  codes already in the
     *                                             database (keys lowercased), shared by reference across all sheet
     *                                             imports so codes imported or generated earlier in the run register
     *                                             as duplicates
     */
    public function __construct(
        private readonly string $department,
        array &$catalogCodes,
    ) {
        $this->catalogCodes = &$catalogCodes;
    }

    /**
     * @return array{imported: int, errors: int, duplicates: int, generated_codes: int, error: ?string, messages: list<array{row: int|null, type: string, message: string}>}
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * Rows that failed validation, captured with their cleaned cell
     * values so the failure report can rewrite them in the export
     * format (#81).
     *
     * @return list<array{row: int, reason: string, values: array<string, mixed>}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @param  array<int, array<array-key, mixed>>  $rows
     */
    public function array(array $rows): void
    {
        $columns = $this->mapColumns($rows[0] ?? []);

        $missing = [];
        if ($columns['title'] === null) {
            $missing[] = 'APPROVED OFFICIAL LONG TITLE';
        }
        if ($columns['members'] === []) {
            $missing[] = 'MEMBER *';
        }
        if ($missing !== []) {
            $this->report['error'] = sprintf(
                "Sheet '%s' is missing required header(s): %s",
                $this->department,
                implode(', ', $missing),
            );

            return;
        }

        if ($columns['catalog'] === null) {
            $this->warn(null, 'No catalog column found; catalog codes will be generated for every row');
        }

        foreach (array_slice($rows, 1) as $index => $row) {
            $this->importRow($row, $index + 2, $columns);
        }
    }

    /**
     * Map each normalized header to its column index. First match wins for
     * scalar columns; every MEMBER * column is collected.
     *
     * @param  array<array-key, mixed>  $headerRow
     * @return array{year: int|null, paper_type: int|null, catalog: int|null, title: int|null, copies: int|null, members: list<int>, technical_adviser: int|null, research_adviser: int|null, department: int|null, dean: int|null}
     */
    private function mapColumns(array $headerRow): array
    {
        $columns = [
            'year' => null,
            'paper_type' => null,
            'catalog' => null,
            'title' => null,
            'copies' => null,
            'members' => [],
            'technical_adviser' => null,
            'research_adviser' => null,
            'department' => null,
            'dean' => null,
        ];

        foreach ($headerRow as $i => $raw) {
            $header = self::normalizeHeader($raw);

            if (str_starts_with($header, 'MEMBER')) {
                $columns['members'][] = $i;

                continue;
            }

            $target = match (true) {
                in_array($header, ['TYPE OF ACADEMIC PAPER', 'PAPER TYPE']) => 'paper_type',
                str_starts_with($header, 'CATALOG') => 'catalog',
                in_array($header, ['APPROVED OFFICIAL LONG TITLE', 'TITLE']) => 'title',
                $header === 'COPIES' => 'copies',
                $header === 'TECHNICAL ADVISER' => 'technical_adviser',
                in_array($header, ['RESEARCH PROJECT ADVISER', 'RESEARCH ADVISER']) => 'research_adviser',
                $header === 'DEPARTMENT' => 'department',
                $header === 'DEAN' => 'dean',
                in_array($header, ['YEAR', 'PUBLICATION YEAR']) => 'year',
                default => null,
            };

            if ($target !== null) {
                $columns[$target] ??= $i;
            }
        }

        // Sheets that leave the first two headers blank still carry the
        // year and paper type in columns A and B.
        $columns['year'] ??= 0;
        $columns['paper_type'] ??= 1;

        return $columns;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  array<string, int|list<int>|null>  $columns
     */
    private function importRow(array $row, int $rowNumber, array $columns): void
    {
        if (! array_filter($row, fn ($value) => self::clean($value) !== '')) {
            return; // fully blank spacer row
        }

        $title = self::clean($this->cellAt($row, $columns['title']));
        if ($title === '') {
            $this->fail($rowNumber, 'Missing title', $row, $columns);

            return;
        }

        $typeRaw = self::clean($this->cellAt($row, $columns['paper_type']));
        $paperType = self::PAPER_TYPES[mb_strtolower($typeRaw)] ?? null;
        if ($paperType === null) {
            $this->fail($rowNumber, "Unknown paper type '{$typeRaw}'", $row, $columns);

            return;
        }

        $year = self::clean($this->cellAt($row, $columns['year']));
        if (! ctype_digit($year) || (int) $year < 1900 || (int) $year > 2100) {
            $this->fail($rowNumber, "Missing or invalid publication year '{$year}'", $row, $columns);

            return;
        }

        $copiesRaw = $this->cellAt($row, $columns['copies']);
        $copies = self::clean($copiesRaw);
        if ($copies !== '' && ! ctype_digit($copies)) {
            $this->fail($rowNumber, "Invalid copies value '{$copies}'", $row, $columns);

            return;
        }

        $catalog = self::clean($this->cellAt($row, $columns['catalog']));
        if ($catalog !== '' && isset($this->catalogCodes[mb_strtolower($catalog)])) {
            $this->report['duplicates']++;
            $this->message($rowNumber, 'duplicate', "Catalog code '{$catalog}' already exists");

            return;
        }

        $department = self::clean($this->cellAt($row, $columns['department']));
        if ($department !== '' && mb_strtolower($department) !== mb_strtolower($this->department)) {
            $this->warn($rowNumber, "Department column '{$department}' does not match sheet '{$this->department}'; using the sheet name");
        }

        try {
            DB::transaction(function () use ($row, $columns, $title, $paperType, $year, $copies, $catalog): void {
                $paper = AcademicPaper::create([
                    'catalog_code' => $catalog === '' ? null : $catalog,
                    'title' => $title,
                    'publication_year' => (int) $year,
                    'paper_type' => $paperType,
                    'research_adviser_id' => $this->personId(ResearchAdviser::class, $this->cellAt($row, $columns['research_adviser'])),
                    'technical_adviser_id' => $this->personId(TechnicalAdviser::class, $this->cellAt($row, $columns['technical_adviser'])),
                    'department' => $this->department,
                    'dean_id' => $this->personId(Dean::class, $this->cellAt($row, $columns['dean'])),
                ]);

                $authorIds = collect($columns['members'])
                    ->map(fn ($i) => $this->personId(Author::class, $this->cellAt($row, $i)))
                    ->filter()
                    ->unique()
                    ->all();

                if ($authorIds !== []) {
                    $paper->authors()->attach($authorIds);
                }

                $copyNumber = ((int) $paper->copies()->max('copy_number')) + 1;
                for ($i = 0; $i < (int) $copies; $i++) {
                    $paper->copies()->create([
                        'copy_number' => $copyNumber + $i,
                        'status' => 'Available',
                    ]);
                }

                $this->catalogCodes[mb_strtolower($paper->catalog_code)] = true;
                $this->report['imported']++;
                if ($catalog === '') {
                    $this->report['generated_codes']++;
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Catalog import row failed', [
                'sheet' => $this->department,
                'row' => $rowNumber,
                'error' => $e->getMessage(),
            ]);
            $this->fail($rowNumber, 'Database error while importing row', $row, $columns);
        }
    }

    /**
     * Find-or-create a lookup-table person by normalized name. The
     * comparison trims, collapses whitespace and ignores case; the stored
     * display name keeps the sheet spelling (honorifics included).
     *
     * @param  class-string<Model>  $model
     */
    private function personId(string $model, mixed $raw): ?int
    {
        $name = self::clean($raw);
        if ($name === '') {
            return null;
        }

        $this->peopleIds[$model] ??= $model::query()
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $existing) => [mb_strtolower(self::clean($existing)) => (int) $id])
            ->all();

        return $this->peopleIds[$model][mb_strtolower($name)]
            ??= (int) $model::create(['name' => $name])->getKey();
    }

    private function cellAt(array $row, mixed $column): mixed
    {
        return is_int($column) ? ($row[$column] ?? null) : null;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  array<string, int|list<int>|null>  $columns
     */
    private function fail(int $rowNumber, string $reason, array $row, array $columns): void
    {
        $this->report['errors']++;
        $this->message($rowNumber, 'error', $reason);
        $this->failures[] = [
            'row' => $rowNumber,
            'reason' => $reason,
            'values' => [
                'year' => self::clean($this->cellAt($row, $columns['year'])),
                'paper_type' => self::clean($this->cellAt($row, $columns['paper_type'])),
                'catalog' => self::clean($this->cellAt($row, $columns['catalog'])),
                'title' => self::clean($this->cellAt($row, $columns['title'])),
                'copies' => self::clean($this->cellAt($row, $columns['copies'])),
                'members' => array_map(
                    fn ($i) => self::clean($this->cellAt($row, $i)),
                    $columns['members'],
                ),
                'technical_adviser' => self::clean($this->cellAt($row, $columns['technical_adviser'])),
                'research_adviser' => self::clean($this->cellAt($row, $columns['research_adviser'])),
                'department' => self::clean($this->cellAt($row, $columns['department'])),
                'dean' => self::clean($this->cellAt($row, $columns['dean'])),
            ],
        ];
    }

    private function warn(?int $row, string $message): void
    {
        $this->message($row, 'warning', $message);
    }

    private function message(?int $row, string $type, string $message): void
    {
        $this->report['messages'][] = [
            'row' => $row,
            'type' => $type,
            'message' => $message,
        ];
    }

    /** Trim and collapse internal whitespace; null-safe. */
    private static function clean(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) ($value ?? '')));
    }

    private static function normalizeHeader(mixed $value): string
    {
        return mb_strtoupper(self::clean($value));
    }
}
