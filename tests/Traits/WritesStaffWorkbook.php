<?php

namespace Tests\Traits;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates real .xlsx fixtures shaped like the staff catalog workbook
 * and cleans them up after the test. The shared `staffWorkbook` fixture
 * is the compact variant (one drift header, a blank code, a bad type,
 * a duplicate); tests exercising more header drift override it.
 */
trait WritesStaffWorkbook
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
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

        $path = sys_get_temp_dir().'/catalog-import-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * The compact staff workbook fixture: a `CATALOG CODE` drift header,
     * a blank code (generated), an unknown paper type, and a duplicate.
     */
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
