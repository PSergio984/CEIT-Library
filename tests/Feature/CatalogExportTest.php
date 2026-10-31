<?php

namespace Tests\Feature;

use App\Exports\CatalogExport;
use App\Exports\CatalogFailureExport;
use App\Exports\CatalogSheetExport;
use App\Exports\CatalogTemplateExport;
use App\Imports\CatalogImport;
use App\Jobs\ImportCatalogJob;
use App\Livewire\Pages\Admin\CatalogImportDropdown;
use App\Models\AcademicPaper;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WritesStaffWorkbook;

class CatalogExportTest extends TestCase
{
    use RefreshDatabase;
    use WritesStaffWorkbook;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create(['role_id' => 3]);
        $this->actingAs($this->admin);
    }

    #[Test]
    public function the_blank_template_has_exactly_three_department_sheets_with_canonical_headers_only(): void
    {
        Excel::store(new CatalogTemplateExport, 'template.xlsx', 'local');

        $document = IOFactory::load(Storage::disk('local')->path('template.xlsx'));

        $this->assertSame(
            ['Electrical Engineering', 'Civil Engineering', 'Information Technology'],
            $document->getSheetNames(),
        );

        foreach ($document->getSheetNames() as $name) {
            $rows = $document->getSheetByName($name)->toArray();

            $this->assertCount(1, $rows);
            $this->assertSame(CatalogSheetExport::HEADERS, $rows[0]);
        }
    }

    #[Test]
    public function the_catalog_export_writes_papers_back_in_staff_format(): void
    {
        (new CatalogImport)($this->staffWorkbook());

        Excel::store(new CatalogExport, 'export.xlsx', 'local');

        $document = IOFactory::load(Storage::disk('local')->path('export.xlsx'));

        $this->assertSame(
            ['Electrical Engineering', 'Civil Engineering', 'Information Technology'],
            $document->getSheetNames(),
        );

        $ee = $document->getSheetByName('Electrical Engineering')->toArray();
        $this->assertCount(3, $ee); // header + 2 papers

        $row = $ee[1];
        $this->assertEquals(2024, $row[0]);
        $this->assertSame('Thesis', $row[1]);
        $this->assertSame('CEIT-EE-24-01', $row[2]);
        $this->assertSame('Smart Grid Monitoring', $row[3]);
        $this->assertEquals(2, $row[4]); // two inventory rows were created for it
        $this->assertSame('Dela Cruz, Juan', $row[5]);
        $this->assertSame('Electrical Engineering', $row[14]);

        $it = $document->getSheetByName('Information Technology')->toArray();
        $this->assertCount(3, $it); // header + 2 papers (error + duplicate rows were never imported)

        // The generated-code paper exports the code the importer assigned.
        $generated = collect($it)->first(fn ($r) => $r[3] === 'Capstone No Code');
        $this->assertNotNull($generated);
        $this->assertNotNull($generated[2]);

        $ce = $document->getSheetByName('Civil Engineering')->toArray();
        $this->assertCount(2, $ce);
    }

    #[Test]
    public function the_export_keeps_only_the_first_seven_authors(): void
    {
        $workbook = $this->writeWorkbook([
            'Information Technology' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES',
                    'MEMBER 1', 'MEMBER 2', 'MEMBER 3', 'MEMBER 4', 'MEMBER 5', 'MEMBER 6', 'MEMBER 7', 'MEMBER 8', 'MEMBER 9', ],
                [2025, 'THESIS', 'CEIT-IT-25-10', 'Nine Author Paper', 1,
                    'A One', 'A Two', 'A Three', 'A Four', 'A Five', 'A Six', 'A Seven', 'A Eight', 'A Nine', ],
            ],
        ]);
        (new CatalogImport)($workbook);

        Excel::store(new CatalogExport, 'export.xlsx', 'local');

        $rows = IOFactory::load(Storage::disk('local')->path('export.xlsx'))
            ->getSheetByName('Information Technology')
            ->toArray();

        $this->assertSame(
            ['A One', 'A Two', 'A Three', 'A Four', 'A Five', 'A Six', 'A Seven'],
            array_slice($rows[1], 5, 7),
        );

        // The format stops at MEMBER 7 — MEMBER 8/9 have no columns past DEAN.
        $this->assertCount(count(CatalogSheetExport::HEADERS), $rows[1]);
        $this->assertNull($rows[1][16] ?? null);
    }

    #[Test]
    public function a_stored_title_leading_with_a_formula_character_is_escaped_on_export(): void
    {
        AcademicPaper::create([
            'catalog_code' => 'CEIT-IT-25-77',
            'title' => '=HYPERLINK("https://example.com")',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
        ]);

        Excel::store(new CatalogExport, 'export.xlsx', 'local');

        $rows = IOFactory::load(Storage::disk('local')->path('export.xlsx'))
            ->getSheetByName('Information Technology')
            ->toArray();

        $this->assertSame('\'=HYPERLINK("https://example.com")', $rows[1][3]);
    }

    #[Test]
    public function a_freshly_exported_catalog_reimports_as_all_duplicates(): void
    {
        (new CatalogImport)($this->staffWorkbook());

        Excel::store(new CatalogExport, 'export.xlsx', 'local');

        $report = (new CatalogImport)(Storage::disk('local')->path('export.xlsx'));

        $this->assertSame(0, $report['sheets']['Electrical Engineering']['imported']);
        $this->assertSame(2, $report['sheets']['Electrical Engineering']['duplicates']);
        $this->assertSame(0, $report['sheets']['Civil Engineering']['imported']);
        $this->assertSame(1, $report['sheets']['Civil Engineering']['duplicates']);
        $this->assertSame(0, $report['sheets']['Information Technology']['imported']);
        $this->assertSame(2, $report['sheets']['Information Technology']['duplicates']);
        $this->assertSame(0, collect($report['sheets'])->sum('errors'));

        $this->assertDatabaseCount('academic_papers', 5);
    }

    #[Test]
    public function admins_download_the_blank_template_and_catalog_from_the_dropdown(): void
    {
        Livewire::test(CatalogImportDropdown::class)
            ->call('downloadTemplate')
            ->assertFileDownloaded('ceit-catalog-template.xlsx')
            ->call('exportCatalog')
            ->assertFileDownloaded('ceit-catalog-export.xlsx');
    }

    #[Test]
    public function the_template_and_catalog_exports_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => 2]));

        $this->withoutExceptionHandling();

        foreach (['downloadTemplate', 'exportCatalog'] as $action) {
            try {
                Livewire::test(CatalogImportDropdown::class)->call($action);
                $this->fail("{$action} did not throw for a non-admin");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function the_failure_report_downloads_failed_rows_in_the_export_format(): void
    {
        $import = new CatalogImport;
        $import->preview($this->staffWorkbook());
        $failures = $import->failures();

        $this->assertCount(1, $failures['Information Technology']);

        Excel::store(new CatalogFailureExport($failures), 'failures.xlsx', 'local');

        $rows = IOFactory::load(Storage::disk('local')->path('failures.xlsx'))
            ->getSheetByName('Information Technology')
            ->toArray();

        $this->assertCount(2, $rows); // header + the one failed row
        $this->assertSame('FAILED ROW', $rows[0][16]);
        $this->assertSame('FAILURE REASON', $rows[0][17]);

        $row = $rows[1];
        $this->assertSame('CEIT-IT-25-99', $row[2]);
        $this->assertSame('Bad Type Row', $row[3]);
        $this->assertSame('Xx, Yy', $row[5]);
        $this->assertEquals(4, $row[16]);
        $this->assertStringContainsString('Unknown paper type', $row[17]);
    }

    #[Test]
    public function the_preview_modal_downloads_the_failure_report(): void
    {
        Livewire::test(CatalogImportDropdown::class)
            ->call('openImport')
            ->set('importFile', $this->workbookUpload())
            ->call('downloadFailureReport')
            ->assertFileDownloaded('catalog-import-failures.xlsx');
    }

    #[Test]
    public function the_job_stores_the_failure_report_and_links_it_in_the_notification(): void
    {
        Bus::fake();

        $path = 'catalog-imports/test-workbook.xlsx';
        Storage::disk('local')->put($path, file_get_contents($this->staffWorkbook()));

        (new ImportCatalogJob($path, $this->admin->id))->handle();

        $notification = Notification::where('user_id', $this->admin->id)
            ->where('type', 'catalog_import')
            ->firstOrFail();

        $failurePath = $notification->data['failure_report_path'];
        $this->assertNotNull($failurePath);
        $this->assertTrue(Storage::disk('local')->exists($failurePath));

        // The stored report carries the failed row in the export format.
        $rows = IOFactory::load(Storage::disk('local')->path($failurePath))
            ->getSheetByName('Information Technology')
            ->toArray();
        $this->assertSame('Bad Type Row', $rows[1][3]);
        $this->assertEquals(4, $rows[1][16]);

        // The source upload is still deleted.
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    #[Test]
    public function the_failure_report_route_downloads_the_stored_file_for_admins_only(): void
    {
        Storage::disk('local')->put('catalog-imports/failures/report.xlsx', 'xlsx-bytes');

        $this->get(route('admin.catalog-import.failure-report', 'report.xlsx'))
            ->assertOk()
            ->assertDownload('catalog-import-failures.xlsx');

        $this->actingAs(User::factory()->create(['role_id' => 2]));

        // The 403 error page needs a frontend build; assert the gate throws.
        $this->withoutExceptionHandling();
        $this->expectException(AuthorizationException::class);

        $this->get(route('admin.catalog-import.failure-report', 'report.xlsx'));
    }
}
