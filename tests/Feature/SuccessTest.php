<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\User;
use Tests\TestCase;

class SuccessTest extends TestCase
{
    public function test_no_fulltext_index_errors()
    {
        // This is the main success - we can create academic papers without fulltext index errors
        $paper = AcademicPaper::factory()->create([
            'title' => 'A Paper About Fulltext Search',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'A Paper About Fulltext Search',
            'catalog_code' => 'CEIT-IT-25-01',
        ]);

        // Test that we can create multiple papers
        $paper2 = AcademicPaper::factory()->create([
            'title' => 'Another Paper',
            'catalog_code' => 'CEIT-IT-25-02',
        ]);

        $this->assertDatabaseHas('academic_papers', [
            'title' => 'Another Paper',
            'catalog_code' => 'CEIT-IT-25-02',
        ]);
    }

    public function test_user_creation_works()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@plv.edu.ph',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@plv.edu.ph',
        ]);
    }

    public function test_sqlite_in_memory_works()
    {
        // Test that we're using SQLite in-memory database
        $connection = \DB::connection()->getDriverName();
        $this->assertEquals('sqlite', $connection);

        // Test that database is empty at start of each test
        $userCount = User::count();
        $paperCount = AcademicPaper::count();

        $this->assertEquals(0, $userCount);
        $this->assertEquals(0, $paperCount);
    }
}
