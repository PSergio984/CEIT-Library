<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing that cleanup runs even on error...\n\n";

// Pre-cleanup
App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->delete();
DB::table('catalog_sequences')->where('sequence_key', 'like', 'IT-25%')->delete();

try {
    $paper1 = App\Models\AcademicPaper::create([
        'title' => 'Test Sequence 1',
        'publication_year' => 2025,
        'paper_type' => 'Thesis',
        'department' => 'Information Technology',
        'research_project_adviser' => 'Dr. Test',
        'dean' => 'Dean Test',
    ]);
    echo "Created Paper 1: {$paper1->catalog_code}\n";

    // Simulate an error
    throw new RuntimeException("Simulated error after creating 1 paper");
} catch (Exception $e) {
    echo "\n❌ Caught expected error: {$e->getMessage()}\n";
} finally {
    echo "\nRunning cleanup in finally block...\n";
    $count = App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->count();
    echo "Found {$count} test record(s) before cleanup\n";

    App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->delete();
    DB::table('catalog_sequences')->where('sequence_key', 'like', 'IT-25%')->delete();

    $countAfter = App\Models\AcademicPaper::where('title', 'like', 'Test Sequence%')->count();
    echo "Found {$countAfter} test record(s) after cleanup\n";

    echo "\n✓ Cleanup executed successfully even though an error occurred!\n";
}
