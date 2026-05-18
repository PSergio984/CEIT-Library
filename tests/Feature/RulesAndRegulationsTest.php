<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesAndRegulationsTest extends TestCase
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

    /** @test - TC068: Rules and Regulations - View List */
    public function rules_and_regulations_can_be_viewed_by_admin()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create rule headers and regulations
        $header1 = RuleHeader::factory()->create(['title' => 'General Conduct']);
        RuleRegulation::factory()->count(3)->create(['rule_header_id' => $header1->id]);

        $header2 = RuleHeader::factory()->create(['title' => 'Borrowing Policy']);
        RuleRegulation::factory()->count(2)->create(['rule_header_id' => $header2->id]);

        $response = $this->get(route('admin.rules-and-regulations.index'));
        $response->assertStatus(200);

        $response->assertSee('General Conduct', false);
        $response->assertSee('Borrowing Policy', false);
    }

    /** @test - TC069: Rules and Regulations - Student View List */
    public function rules_and_regulations_can_be_viewed_by_student()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $this->actingAs($student);

        // Create rule headers and regulations
        $header1 = RuleHeader::factory()->create(['title' => 'General Conduct']);
        RuleRegulation::factory()->count(3)->create(['rule_header_id' => $header1->id]);

        $response = $this->get(route('rules-and-regulations.index'));
        $response->assertStatus(200);

        $response->assertSee('General Conduct', false);
    }
}
