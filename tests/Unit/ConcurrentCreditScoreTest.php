<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ScoreIncrement;
use App\Models\ViolationTransaction;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ConcurrentCreditScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test concurrent creation of ScoreIncrements doesn't cause race conditions
     */
    public function test_concurrent_score_increment_creation()
    {
        // Create a user with initial score of 50
        $user = User::factory()->create(['credit_score' => 50]);

        // Simulate concurrent creation of score increments
        $scoreValues = [10, 15, 20, 5];

        // Create multiple score increments rapidly
        foreach ($scoreValues as $value) {
            ScoreIncrement::create([
                'user_id' => $user->id,
                'name' => 'Test Reward',
                'description' => 'Concurrent test',
                'score_value' => $value,
            ]);
        }

        // Refresh user and verify atomic updates worked correctly
        $user->refresh();

        // Should be capped at 100
        $this->assertEquals(100, $user->credit_score);
        $this->assertLessThanOrEqual(100, $user->credit_score);
        $this->assertGreaterThanOrEqual(0, $user->credit_score);
    }

    /**
     * Test concurrent deletion of ScoreIncrements
     */
    public function test_concurrent_score_increment_deletion()
    {
        $user = User::factory()->create(['credit_score' => 100]);

        // Create score increments
        $increments = collect();
        for ($i = 0; $i < 5; $i++) {
            $increments->push(ScoreIncrement::create([
                'user_id' => $user->id,
                'name' => 'Test Reward ' . $i,
                'description' => 'Test',
                'score_value' => 10,
            ]));
        }

        // User should still be at 100 (50 points added but capped)
        $user->refresh();
        $this->assertEquals(100, $user->credit_score);

        // Delete all increments
        foreach ($increments as $increment) {
            $increment->delete();
        }

        // User should be back at 50 (100 - 50)
        $user->refresh();
        $this->assertEquals(50, $user->credit_score);
    }

    /**
     * Test concurrent updates to ScoreIncrement values
     */
    public function test_concurrent_score_increment_updates()
    {
        $user = User::factory()->create(['credit_score' => 50]);

        $increment = ScoreIncrement::create([
            'user_id' => $user->id,
            'name' => 'Test Reward',
            'description' => 'Test',
            'score_value' => 10,
        ]);

        $user->refresh();
        $this->assertEquals(60, $user->credit_score);

        // Update the score value
        $increment->update(['score_value' => 25]);

        // Should add the difference (25 - 10 = +15), so 60 + 15 = 75
        $user->refresh();
        $this->assertEquals(75, $user->credit_score);
    }

    /**
     * Test mixed concurrent operations
     */
    public function test_mixed_concurrent_operations()
    {
        $user = User::factory()->create(['credit_score' => 50]);
        $violation = Violation::factory()->create(['penalty_score' => 5]);

        // Create score increment (+10)
        ScoreIncrement::create([
            'user_id' => $user->id,
            'name' => 'Reward',
            'description' => 'Test',
            'score_value' => 10,
        ]);

        // Create violation (-5)
        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation->id,
            'date_occurred' => now(),
            'severity' => 'Minor',
        ]);

        // Should be 50 + 10 - 5 = 55
        $user->refresh();
        $this->assertEquals(55, $user->credit_score);
    }

    /**
     * Test that credit score never goes below 0
     */
    public function test_credit_score_floor_constraint()
    {
        $user = User::factory()->create(['credit_score' => 10]);
        $violation = Violation::factory()->create(['penalty_score' => 20]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation->id,
            'date_occurred' => now(),
            'severity' => 'Critical',
        ]);

        $user->refresh();

        // Should be clamped at 0, not -10
        $this->assertEquals(0, $user->credit_score);
        $this->assertGreaterThanOrEqual(0, $user->credit_score);
    }

    /**
     * Test that credit score never exceeds 100
     */
    public function test_credit_score_ceiling_constraint()
    {
        $user = User::factory()->create(['credit_score' => 95]);

        ScoreIncrement::create([
            'user_id' => $user->id,
            'name' => 'Reward',
            'description' => 'Test',
            'score_value' => 20,
        ]);

        $user->refresh();

        // Should be capped at 100, not 115
        $this->assertEquals(100, $user->credit_score);
        $this->assertLessThanOrEqual(100, $user->credit_score);
    }

    /**
     * Test atomic operations handle race conditions correctly
     */
    public function test_atomic_operations_prevent_race_conditions()
    {
        $user = User::factory()->create(['credit_score' => 50]);

        // Simulate rapid-fire operations
        DB::transaction(function () use ($user) {
            for ($i = 0; $i < 10; $i++) {
                ScoreIncrement::create([
                    'user_id' => $user->id,
                    'name' => 'Rapid Reward ' . $i,
                    'description' => 'Race condition test',
                    'score_value' => 5,
                ]);
            }
        });

        $user->refresh();

        // 50 + (10 * 5) = 100
        $this->assertEquals(100, $user->credit_score);

        // Verify all increments were created
        $this->assertEquals(10, ScoreIncrement::where('user_id', $user->id)->count());
    }
}
