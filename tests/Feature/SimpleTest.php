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
            'email' => 'john@plv.edu.ph',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@plv.edu.ph',
        ]);

        $this->assertInstanceOf(User::class, $user);
    }

    public function test_academic_paper_can_be_created()
    {
        $paper = AcademicPaper::factory()->create([
            'title' => 'Test Paper',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Test Paper',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        $this->assertInstanceOf(AcademicPaper::class, $paper);
    }
}
