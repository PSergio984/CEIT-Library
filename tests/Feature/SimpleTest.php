<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AcademicPaper;
use Tests\TestCase;

class SimpleTest extends TestCase
{
    public function test_user_can_be_created()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_academic_paper_can_be_created()
    {
        $paper = AcademicPaper::factory()->create([
            'title' => 'Test Paper',
            'catalog_code' => 'TEST-001',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Test Paper',
            'catalog_code' => 'TEST-001',
        ]);

        $this->assertInstanceOf(AcademicPaper::class, $paper);
    }
}
