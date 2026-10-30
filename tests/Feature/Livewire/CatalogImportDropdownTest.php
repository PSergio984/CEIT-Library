<?php

namespace Tests\Feature\Livewire;

use App\Jobs\ImportCatalogJob;
use App\Livewire\Pages\Admin\CatalogImportDropdown;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogImportDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create(['role_id' => 3]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    #[Test]
    public function preview_runs_full_validation_with_zero_db_writes(): void
    {
        Livewire::test(CatalogImportDropdown::class)
            ->call('openImport')
            ->assertSet('showImportModal', true)
            ->set('importFile', $this->workbookUpload())
            ->assertSet('preview.sheets.Electrical Engineering.imported', 2)
            ->assertSet('preview.sheets.Electrical Engineering.errors', 0)
            ->assertSet('preview.sheets.Information Technology.imported', 2)
            ->assertSet('preview.sheets.Information Technology.errors', 1)
            ->assertSet('preview.sheets.Information Technology.duplicates', 1)
            ->assertSet('preview.sheets.Information Technology.generated_codes', 1);

        // Preview is a dry run — papers, people, inventory and sequence
        // counters all stay untouched.
        $this->assertDatabaseCount('academic_papers', 0);
        $this->assertDatabaseCount('authors', 0);
        $this->assertDatabaseCount('inventories', 0);
        $this->assertSame(0, DB::table('catalog_sequences')->count());
    }

    #[Test]
    public function preview_reports_row_errors_with_sheet_row_and_reason(): void
    {
        $component = Livewire::test(CatalogImportDropdown::class)
            ->call('openImport')
            ->set('importFile', $this->workbookUpload());

        $messages = collect($component->get('preview')['sheets']['Information Technology']['messages']);

        $error = $messages->firstWhere('type', 'error');
        $this->assertSame(4, $error['row']);
        $this->assertStringContainsString('Unknown paper type', $error['message']);

        $duplicate = $messages->firstWhere('type', 'duplicate');
        $this->assertSame(5, $duplicate['row']);
    }

    #[Test]
    public function confirm_dispatches_a_queued_job_instead_of_importing_inline(): void
    {
        Queue::fake();

        Livewire::test(CatalogImportDropdown::class)
            ->call('openImport')
            ->set('importFile', $this->workbookUpload())
            ->call('confirmImport')
            ->assertSet('showImportModal', false)
            ->assertSet('preview', null);

        Queue::assertPushed(ImportCatalogJob::class, fn ($job) => $job->userId === $this->admin->id);

        $this->assertDatabaseCount('academic_papers', 0);
    }

    #[Test]
    public function the_job_commits_the_import_notifies_the_uploader_and_deletes_the_file(): void
    {
        Bus::fake();

        $path = 'catalog-imports/test-workbook.xlsx';
        Storage::disk('local')->put($path, file_get_contents($this->staffWorkbook()));

        (new ImportCatalogJob($path, $this->admin->id))->handle();

        $this->assertDatabaseCount('academic_papers', 5);

        $notification = Notification::where('user_id', $this->admin->id)
            ->where('type', 'catalog_import')
            ->firstOrFail();

        $this->assertStringContainsString('5 imported', $notification->message);
        $this->assertStringContainsString('1 skipped', $notification->message);
        $this->assertStringContainsString('1 errors', $notification->message);
        $this->assertArrayHasKey('failure_report_path', $notification->data);

        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    #[Test]
    public function a_workbook_the_engine_cannot_read_reports_a_failed_notification(): void
    {
        $path = 'catalog-imports/broken-workbook.xlsx';
        Storage::disk('local')->put($path, 'this is not a spreadsheet');

        (new ImportCatalogJob($path, $this->admin->id))->handle();

        $notification = Notification::where('user_id', $this->admin->id)
            ->where('type', 'catalog_import')
            ->firstOrFail();

        $this->assertDatabaseCount('academic_papers', 0);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    #[Test]
    public function librarians_cannot_open_the_import_modal(): void
    {
        $librarian = User::factory()->create(['role_id' => 2]);
        $this->actingAs($librarian);

        $this->withoutExceptionHandling();
        $this->expectException(AuthorizationException::class);

        Livewire::test(CatalogImportDropdown::class)->call('openImport');
    }

    #[Test]
    public function a_non_excel_upload_is_rejected(): void
    {
        Livewire::test(CatalogImportDropdown::class)
            ->call('openImport')
            ->set('importFile', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
            ->assertHasErrors('importFile')
            ->assertSet('preview', null);
    }

    /**
     * A real .xlsx upload carrying the staff workbook fixture.
     */
    private function workbookUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'catalog.xlsx',
            file_get_contents($this->staffWorkbook()),
        );
    }

    /**
     * Write a workbook shaped like the staff catalog file.
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

        $path = sys_get_temp_dir().'/catalog-import-ux-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function staffWorkbook(): string
    {
        return $this->writeWorkbook([
            'Electrical Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2024, 'THESIS', 'CEIT-EE-24-01', 'Smart Grid Monitoring', 2, 'Dela Cruz, Juan'],
                [2024, 'CAPSTONE', 'CEIT-EE-24-02', 'IoT Power Meter', 0, 'Garcia, Ana'],
            ],
            'Civil Engineering' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG NUMBER', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2023, 'RESEARCH', 'CEIT-CE-23-01', 'Concrete Mix Study', 1, 'Torres, Jose'],
            ],
            'Information Technology' => [
                ['YEAR', 'TYPE OF ACADEMIC PAPER', 'CATALOG CODE', 'APPROVED OFFICIAL LONG TITLE', 'COPIES', 'MEMBER 1'],
                [2025, 'THESIS', 'CEIT-IT-25-01', 'Library Kiosk System', 1, 'Engr. Santos'],
                [2026, 'CAPSTONE', null, 'Capstone No Code', 2, 'Lee, Kevin'],
                [2025, 'WIDGETS', 'CEIT-IT-25-99', 'Bad Type Row', 1, 'Xx, Yy'],
                [2025, 'THESIS', 'CEIT-EE-24-01', 'Duplicate Code Paper', 1, 'Ng, Pat'],
            ],
        ]);
    }
}
