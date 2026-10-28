<?php

namespace Tests\Feature;

use App\Imports\CatalogImport;
use App\Jobs\AiIndexRebuildImmediateJob;
use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;
use App\Models\Inventory;
use App\Models\TechnicalAdviser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Write a workbook shaped like the staff catalog file. Keys are sheet
     * names; each row is a 0-indexed list of cell values (row 0 = headers).
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    private function writeWorkbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;
            $sheet->setTitle($name);

            foreach ($rows as $r => $row) {
                foreach ($row as $c => $value) {
                    if ($value !== null) {
                        $sheet->setCellValue([$c + 1, $r + 1], $value);
                    }
                }
            }
        }

        $path = sys_get_temp_dir().'/catalog-import-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * The staff workbook format: per-department sheets with drifting
     * headers plus a `Documents` sheet that must be ignored.
     */
    private function staffWorkbook(): string
    {
        return $this->writeWorkbook([
            // Straightforward variant: YEAR/TYPE headers, CATALOG NUMBER,
            // COPIES in column E.
            'Electrical Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1', 'MEMBER 2', 'TECHNICAL ADVISER', 'RESEARCH PROJECT ADVISER', 'DEPARTMENT', 'DEAN'],
                [2024, 'THESIS', 'CEIT-EE-24-01', 'Smart Grid Monitoring', 2, 'Dela Cruz, Juan', 'Santos, Maria', 'ENGR. REYES', 'PROF. LIM', 'ELECTRICAL ENGINEERING', 'DEAN AQUINO'],
                [2024, 'CAPSTONE', 'CEIT-EE-24-02', 'IoT Power Meter', 0, 'Garcia, Ana', null, 'ENGR. REYES', 'PROF. LIM', 'ELECTRICAL ENGINEERING', 'DEAN AQUINO'],
            ],
            // Blank headers on the year/type columns (positional A/B),
            // `CATALOG ` with trailing space, COPIES in column F, two bare
            // `MEMBER` columns.
            'Civil Engineering' => [
                [null, null, 'CATALOG ', 'APPROVED OFFICIAL LONG TITLE', 'DEPARTMENT', 'COPIES', 'MEMBER', 'MEMBER', 'TECHNICAL ADVISER', 'RESEARCH PROJECT ADVISER', 'DEAN'],
                [2023, 'RESEARCH', 'CEIT-CE-23-01', 'Concrete Mix Study', 'CIVIL ENGINEERING', 1, 'Torres, Jose', 'Ramos, Liza', 'ENGR. DY', 'DR. SY', 'DEAN AQUINO'],
                [2025, 'THESIS', 'CEIT-CE-25-01', 'Bridge Load Analysis', 'CIVIL ENGINEERING', 3, 'Torres, Jose', null, 'ENGR. DY', 'DR. SY', 'DEAN AQUINO'],
            ],
            // CATALOG CODE variant, a blank code (generated), an in-file
            // duplicate, an unknown paper type, a department mismatch, a
            // missing year, a missing title, and a blank spacer row.
            'Information Technology' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG CODE', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1', 'TECHNICAL ADVISER', 'RESEARCH PROJECT ADVISER', 'DEPARTMENT', 'DEAN'],
                [2025, 'THESIS', 'CEIT-IT-25-01', 'Library Kiosk System', 1, 'Engr. Santos', 'engr. reyes', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
                [2026, 'CAPSTONE', null, 'Capstone No Code', 2, 'Lee, Kevin', 'ENGR. REYES', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
                [2025, 'THESIS', 'CEIT-EE-24-01', 'Duplicate Code Paper', 1, 'Ng, Pat', 'ENGR. REYES', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
                [2025, 'WIDGETS', 'CEIT-IT-25-99', 'Bad Type Row', 1, 'Xx, Yy', 'ENGR. REYES', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
                [2025, 'THESIS', 'CEIT-IT-25-02', 'Department Mismatch Row', 1, 'Oz, Amy', 'ENGR. REYES', 'PROF. LIM', 'CIVIL ENGINEERING', 'DEAN AQUINO'],
                [null, 'THESIS', 'CEIT-IT-25-03', 'Missing Year Row', 1, 'Aa, Bb', 'ENGR. REYES', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
                [null, null, null, null, null, null, null, null, null, null],
                [2025, 'THESIS', 'CEIT-IT-25-04', '', 1, 'Cc, Dd', 'ENGR. REYES', 'PROF. LIM', 'INFORMATION TECHNOLOGY', 'DEAN AQUINO'],
            ],
            'Documents' => [
                ['DOC ID', 'FILENAME'],
                ['DOC-1', 'minutes.pdf'],
            ],
        ]);
    }

    #[Test]
    public function it_imports_the_staff_workbook_per_sheet_and_reports_everything(): void
    {
        $report = (new CatalogImport)($this->staffWorkbook());

        // --- Report shape and per-sheet counts
        $this->assertSame(
            ['Electrical Engineering', 'Civil Engineering', 'Information Technology'],
            array_keys($report['sheets'])
        );

        $ee = $report['sheets']['Electrical Engineering'];
        $this->assertSame(2, $ee['imported']);
        $this->assertSame(0, $ee['errors']);
        $this->assertSame(0, $ee['duplicates']);
        $this->assertSame(0, $ee['generated_codes']);
        $this->assertNull($ee['error']);

        $ce = $report['sheets']['Civil Engineering'];
        $this->assertSame(2, $ce['imported']);
        $this->assertSame(0, $ce['errors']);
        $this->assertSame(0, $ce['duplicates']);

        $it = $report['sheets']['Information Technology'];
        $this->assertSame(3, $it['imported']);
        $this->assertSame(1, $it['generated_codes']);
        $this->assertSame(1, $it['duplicates']);
        $this->assertSame(3, $it['errors']);

        $types = collect($it['messages'])->groupBy('type');
        $this->assertCount(1, $types['duplicate']);
        $this->assertCount(1, $types['warning']);
        $this->assertCount(3, $types['error']);
        $this->assertStringContainsString('CEIT-EE-24-01', $types['duplicate'][0]['message']);
        $this->assertStringContainsString('CIVIL ENGINEERING', $types['warning'][0]['message']);
        $this->assertSame(6, $types['warning'][0]['row']); // actual spreadsheet row

        $this->assertArrayNotHasKey('Documents', $report['sheets']);

        // --- Database state
        $this->assertDatabaseCount('academic_papers', 7);
        $this->assertDatabaseHas('academic_papers', [
            'catalog_code' => 'CEIT-EE-24-01',
            'title' => 'Smart Grid Monitoring',
            'publication_year' => 2024,
            'paper_type' => 'Thesis',
            'department' => 'Electrical Engineering',
        ]);
        $this->assertDatabaseHas('academic_papers', [
            'catalog_code' => 'CEIT-CE-23-01',
            'paper_type' => 'Research',
            'department' => 'Civil Engineering',
        ]);
        $this->assertDatabaseHas('academic_papers', [
            'catalog_code' => 'CEIT-IT-26-01', // generated for the blank code
            'title' => 'Capstone No Code',
            'paper_type' => 'Capstone',
            'department' => 'Information Technology',
        ]);

        // Department comes from the sheet name, not the DEPARTMENT column.
        $this->assertDatabaseHas('academic_papers', [
            'catalog_code' => 'CEIT-IT-25-02',
            'department' => 'Information Technology',
        ]);

        // Copies: COPIES count becomes that many Available inventory rows.
        $paper = AcademicPaper::where('catalog_code', 'CEIT-EE-24-01')->firstOrFail();
        $this->assertSame(2, $paper->copies()->count());
        $this->assertDatabaseHas('inventories', [
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
            'status' => 'Available',
        ]);
        $this->assertDatabaseHas('inventories', [
            'academic_paper_id' => $paper->id,
            'copy_number' => 2,
            'status' => 'Available',
        ]);

        // Zero copies still imports the paper.
        $this->assertSame(0, AcademicPaper::where('catalog_code', 'CEIT-EE-24-02')->firstOrFail()->copies()->count());

        // Authors attached from every MEMBER column.
        $this->assertSame(
            ['Dela Cruz, Juan', 'Santos, Maria'],
            $paper->authors()->orderBy('name')->pluck('name')->all()
        );

        // People dedupe case-insensitively and are stored as first written.
        $this->assertDatabaseCount('technical_advisers', 2); // ENGR. REYES + ENGR. DY
        $this->assertDatabaseCount('research_advisers', 2); // PROF. LIM + DR. SY
        $this->assertDatabaseCount('deans', 1); // DEAN AQUINO
        $this->assertDatabaseCount('authors', 8);
        $this->assertDatabaseHas('technical_advisers', ['name' => 'ENGR. REYES']);
        $this->assertSame(
            TechnicalAdviser::where('name', 'ENGR. REYES')->value('id'),
            AcademicPaper::where('catalog_code', 'CEIT-IT-25-01')->value('technical_adviser_id')
        );

        // Skipped rows did not create people.
        $this->assertDatabaseMissing('authors', ['name' => 'Ng, Pat']);
        $this->assertDatabaseMissing('authors', ['name' => 'Xx, Yy']);

        // --- One index rebuild after commit, nothing during the import.
        Bus::assertDispatched(AiIndexRebuildJob::class, 1);
        Bus::assertNotDispatched(AiIndexRebuildImmediateJob::class);
    }

    #[Test]
    public function reimporting_the_same_workbook_is_idempotent_and_reports_duplicates(): void
    {
        $path = $this->writeWorkbook([
            'Electrical Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2024, 'THESIS', 'CEIT-EE-24-01', 'Smart Grid Monitoring', 1, 'Dela Cruz, Juan'],
            ],
            'Civil Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2023, 'RESEARCH', 'CEIT-CE-23-01', 'Concrete Mix Study', 2, 'Torres, Jose'],
            ],
            'Information Technology' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG CODE', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2025, 'THESIS', 'CEIT-IT-25-01', 'Library Kiosk System', 1, 'Engr. Santos'],
            ],
        ]);

        (new CatalogImport)($path);
        $this->assertDatabaseCount('academic_papers', 3);
        $paper = AcademicPaper::where('catalog_code', 'CEIT-CE-23-01')->firstOrFail();
        $original = $paper->toArray();

        $report = (new CatalogImport)($path);

        // Nothing new, nothing updated — just duplicate reports.
        $this->assertDatabaseCount('academic_papers', 3);
        $this->assertSame($original, $paper->fresh()->toArray());
        $this->assertSame(2, Inventory::where('academic_paper_id', $paper->id)->count());
        $this->assertSame(
            3,
            collect($report['sheets'])->sum('duplicates')
        );
        $this->assertSame(
            0,
            collect($report['sheets'])->sum('imported')
        );
    }

    #[Test]
    public function a_catalog_code_already_in_the_database_is_reported_as_duplicate(): void
    {
        AcademicPaper::factory()->create(['catalog_code' => 'CEIT-EE-24-01']);

        $report = (new CatalogImport)($this->writeWorkbook([
            'Electrical Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2024, 'THESIS', 'CEIT-EE-24-01', 'Smart Grid Monitoring', 1, 'Dela Cruz, Juan'],
                [2024, 'CAPSTONE', 'CEIT-EE-24-02', 'IoT Power Meter', 1, 'Garcia, Ana'],
            ],
        ]));

        $ee = $report['sheets']['Electrical Engineering'];
        $this->assertSame(1, $ee['imported']);
        $this->assertSame(1, $ee['duplicates']);
        $this->assertDatabaseCount('academic_papers', 2);
        // The pre-existing row was not touched.
        $this->assertSame('CEIT-EE-24-01', AcademicPaper::orderBy('id')->first()->catalog_code);
    }

    #[Test]
    public function a_sheet_missing_required_headers_fails_at_file_level_but_other_sheets_import(): void
    {
        $report = (new CatalogImport)($this->writeWorkbook([
            'Electrical Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'COPIES', 'TECHNICAL ADVISER'],
                [2024, 'THESIS', 1, 'ENGR. REYES'],
            ],
            'Civil Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2023, 'RESEARCH', 'CEIT-CE-23-01', 'Concrete Mix Study', 1, 'Torres, Jose'],
            ],
        ]));

        $ee = $report['sheets']['Electrical Engineering'];
        $this->assertNotNull($ee['error']);
        $this->assertStringContainsString('Electrical Engineering', $ee['error']);
        $this->assertStringContainsString('APPROVED OFFICIAL LONG TITLE', $ee['error']);
        $this->assertStringContainsString('MEMBER', $ee['error']);
        $this->assertSame(0, $ee['imported']);

        $this->assertSame(1, $report['sheets']['Civil Engineering']['imported']);
        $this->assertStringContainsString(
            'Information Technology',
            $report['sheets']['Information Technology']['error']
        );
        $this->assertDatabaseCount('academic_papers', 1);
    }
}
