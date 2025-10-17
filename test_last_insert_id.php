<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

/**
 * Safely retrieve LAST_INSERT_ID with comprehensive error handling
 * 
 * @throws \RuntimeException if query fails or returns invalid result
 * @return int The LAST_INSERT_ID value
 */
function getLastInsertIdSafely(): int
{
    try {
        $result = DB::select('SELECT LAST_INSERT_ID() as id');

        // Validate result is not empty
        if (empty($result) || !is_array($result)) {
            throw new \RuntimeException('LAST_INSERT_ID query returned empty or invalid result');
        }

        // Validate first element exists and has the 'id' property
        if (!isset($result[0]) || !is_object($result[0]) || !property_exists($result[0], 'id')) {
            throw new \RuntimeException('LAST_INSERT_ID result missing expected structure');
        }

        // Validate the id value is numeric
        if (!is_numeric($result[0]->id)) {
            throw new \RuntimeException("LAST_INSERT_ID returned non-numeric value: " . var_export($result[0]->id, true));
        }

        return (int) $result[0]->id;
    } catch (\Exception $e) {
        echo "❌ Error retrieving LAST_INSERT_ID: {$e->getMessage()}\n";
        throw $e; // Re-throw to stop execution
    }
}

echo "Testing LAST_INSERT_ID behavior...\n\n";

// Clean up
DB::table('catalog_sequences')->where('sequence_key', 'TEST-25')->delete();

echo "Test 1: Insert new row\n";
DB::statement(
    'INSERT INTO catalog_sequences (sequence_key, last_sequence, created_at, updated_at) 
     VALUES (?, 1, ?, ?) 
     ON DUPLICATE KEY UPDATE last_sequence = LAST_INSERT_ID(last_sequence + 1), updated_at = ?',
    ['TEST-25', $now = now(), $now, $now]
);
$lastId = getLastInsertIdSafely();
$actualSequence = DB::table('catalog_sequences')->where('sequence_key', 'TEST-25')->value('last_sequence');
echo "LAST_INSERT_ID: {$lastId}\n";
echo "Actual last_sequence: {$actualSequence}\n\n";

echo "Test 2: Update existing row\n";
DB::statement(
    'INSERT INTO catalog_sequences (sequence_key, last_sequence, created_at, updated_at) 
     VALUES (?, 1, ?, ?) 
     ON DUPLICATE KEY UPDATE last_sequence = LAST_INSERT_ID(last_sequence + 1), updated_at = VALUES(updated_at)',
    ['TEST-25', now(), now()]
);
$lastId = getLastInsertIdSafely();
$actualSequence = DB::table('catalog_sequences')->where('sequence_key', 'TEST-25')->value('last_sequence');
echo "LAST_INSERT_ID: {$lastId}\n";
echo "Actual last_sequence: {$actualSequence}\n\n";

echo "Test 3: Update again\n";
DB::statement(
    'INSERT INTO catalog_sequences (sequence_key, last_sequence, created_at, updated_at) 
     VALUES (?, 1, ?, ?) 
     ON DUPLICATE KEY UPDATE last_sequence = LAST_INSERT_ID(last_sequence + 1), updated_at = VALUES(updated_at)',
    ['TEST-25', now(), now()]
);
$lastId = getLastInsertIdSafely();
$actualSequence = DB::table('catalog_sequences')->where('sequence_key', 'TEST-25')->value('last_sequence');
echo "LAST_INSERT_ID: {$lastId}\n";
echo "Actual last_sequence: {$actualSequence}\n\n";

// Clean up
DB::table('catalog_sequences')->where('sequence_key', 'TEST-25')->delete();
echo "Cleanup done.\n";
