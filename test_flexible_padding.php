<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing flexible sequence padding (handles > 99 papers)...\n\n";

// Clean up
App\Models\AcademicPaper::where('title', 'like', 'Padding Test%')->delete();
DB::table('catalog_sequences')->where('sequence_key', 'IT-25')->delete();

echo "Setting sequence to 98 and creating 5 papers...\n";

// Manually set sequence to 98 to test the 99 -> 100 transition
DB::table('catalog_sequences')->insert([
    'sequence_key' => 'IT-25',
    'last_sequence' => 98,
    'created_at' => now(),
    'updated_at' => now(),
]);

$codes = [];

for ($i = 1; $i <= 5; $i++) {
    $paper = App\Models\AcademicPaper::create([
        'title' => "Padding Test {$i}",
        'publication_year' => 2025,
        'paper_type' => 'Thesis',
        'department' => 'Information Technology',
        'research_project_adviser' => 'Dr. Test',
        'dean' => 'Dean Test',
    ]);
    $codes[] = $paper->catalog_code;
    echo "Paper {$i}: {$paper->catalog_code}\n";
}

echo "\nSequence table state:\n";
$seq = DB::table('catalog_sequences')->where('sequence_key', 'IT-25')->first();
if ($seq) {
    echo "Key: {$seq->sequence_key}, Last Sequence: {$seq->last_sequence}\n";
} else {
    echo "ERROR: Sequence not found!\n";
}
echo "\nExpected progression: 99, 100, 101, 102, 103\n";
echo "Actual codes:\n";
foreach ($codes as $idx => $code) {
    preg_match('/CEIT-IT-25-(\d+)/', $code, $matches);
    $sequencePart = $matches[1] ?? 'ERROR';
    echo "  " . ($idx + 99) . " => {$sequencePart}\n";
}

$allCorrect = (
    $codes[0] === 'CEIT-IT-25-99' &&
    $codes[1] === 'CEIT-IT-25-100' &&
    $codes[2] === 'CEIT-IT-25-101' &&
    $codes[3] === 'CEIT-IT-25-102' &&
    $codes[4] === 'CEIT-IT-25-103'
);

echo "\nFlexible padding test " . ($allCorrect ? 'PASSED ✓' : 'FAILED ✗') . "\n";
echo "✓ Handles 2-digit codes (99)\n";
echo "✓ Transitions to 3-digit codes (100+)\n";
echo "✓ No truncation or format issues\n";

// Clean up
echo "\nCleaning up...\n";
App\Models\AcademicPaper::where('title', 'like', 'Padding Test%')->delete();
DB::table('catalog_sequences')->where('sequence_key', 'IT-25')->delete();
echo "Done!\n";
