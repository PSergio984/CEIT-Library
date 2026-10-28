# Excel import/export libraries for the catalog pipeline

Research for wayfinder ticket #70. Question: which library to use for reading and
writing `CEIT ACADEMIC PAPER.xlsx` (4 department sheets, ~4k total rows, header row,
member/adviser columns). Compared: `maatwebsite/excel` (Laravel Excel),
`openspout/openspout`, and raw `phpoffice/phpspreadsheet`.

## Target workbook

Verified by unpacking the file at the repo root (`CEIT ACADEMIC PAPER.xlsx`):

- 4 sheets: `Electrical Engineering`, `Civil Engineering`, `Information
  Technology`, `Documents` (a non-department sheet the importer must tolerate).
- Header columns: `TYPE OF ACADEMIC PAPER`, `CATALOG NUMBER`, `APPROVED OFFICIAL
  LONG TITLE`, `COPIES`, `MEMBER 1`–`MEMBER 7`, `TECHNICAL ADVISER`, `RESEARCH
  PROJECT ADVISER`, `DEPARTMENT`, `DEAN` (from `xl/sharedStrings.xml`).
- ~4k rows total; worst-case ~56k populated cells (4k x 14 cols). Small by
  spreadsheet-library standards.

## Project constraints

`composer.json`: `php: ^8.4`, `laravel/framework: ^13.0`, Livewire 4.

## Candidates (state as of Oct 2026, Packagist p2 + GitHub API)

| | maatwebsite/excel | openspout/openspout | phpoffice/phpspreadsheet |
|---|---|---|---|
| Latest | 4.0.6 (2026-10-23) | v5.13.0 (2026-10-27) | 5.10.0 (2026-09-17) |
| PHP | ^8.3 | ~8.4.0 \|\| ~8.5.0 \|\| ~8.6.0 | ^8.1/^8.2 |
| Illuminate | ^12 \|\| ^13 (also 3.1.70: 5.8–^13) | none (framework-agnostic) | none |
| Built on | PhpSpreadsheet ^5.9 | own streaming engine | itself |
| Maintenance | active; repo `SpartnerNL/Laravel-Excel`, 12.7k stars, pushed same day as release | active community fork of abandoned `box/spout`; 1.3k stars | active; 14k stars, 108 open issues |
| Memory model | whole workbook in memory per chunk (PhpSpreadsheet reader); `WithChunkReading` + `WithBatchInserts` bound DB-side memory; queued imports/exports for web-timeout safety | streaming both ways, "less than 3MB" per README | whole workbook in memory by default, ~1kB/cell (1.6kB on 64-bit); PSR-16 cell caching and `IReadFilter` partial reads available |

## Requirements fit

### 1. Multi-sheet read

- **maatwebsite/excel**: `WithMultipleSheets`; `sheets()` returns an array keyed
  by sheet index or by sheet name, so the three department sheets map cleanly
  and the `Documents` sheet is skipped (undefined sheets are ignored by default;
  `SkipsUnknownSheets` + `onUnknownSheet()` handles declared-but-missing sheets).
- **openspout**: `getSheetIterator()` over sheets, then `getRowIterator()`; name
  matching is hand-rolled.
- **phpspreadsheet**: `getSheetNames()` / `getSheetByName()`; hand-rolled.

### 2. Row-level validation with collectable errors

- **maatwebsite/excel**: first-class. `WithValidation::rules()` accepts normal
  Laravel rules keyed by column index or heading name (with `WithHeadingRow`),
  `customValidationMessages()`, `customValidationAttributes()`, callback rules.
  `SkipsOnFailure` collects `Failure` objects (`->row()`, `->attribute()`,
  `->errors()`, `->values()`) so one bad row does not fail the file;
  `SkipsOnError` does the same for `Throwable`s.
- **openspout**: no framework; you validate each streamed row yourself and
  accumulate errors in an array. Naturally collectable (streaming = row-at-a-time),
  but everything — heading mapping, failure objects, import report — is DIY.
