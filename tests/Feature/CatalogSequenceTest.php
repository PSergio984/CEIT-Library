<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogSequenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_sequential_catalog_codes_for_same_department_and_year(): void
    {
        // Create 3 papers for IT department in 2025
        $paper1 = AcademicPaper::create([
            'title' => 'Test Sequence 1',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        $paper2 = AcademicPaper::create([
            'title' => 'Test Sequence 2',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        $paper3 = AcademicPaper::create([
            'title' => 'Test Sequence 3',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        // Assert catalog codes are sequential
        $this->assertEquals('CEIT-IT-25-01', $paper1->catalog_code);
        $this->assertEquals('CEIT-IT-25-02', $paper2->catalog_code);
        $this->assertEquals('CEIT-IT-25-03', $paper3->catalog_code);

        // Verify sequence table state
        $sequence = DB::table('catalog_sequences')
            ->where('sequence_key', 'IT-25')
            ->first();

        $this->assertNotNull($sequence);
        $this->assertEquals(3, $sequence->last_sequence);
    }

    #[Test]
    public function it_handles_flexible_padding_for_sequences_over_99(): void
    {
        // Manually set sequence to 98 to test the 99 -> 100 transition
        DB::table('catalog_sequences')->insert([
            'sequence_key' => 'IT-25',
            'last_sequence' => 98,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $papers = [];
        for ($i = 1; $i <= 5; $i++) {
            $papers[] = AcademicPaper::create([
                'title' => "Padding Test {$i}",
                'publication_year' => 2025,
                'paper_type' => 'Thesis',
                'department' => 'Information Technology',
                'research_project_adviser' => 'Dr. Test',
                'dean' => 'Dean Test',
            ]);
        }

        // Assert codes maintain minimum 2-digit format and expand to 3 digits
        $this->assertEquals('CEIT-IT-25-99', $papers[0]->catalog_code);
        $this->assertEquals('CEIT-IT-25-100', $papers[1]->catalog_code);
        $this->assertEquals('CEIT-IT-25-101', $papers[2]->catalog_code);
        $this->assertEquals('CEIT-IT-25-102', $papers[3]->catalog_code);
        $this->assertEquals('CEIT-IT-25-103', $papers[4]->catalog_code);

        // Verify final sequence state
        $sequence = DB::table('catalog_sequences')
            ->where('sequence_key', 'IT-25')
            ->value('last_sequence');

        $this->assertEquals(103, $sequence);
    }

    #[Test]
    public function it_supports_large_sequence_numbers(): void
    {
        $testCases = [
            ['start' => 0, 'expected' => 'CEIT-CE-25-01', 'description' => 'First paper'],
            ['start' => 8, 'expected' => 'CEIT-CE-25-09', 'description' => 'Single digit'],
            ['start' => 98, 'expected' => 'CEIT-CE-25-99', 'description' => 'Max 2-digit'],
            ['start' => 99, 'expected' => 'CEIT-CE-25-100', 'description' => 'First 3-digit'],
            ['start' => 998, 'expected' => 'CEIT-CE-25-999', 'description' => 'Max 3-digit'],
            ['start' => 999, 'expected' => 'CEIT-CE-25-1000', 'description' => 'First 4-digit'],
        ];

        foreach ($testCases as $case) {
            // Reset sequence for each test case
            DB::table('catalog_sequences')
                ->where('sequence_key', 'CE-25')
                ->delete();

            DB::table('catalog_sequences')->insert([
                'sequence_key' => 'CE-25',
                'last_sequence' => $case['start'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paper = AcademicPaper::create([
                'title' => 'Large Seq Test',
                'publication_year' => 2025,
                'paper_type' => 'Thesis',
                'department' => 'Civil Engineering',
                'research_project_adviser' => 'Dr. Test',
                'dean' => 'Dean Test',
            ]);

            $this->assertEquals(
                $case['expected'],
                $paper->catalog_code,
                "Failed for: {$case['description']}"
            );

            // Cleanup after each test case
            $paper->delete();
        }
    }

    #[Test]
    public function it_generates_unique_codes_for_different_departments(): void
    {
        $itPaper = AcademicPaper::create([
            'title' => 'IT Paper',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        $cePaper = AcademicPaper::create([
            'title' => 'CE Paper',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Civil Engineering',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        $eePaper = AcademicPaper::create([
            'title' => 'EE Paper',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Electrical Engineering',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        // Each department should start at 01
        $this->assertEquals('CEIT-IT-25-01', $itPaper->catalog_code);
        $this->assertEquals('CEIT-CE-25-01', $cePaper->catalog_code);
        $this->assertEquals('CEIT-EE-25-01', $eePaper->catalog_code);

        // Verify separate sequence keys
        $this->assertDatabaseHas('catalog_sequences', ['sequence_key' => 'IT-25', 'last_sequence' => 1]);
        $this->assertDatabaseHas('catalog_sequences', ['sequence_key' => 'CE-25', 'last_sequence' => 1]);
        $this->assertDatabaseHas('catalog_sequences', ['sequence_key' => 'EE-25', 'last_sequence' => 1]);
    }

    #[Test]
    public function it_generates_unique_codes_for_different_years(): void
    {
        $paper2024 = AcademicPaper::create([
            'title' => '2024 Paper',
            'publication_year' => 2024,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        $paper2025 = AcademicPaper::create([
            'title' => '2025 Paper',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'department' => 'Information Technology',
            'research_project_adviser' => 'Dr. Test',
            'dean' => 'Dean Test',
        ]);

        // Different years should have different sequence keys
        $this->assertEquals('CEIT-IT-24-01', $paper2024->catalog_code);
        $this->assertEquals('CEIT-IT-25-01', $paper2025->catalog_code);

        // Verify separate sequence keys for different years
        $this->assertDatabaseHas('catalog_sequences', ['sequence_key' => 'IT-24', 'last_sequence' => 1]);
        $this->assertDatabaseHas('catalog_sequences', ['sequence_key' => 'IT-25', 'last_sequence' => 1]);
    }

    #[Test]
    public function it_generates_unique_sequential_codes(): void
    {
        // Create multiple papers sequentially to verify unique sequence generation
        $papers = collect();

        for ($i = 1; $i <= 10; $i++) {
            $papers->push(AcademicPaper::create([
                'title' => "Sequential Test {$i}",
                'publication_year' => 2025,
                'paper_type' => 'Thesis',
                'department' => 'Information Technology',
                'research_project_adviser' => 'Dr. Test',
                'dean' => 'Dean Test',
            ]));
        }

        // Extract sequence numbers from catalog codes
        $sequences = $papers->map(function ($paper) {
            preg_match('/CEIT-IT-25-(\d+)/', $paper->catalog_code, $matches);
            return (int) $matches[1];
        })->sort()->values();

        // All sequences should be unique and sequential
        $expected = collect(range(1, 10));
        $this->assertEquals($expected->toArray(), $sequences->toArray());

        // No duplicates
        $this->assertEquals(10, $sequences->unique()->count());
    }
}
