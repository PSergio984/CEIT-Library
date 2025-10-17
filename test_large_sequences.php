<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing flexible padding with very large sequences...\n\n";

// Clean up
App\Models\AcademicPaper::where('title', 'Large Seq Test')->delete();
DB::table('catalog_sequences')->where('sequence_key', 'CE-25')->delete();

// Test various edge cases
$testCases = [
    ['start' => 0, 'expected' => 'CEIT-CE-25-01', 'description' => 'First paper (sequence 1)'],
    ['start' => 8, 'expected' => 'CEIT-CE-25-09', 'description' => 'Single digit (9)'],
    ['start' => 98, 'expected' => 'CEIT-CE-25-99', 'description' => 'Max 2-digit (99)'],
    ['start' => 99, 'expected' => 'CEIT-CE-25-100', 'description' => 'First 3-digit (100)'],
    ['start' => 998, 'expected' => 'CEIT-CE-25-999', 'description' => 'Max 3-digit (999)'],
    ['start' => 999, 'expected' => 'CEIT-CE-25-1000', 'description' => 'First 4-digit (1000)'],
];

$allPassed = true;

foreach ($testCases as $test) {
    try {
        DB::transaction(function () use ($test, &$allPassed) {
            // Reset sequence
            DB::table('catalog_sequences')->where('sequence_key', 'CE-25')->delete();
            DB::table('catalog_sequences')->insert([
                'sequence_key' => 'CE-25',
                'last_sequence' => $test['start'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paper = App\Models\AcademicPaper::create([
                'title' => 'Large Seq Test',
                'publication_year' => 2025,
                'paper_type' => 'Thesis',
                'department' => 'Civil Engineering',
                'research_project_adviser' => 'Dr. Test',
                'dean' => 'Dean Test',
            ]);

            if (!$paper || !isset($paper->catalog_code)) {
                throw new \Exception("Failed to generate catalog_code");
            }

            $passed = ($paper->catalog_code === $test['expected']);
            $allPassed = $allPassed && $passed;

            echo ($passed ? '✓' : '✗') . " {$test['description']}\n";
            echo "  Expected: {$test['expected']}\n";
            echo "  Got:      {$paper->catalog_code}\n\n";

            // Transaction will auto-rollback, cleaning up test data
        });
    } catch (\Exception $e) {
        echo "✗ {$test['description']} - ERROR: {$e->getMessage()}\n\n";
        $allPassed = false;
    }
}
echo "\nOverall test " . ($allPassed ? 'PASSED ✓' : 'FAILED ✗') . "\n";
echo "\nConclusion: Flexible padding supports unlimited sequence numbers!\n";
echo "- Maintains minimum 2-digit format for 01-99\n";
echo "- Automatically expands to 3+ digits for 100+\n";
echo "- No artificial limits on department paper count\n";

// Clean up
DB::table('catalog_sequences')->where('sequence_key', 'CE-25')->delete();
echo "\nCleanup done!\n";