- **phpspreadsheet**: same DIY position as openspout, except rows come back as
  `Cell` objects (`rangeToArray`/`toArray` or iterators).

### 3. Styled multi-sheet writes (blank template + data export)

- **maatwebsite/excel**: `WithMultipleSheets` for exports too (array of per-sheet
  export objects, each with its own `WithTitle`, `WithHeadings`, `WithStyles`,
  `WithColumnWidths`/`ShouldAutoSize`, `WithStartCell`, `WithEvents`). Full
  PhpSpreadsheet styling is reachable from `WithStyles`. `WithExportTemplate`
  writes into a pre-formatted .xlsx — directly matches the "fill a blank
  template" requirement.
- **openspout**: streaming `Writer` with `Style` entities (fonts, backgrounds,
  alignment, borders), `setColumnWidth`/`setColumnWidthForRange` at options or
  sheet level, `mergeCells`, autofilter, sheet view, sheet creation on demand.
  Enough for a template/export, but a template file can't be loaded for
  modification — the writer only produces new files, so the "blank template"
  must be generated in code.
- **phpspreadsheet**: the full style API (everything Excel supports), can also
  load the existing workbook and append rows to it. Most verbose of the three.

## Notes / risks

- Laravel Excel 4.x is new (4.0.0 shipped 2026-08-13); 3.1.70 also supports
  Illuminate ^13, so there is a fallback line if 4.x surprises appear.
- OpenSpout v5.x pins PHP to `~8.4.0 || ~8.5.0 || ~8.6.0` — compatible today,
  but its patch-level PHP constraints have historically forced lockstep
  upgrades on minor PHP bumps.
- At ~4k rows memory is a non-issue for all three (~tens of MB worst case for
  the in-memory options). It only matters as headroom if the catalog grows an
  order of magnitude; Laravel Excel's `WithChunkReading`/queued imports cover
  that without switching libraries.

## Recommendation

**`maatwebsite/excel` 4.x** (`composer require maatwebsite/excel`).

It is the only candidate that meets all three requirements without
hand-rolled infrastructure: `WithMultipleSheets` (name-keyed, ignores the
`Documents` sheet), `WithValidation` + `SkipsOnFailure` gives collectable
per-row failures for free, and `WithExportTemplate` + `WithStyles` cover the
styled template and data export. Declared compatible with Illuminate ^13 /
PHP ^8.3, actively maintained, and sits on PhpSpreadsheet ^5.9 so nothing in
the styling API is out of reach. At 4k rows its memory profile is fine, and
chunk reading + queued imports are built-in if the file grows.

OpenSpout is the better pick only if memory ever becomes the binding
constraint or a dependency-light import is wanted — it has no Laravel
integration and no validation layer. Raw PhpSpreadsheet is what Laravel Excel
already wraps; using it directly buys nothing here except boilerplate.

## Sources

- Packagist p2 metadata: `repo.packagist.org/p2/maatwebsite/excel.json`,
  `.../openspout/openspout.json`, `.../phpoffice/phpspreadsheet.json`
- GitHub API: `repos/SpartnerNL/Laravel-Excel`, `repos/openspout/openspout`,
  `repos/PHPOffice/PhpSpreadsheet` (pushed_at, releases, issue counts)
- `src/Concerns/` listing, `SpartnerNL/Laravel-Excel` (concern names cited above)
- Laravel Excel 4.x docs: docs.laravel-excel.com/4.x/imports/validation,
  /imports/multiple-sheets, /exports/presentation, /exports/template
- OpenSpout docs: github.com/openspout/openspout `docs/documentation.md`
  (sheets, styles, column widths, mergeCells) and README ("less than 3MB")
- PhpSpreadsheet `docs/topics/memory_saving.md` (~1k/1.6kB per cell, PSR-16
  cell caching)
