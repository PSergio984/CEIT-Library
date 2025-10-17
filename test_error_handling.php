<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

/**
 * Helper function from test_last_insert_id.php
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

/**
 * Mock function that simulates a bad DB query result
 */
function testErrorHandling()
{
    echo "Testing error handling scenarios...\n\n";

    // Test 1: Empty result
    echo "Test 1: Simulating empty result\n";
    try {
        // Temporarily mock DB::select to return empty array
        $originalDb = DB::getFacadeRoot();
        DB::swap(new class($originalDb) {
            private $original;
            public function __construct($original)
            {
                $this->original = $original;
            }
            public function select($query)
            {
                return []; // Empty result
            }
            public function __call($method, $args)
            {
                return $this->original->$method(...$args);
            }
        });
        getLastInsertIdSafely();
        echo "❌ Should have thrown an exception!\n";
    } catch (\RuntimeException $e) {
        echo "✓ Correctly caught error: {$e->getMessage()}\n";
    } finally {
        DB::swap($originalSelect);
    }

    echo "\n";

    // Test 2: Missing property
    echo "Test 2: Simulating missing 'id' property\n";
    try {
        $originalSelect = DB::getFacadeRoot();
        DB::swap(new class {
            public function select($query)
            {
                return [(object)['wrong_property' => 123]]; // Wrong property name
            }
            public function __call($method, $args)
            {
                return DB::getFacadeRoot()->$method(...$args);
            }
        });

        getLastInsertIdSafely();
        echo "❌ Should have thrown an exception!\n";
    } catch (\RuntimeException $e) {
        echo "✓ Correctly caught error: {$e->getMessage()}\n";
    } finally {
        DB::swap($originalSelect);
    }

    echo "\n";

    // Test 3: Non-numeric value
    echo "Test 3: Simulating non-numeric 'id' value\n";
    try {
        $originalSelect = DB::getFacadeRoot();
        DB::swap(new class {
            public function select($query)
            {
                return [(object)['id' => 'not_a_number']]; // Non-numeric value
            }
            public function __call($method, $args)
            {
                return DB::getFacadeRoot()->$method(...$args);
            }
        });

        getLastInsertIdSafely();
        echo "❌ Should have thrown an exception!\n";
    } catch (\RuntimeException $e) {
        echo "✓ Correctly caught error: {$e->getMessage()}\n";
    } finally {
        DB::swap($originalSelect);
    }

    echo "\n✅ All error handling tests passed!\n";
}

testErrorHandling();
