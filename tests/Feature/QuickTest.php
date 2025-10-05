<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class QuickTest extends TestCase
{
    public function test_database_works_without_fulltext_errors()
    {
        // This test verifies that our custom database setup works
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
    }
}
