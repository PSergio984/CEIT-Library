<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing catalog sequence generation...\n\n";

// Clean up existing test data
App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->delete();
DB::table('catalog_sequences')->where('sequence_key', 'like', 'IT-25%')->delete();

echo "Creating 3 papers for IT department in 2025...\n";

$paper1 = null;
$paper2 = null;
$paper3 = null;

try {
    // Create Paper 1
    $paper1 = App\Models\AcademicPaper::create([
        'title' => 'Test Sequence 1',
        'publication_year' => 2025,
        'paper_type' => 'Thesis',
        'department' => 'Information Technology',
        'research_project_adviser' => 'Dr. Test',
        'dean' => 'Dean Test',
    ]);

    if (!$paper1 || !($paper1 instanceof App\Models\AcademicPaper)) {
        throw new RuntimeException("Failed to create Paper 1: create() returned invalid result");
    }
    echo "Paper 1: {$paper1->catalog_code}\n";

    // Create Paper 2
    $paper2 = App\Models\AcademicPaper::create([
        'title' => 'Test Sequence 2',
        'publication_year' => 2025,
        'paper_type' => 'Thesis',
        'department' => 'Information Technology',
        'research_project_adviser' => 'Dr. Test',
        'dean' => 'Dean Test',
    ]);

    if (!$paper2 || !($paper2 instanceof App\Models\AcademicPaper)) {
        throw new RuntimeException("Failed to create Paper 2: create() returned invalid result");
    }
    echo "Paper 2: {$paper2->catalog_code}\n";

    // Create Paper 3
    $paper3 = App\Models\AcademicPaper::create([
        'title' => 'Test Sequence 3',
        'publication_year' => 2025,
        'paper_type' => 'Thesis',
        'department' => 'Information Technology',
        'research_project_adviser' => 'Dr. Test',
        'dean' => 'Dean Test',
    ]);

    if (!$paper3 || !($paper3 instanceof App\Models\AcademicPaper)) {
        throw new RuntimeException("Failed to create Paper 3: create() returned invalid result");
    }
    echo "Paper 3: {$paper3->catalog_code}\n\n";

    // Display sequence table state
    echo "Sequence table state:\n";
    $sequences = DB::table('catalog_sequences')->where('sequence_key', 'IT-25')->get();
    foreach ($sequences as $seq) {
        echo "Key: {$seq->sequence_key}, Last Sequence: {$seq->last_sequence}\n";
    }

    // Validate results
    echo "\nExpected codes: CEIT-IT-25-01, CEIT-IT-25-02, CEIT-IT-25-03\n";
    echo "Test " . ($paper1->catalog_code === 'CEIT-IT-25-01' &&
        $paper2->catalog_code === 'CEIT-IT-25-02' &&
        $paper3->catalog_code === 'CEIT-IT-25-03' ? 'PASSED ✓' : 'FAILED ✗') . "\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
} finally {
    // Always clean up test data, even if test fails
    echo "\nCleaning up test data...\n";

    try {
        App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->delete();
        DB::table('catalog_sequences')->where('sequence_key', 'like', 'IT-25%')->delete();
        echo "Cleanup completed successfully.\n";
    } catch (Exception $cleanupException) {
        echo "⚠️  Warning: Cleanup failed: {$cleanupException->getMessage()}\n";
    }

    echo "Done!\n";
}
