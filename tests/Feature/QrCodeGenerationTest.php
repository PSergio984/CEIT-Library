<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeGenerationTest extends TestCase
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

    /** @test - TC073: QR Code - Borrow QR Generation */
    public function student_borrow_qr_code_can_be_generated()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $this->actingAs($student);

        $response = $this->get(route('profile'));
        $response->assertStatus(200);

        // Verify QR code is displayed (this is primarily a frontend check)
        $response->assertSee('QR', false);
    }
}
