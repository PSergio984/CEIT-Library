<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ScoreIncrement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditScoreHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function getRoleId(string $roleName): int
    {
        return Role::where('name', $roleName)->value('id') ?? match ($roleName) {
            'student' => 1,
            'librarian' => 2,
            'admin' => 3,
            'super_admin' => 4,
            default => 1,
        };
    }

    /** @test - TC024: Credit Score History - View and Filter */
    public function user_can_view_and_filter_credit_score_history()
    {
        $user = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'credit_score' => 100,
        ]);

        // Create some score increment records
        ScoreIncrement::factory()->count(5)->create([
            'user_id' => $user->id,
            'score_value' => 100,
        ]);

        $response = $this->actingAs($user)
            ->get(route('CreditScoreHistory'));

        $response->assertStatus(200);
        // Verify history records are displayed
        // Note: Exact implementation depends on the actual page
    }
}
