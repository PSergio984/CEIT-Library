<?php

namespace Tests\Unit;

use App\Models\AcademicPaper;
use App\Models\Inventory;
use App\Models\Violation;
use Tests\TestCase;
use App\Models\User;
use App\Models\Librarian;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\ViolationTransaction;
use App\Models\ScoreIncrement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can be created with factory.
     *
     * @return void
     */
    public function test_user_can_be_created_with_factory()
    {
        $user = User::factory()->create([
            'student_no' => '2024001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => '[email protected]',
        ]);

        $this->assertDatabaseHas('users', [
            'student_no' => '2024001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => '[email protected]',
        ]);

        $this->assertInstanceOf(User::class, $user);
    }

    /**
     * Test user password is hashed.
     *
     * @return void
     */
    public function test_user_password_is_hashed()
    {
        $user = User::factory()->create([
            'password' => 'plain-text-password',
        ]);

        $this->assertNotEquals('plain-text-password', $user->password);
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    /**
     * Test user has fillable attributes.
     *
     * @return void
     */
    public function test_user_has_correct_fillable_attributes()
    {
        $user = new User();
        $fillable = $user->getFillable();

        $this->assertContains('student_no', $fillable);
        $this->assertContains('first_name', $fillable);
        $this->assertContains('last_name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('id_path', $fillable);
    }

    /**
     * Test user has hidden attributes.
     *
     * @return void
     */
    public function test_user_has_correct_hidden_attributes()
    {
        $user = User::factory()->create();
        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    /**
     * Test password and remember_token are hidden in serialization.
     *
     * @return void
     */
    public function test_sensitive_attributes_are_hidden_in_json()
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
            'remember_token' => 'some-token',
        ]);

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /**
     * Test user has librarian duty relationship.
     *
     * @return void
     */
    public function test_user_has_librarian_duty_relationship()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasOne::class,
            $user->librarianDuty()
        );
    }

    /**
     * Test user has library sessions relationship.
     *
     * @return void
     */
    public function test_user_has_library_sessions_relationship()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->librarySessions()
        );
    }

    /**
     * Test user has borrow transactions relationship.
     *
     * @return void
     */
    public function test_user_can_have_multiple_borrow_transactions()
    {
        $user = User::factory()->create();

        // Create the necessary related records first
        $academicPaper1 = AcademicPaper::factory()->create();
        $academicPaper2 = AcademicPaper::factory()->create();

        $inventory1 = Inventory::factory()->create(['academic_paper_id' => $academicPaper1->id]);
        $inventory2 = Inventory::factory()->create(['academic_paper_id' => $academicPaper2->id]);

        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $academicPaper1->id,
            'inventory_id' => $inventory1->id,
            'borrowed_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(9),
            'due_at' => Carbon::now()->addDays(14),
        ]);

        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $academicPaper2->id,
            'inventory_id' => $inventory2->id,
            'borrowed_at' => Carbon::now()->subDays(3),
            'expires_at' => Carbon::now()->addDays(11),
            'due_at' => Carbon::now()->addDays(15),
        ]);

        $this->assertCount(2, $user->borrowTransactions);
    }

    /**
     * Test user has violations relationship.
     *
     * @return void
     */
    public function test_user_has_violations_relationship()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->violations()
        );
    }

    /**
     * Test user has credit score relationship.
     *
     * @return void
     */
    public function test_user_has_credit_score_relationship()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasOne::class,
            $user->creditScore()
        );
    }

    /**
     * Test isLibrarian returns true for active librarian with valid expiry.
     *
     * @return void
     */
    public function test_is_librarian_returns_true_for_active_librarian()
    {
        $user = User::factory()->create();

        // Create active librarian duty with future expiry date
        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250001',
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $this->assertTrue($user->isLibrarian());
    }

    /**
     * Test isLibrarian returns false for inactive librarian.
     *
     * @return void
     */
    public function test_is_librarian_returns_false_for_inactive_librarian()
    {
        $user = User::factory()->create();

        // Create inactive librarian duty
        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250002',
            'status' => 'inactive',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $this->assertFalse($user->isLibrarian());
    }

    /**
     * Test isLibrarian returns false for expired librarian.
     *
     * @return void
     */
    public function test_is_librarian_returns_false_for_expired_librarian()
    {
        $user = User::factory()->create();

        // Create active librarian duty with past expiry date
        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250003',
            'status' => 'active',
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($user->isLibrarian());
    }

    /**
     * Test isLibrarian returns false for user without librarian duty.
     *
     * @return void
     */
    public function test_is_librarian_returns_false_for_regular_user()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isLibrarian());
    }

    /**
     * Test isLibrarian returns false when librarian duty expires exactly now.
     *
     * @return void
     */
    public function test_is_librarian_returns_false_when_expires_at_is_now()
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 12:00:00'));

        $user = User::factory()->create();

        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250004',
            'status' => 'active',
            'expires_at' => Carbon::now(),
        ]);

        $this->assertFalse($user->isLibrarian());

        Carbon::setTestNow(); // Reset
    }

    /**
     * Test getActiveLibrarianDuty returns active librarian record.
     *
     * @return void
     */
    public function test_get_active_librarian_duty_returns_active_record()
    {
        $user = User::factory()->create();

        $librarian = Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250005',
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $result = $user->getActiveLibrarianDuty();

        $this->assertNotNull($result);
        $this->assertEquals($librarian->id, $result->id);
        $this->assertInstanceOf(Librarian::class, $result);
    }

    /**
     * Test getActiveLibrarianDuty returns null for inactive librarian.
     *
     * @return void
     */
    public function test_get_active_librarian_duty_returns_null_for_inactive()
    {
        $user = User::factory()->create();

        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250006',
            'status' => 'inactive',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $result = $user->getActiveLibrarianDuty();

        $this->assertNull($result);
    }

    /**
     * Test getActiveLibrarianDuty returns null for expired librarian.
     *
     * @return void
     */
    public function test_get_active_librarian_duty_returns_null_for_expired()
    {
        $user = User::factory()->create();

        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250007',
            'status' => 'active',
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $user->getActiveLibrarianDuty();

        $this->assertNull($result);
    }

    /**
     * Test getActiveLibrarianDuty returns null when no librarian duty exists.
     *
     * @return void
     */
    public function test_get_active_librarian_duty_returns_null_when_no_duty_exists()
    {
        $user = User::factory()->create();

        $result = $user->getActiveLibrarianDuty();

        $this->assertNull($result);
    }

    /**
     * Test hasLibrarianPermission returns true when user has permission.
     *
     * @return void
     */
    public function test_has_librarian_permission_returns_true_when_permitted()
    {
        $createdUser = User::factory()->create();

        $mockLibrarian = \Mockery::mock(Librarian::class);
        $mockLibrarian->shouldReceive('hasPermission')
            ->with('manage_books')
            ->andReturn(true);

        $mockUser = \Mockery::mock(User::class)->makePartial();
        $mockUser->shouldReceive('getActiveLibrarianDuty')
            ->andReturn($mockLibrarian);

        $this->assertTrue($mockUser->hasLibrarianPermission('manage_books'));
    }

    /**
     * Test hasLibrarianPermission returns false when user lacks permission.
     *
     * @return void
     */
    public function test_has_librarian_permission_returns_false_when_not_permitted()
    {
        $createdUser = User::factory()->create();

        $mockLibrarian = \Mockery::mock(Librarian::class);
        $mockLibrarian->shouldReceive('hasPermission')
            ->with('manage_books')
            ->andReturn(false);

        $mockUser = \Mockery::mock(User::class)->makePartial();
        $mockUser->shouldReceive('getActiveLibrarianDuty')
            ->andReturn($mockLibrarian);

        $this->assertFalse($mockUser->hasLibrarianPermission('manage_books'));
    }

    /**
     * Test hasLibrarianPermission returns false when user has no active librarian duty.
     *
     * @return void
     */
    public function test_has_librarian_permission_returns_false_when_no_active_duty()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasLibrarianPermission('manage_books'));
    }

    /**
     * Test isInLibrary returns true when user has active session.
     *
     * @return void
     */
    public function test_is_in_library_returns_true_for_active_session()
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subHours(2),
            'time_out' => null,
        ]);

        $this->assertTrue($user->isInLibrary());
    }

    /**
     * Test isInLibrary returns false when user has checked out.
     *
     * @return void
     */
    public function test_is_in_library_returns_false_when_checked_out()
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subHours(2),
            'time_out' => Carbon::now(),
        ]);

        $this->assertFalse($user->isInLibrary());
    }

    /**
     * Test isInLibrary returns false when session is inactive.
     *
     * @return void
     */
    public function test_is_in_library_returns_false_for_inactive_session()
{
    // 1. Create a user
    $user = User::factory()->create();

    // 2. Create a *completed* attendance record for that user
    // An inactive session is one that has a 'time_out' value.
    Attendance::create([
        'user_id' => $user->id,
        'status' => 'completed', // Use a valid status for a finished session
        'time_in' => Carbon::now()->subHours(2),
        'time_out' => Carbon::now()->subHour(), // The presence of a time_out indicates the session is inactive
    ]);

    // 3. Assert that the user is NOT currently in the library
    $this->assertFalse($user->isInLibrary());
}

    /**
     * Test isInLibrary returns false when time_in is null.
     *
     * @return void
     */
    public function test_is_in_library_returns_false_when_time_in_is_null()
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => null,
            'time_out' => null,
        ]);

        $this->assertFalse($user->isInLibrary());
    }

    /**
     * Test isInLibrary returns false when user has no attendance records.
     *
     * @return void
     */
    public function test_is_in_library_returns_false_when_no_attendance()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isInLibrary());
    }

    /**
     * Test user can have multiple library sessions.
     *
     * @return void
     */
    public function test_user_can_have_multiple_library_sessions()
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subDays(2),
            'time_out' => Carbon::now()->subDays(2)->addHours(3),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subDays(1),
            'time_out' => Carbon::now()->subDays(1)->addHours(2),
        ]);

        $this->assertCount(2, $user->librarySessions);
    }

    /**
     * Test user can have multiple violations.
     *
     * @return void
     */
    public function test_user_can_have_multiple_violations()
    {
        $user = User::factory()->create();

        // Create a violation record first (assuming you have a Violation model)
        $violation1 = Violation::factory()->create();
        $violation2 = Violation::factory()->create();

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation1->id,
            'violation_type' => 'late_return',
            'penalty' => 50,
            'created_at' => Carbon::now()->subDays(10),
            'date_occurred' => Carbon::now()->subDays(10),
        ]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation2->id,
            'violation_type' => 'damaged_book',
            'penalty' => 200,
            'created_at' => Carbon::now()->subDays(5),
            'date_occurred' => Carbon::now()->subDays(5),
        ]);

        $this->assertCount(2, $user->violations);
    }

    /**
     * Test email_verified_at is cast to datetime.
     *
     * @return void
     */
    public function test_email_verified_at_is_cast_to_datetime()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2024-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
    }

    /**
     * Test user can have null email_verified_at.
     *
     * @return void
     */
    public function test_user_can_have_null_email_verified_at()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->assertNull($user->email_verified_at);
    }

    /**
     * Test user timestamps are automatically managed.
     *
     * @return void
     */
    public function test_user_has_timestamps()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertInstanceOf(Carbon::class, $user->created_at);
        $this->assertInstanceOf(Carbon::class, $user->updated_at);
    }

    /**
     * Test updated_at changes when user is modified.
     *
     * @return void
     */
    public function test_updated_at_changes_when_user_is_modified()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
        ]);

        $originalUpdatedAt = $user->updated_at;

        // Wait a moment to ensure time difference
        sleep(1);

        $user->first_name = 'Jane';
        $user->save();

        $this->assertNotEquals($originalUpdatedAt, $user->fresh()->updated_at);
    }

    /**
     * Test user model uses HasFactory trait.
     *
     * @return void
     */
    public function test_user_model_uses_has_factory_trait()
    {
        $this->assertTrue(method_exists(User::class, 'factory'));
    }

    /**
     * Test user model uses Notifiable trait.
     *
     * @return void
     */
    public function test_user_model_uses_notifiable_trait()
    {
        $user = User::factory()->create();

        $this->assertTrue(method_exists($user, 'notify'));
        $this->assertTrue(method_exists($user, 'notifications'));
    }

    /**
     * Test user can be created with minimum required fields.
     *
     * @return void
     */
    public function test_user_can_be_created_with_minimum_fields()
    {
        $user = User::factory()->create([
            'student_no' => '2024999',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => '[email protected]',
            'password' => 'password',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('2024999', $user->student_no);
    }

    /**
     * Test multiple users with same status but different expiry dates.
     *
     * @return void
     */
    public function test_is_librarian_handles_edge_case_with_multiple_duties()
    {
        $user = User::factory()->create();

        // Create one expired duty
        Librarian::create([
            'user_id' => $user->id,
            'batch_no' => '20250008',
            'status' => 'active',
            'expires_at' => Carbon::now()->subDays(5),
        ]);

        // Should still be false even with one expired duty
        $this->assertFalse($user->isLibrarian());
    }

    /**
     * Test isInLibrary with multiple sessions, one active.
     *
     * @return void
     */
    public function test_is_in_library_returns_true_with_one_active_among_multiple()
    {
        $user = User::factory()->create();

        // Old completed session
        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subDays(1),
            'time_out' => Carbon::now()->subDays(1)->addHours(2),
        ]);

        // Current active session
        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subHours(1),
            'time_out' => null,
        ]);

        $this->assertTrue($user->isInLibrary());
    }

    /**
     * Cleanup after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
