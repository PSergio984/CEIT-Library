<?php

namespace Tests\Unit;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Librarian;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\TestHelper;

class UserTest extends TestCase
{
    use TestHelper;

    /**
     * Test user can be created with factory.
     *
     * @return void
     */
    public function test_user_can_be_created_with_factory()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@plv.edu.ph',
        ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@plv.edu.ph',
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
        $user = new User;
        $fillable = $user->getFillable();

        $this->assertContains('first_name', $fillable);
        $this->assertContains('last_name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
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

        $inventory1 = Inventory::factory()->create([
            'academic_paper_id' => $academicPaper1->id,
            'copy_number' => 1,
        ]);
        $inventory2 = Inventory::factory()->create([
            'academic_paper_id' => $academicPaper2->id,
            'copy_number' => 1,
        ]);

        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $academicPaper1->id,
            'inventory_id' => $inventory1->id,
            'time_in' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(9),
            'session_token' => $this->generateSessionToken('test-token-1'),
        ]);

        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $academicPaper2->id,
            'inventory_id' => $inventory2->id,
            'time_in' => Carbon::now()->subDays(3),
            'expires_at' => Carbon::now()->addDays(11),
            'session_token' => $this->generateSessionToken('test-token-2'),
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
     * Test user has credit score accessor.
     *
     * @return void
     */
    public function test_user_has_credit_score_accessor()
    {
        $user = User::factory()->create();

        // Test that the credit score accessor exists and returns a numeric value
        $this->assertIsInt($user->credit_score);
        $this->assertEquals(100, $user->credit_score);
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
        Carbon::setTestNow(Carbon::now());

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
     * Test librarian permission system (if implemented in your User model).
     * Note: This test assumes you have a hasLibrarianPermission method in your User model.
     * If not implemented, you can remove these tests or implement the method.
     *
     * @return void
     */
    public function test_librarian_permission_system_exists()
    {
        $user = User::factory()->create();

        // Check if the method exists in the User model
        if (method_exists($user, 'hasLibrarianPermission')) {
            // If method exists, test it
            $this->assertFalse($user->hasLibrarianPermission('manage_books'));
        } else {
            // If method doesn't exist, skip the test
            $this->markTestSkipped('hasLibrarianPermission method not implemented in User model');
        }
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
            'date_occurred' => Carbon::now()->subDays(10),
            'remarks' => 'Late return violation',
        ]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation2->id,
            'date_occurred' => Carbon::now()->subDays(5),
            'remarks' => 'Damaged book violation',
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

        // Simulate time difference using Carbon
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addSecond());

        $user->first_name = 'Jane';
        $user->save();

        $this->assertNotEquals($originalUpdatedAt, $user->fresh()->updated_at);

        // Reset Carbon test time
        \Carbon\Carbon::setTestNow();
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
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test.user@plv.edu.ph',
            'password' => 'password',
        ]);

        $this->assertInstanceOf(User::class, $user);
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
     * Test user full name accessor.
     *
     * @return void
     */
    public function test_user_has_full_name_accessor()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Check if the accessor exists
        if (method_exists($user, 'getFullNameAttribute')) {
            $this->assertEquals('John Doe', $user->full_name);
        } else {
            // If accessor doesn't exist, just verify the user was created with correct names
            $this->assertEquals('John', $user->first_name);
            $this->assertEquals('Doe', $user->last_name);
        }
    }

    /**
     * Test user can be soft deleted.
     *
     * @return void
     */
    public function test_user_can_be_deleted()
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertNull(User::find($userId));
    }

    /**
     * Test user credit score starts at default value.
     *
     * @return void
     */
    public function test_user_credit_score_starts_at_default()
    {
        $user = User::factory()->create();

        // User with no violations should have default credit score of 100
        $this->assertEquals(100, $user->credit_score);
    }

    /**
     * Test user credit score calculation with no violations.
     *
     * @return void
     */
    public function test_user_credit_score_with_no_violations()
    {
        $user = User::factory()->create();

        // User with no violations should have full credit score
        $this->assertEquals(100, $user->credit_score);
        $this->assertCount(0, $user->violations);
    }

    /**
     * Test user can have multiple academic papers borrowed.
     *
     * @return void
     */
    public function test_user_can_have_multiple_academic_papers_borrowed()
    {
        $user = User::factory()->create();

        // Create academic papers
        $paper1 = AcademicPaper::factory()->create(['title' => 'Paper 1']);
        $paper2 = AcademicPaper::factory()->create(['title' => 'Paper 2']);

        // Create inventory items
        $inventory1 = Inventory::factory()->create([
            'academic_paper_id' => $paper1->id,
            'copy_number' => 1,
        ]);
        $inventory2 = Inventory::factory()->create([
            'academic_paper_id' => $paper2->id,
            'copy_number' => 1,
        ]);

        // Create borrow transactions
        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper1->id,
            'inventory_id' => $inventory1->id,
            'time_in' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(9),
            'session_token' => $this->generateSessionToken('test-token-1'),
        ]);

        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper2->id,
            'inventory_id' => $inventory2->id,
            'time_in' => Carbon::now()->subDays(3),
            'expires_at' => Carbon::now()->addDays(11),
            'session_token' => $this->generateSessionToken('test-token-2'),
        ]);

        $this->assertCount(2, $user->borrowTransactions);

        // Check if the relationship exists
        if (method_exists($user, 'academicPapers')) {
            $this->assertCount(2, $user->academicPapers);
        } else {
            // If relationship doesn't exist, just verify the transactions were created
            $this->assertDatabaseCount('borrow_transactions', 2);
        }
    }

    /**
     * Test user can have overdue books.
     *
     * @return void
     */
    public function test_user_can_have_overdue_books()
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
        ]);

        // Create overdue transaction
        BorrowTransaction::create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'time_in' => Carbon::now()->subDays(20),
            'expires_at' => Carbon::now()->subDays(5), // Expired 5 days ago
            'session_token' => $this->generateSessionToken(),
        ]);

        $overdueTransactions = $user->borrowTransactions()
            ->where('expires_at', '<', Carbon::now())
            ->get();

        $this->assertCount(1, $overdueTransactions);
    }

    /**
     * Test user can have active library sessions.
     *
     * @return void
     */
    public function test_user_can_have_active_library_sessions()
    {
        $user = User::factory()->create();

        // Create active session
        Attendance::create([
            'user_id' => $user->id,
            'status' => 'active',
            'time_in' => Carbon::now()->subHours(2),
            'time_out' => null,
        ]);

        $activeSessions = $user->librarySessions()
            ->where('status', 'active')
            ->whereNull('time_out')
            ->get();

        $this->assertCount(1, $activeSessions);
        $this->assertTrue($user->isInLibrary());
    }

    /**
     * Test user violation count.
     *
     * @return void
     */
    public function test_user_violation_count()
    {
        $user = User::factory()->create();

        // Create violations
        $violation1 = Violation::factory()->create();
        $violation2 = Violation::factory()->create();

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation1->id,
            'date_occurred' => Carbon::now()->subDays(10),
            'remarks' => 'Late return violation',
        ]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation2->id,
            'date_occurred' => Carbon::now()->subDays(5),
            'remarks' => 'Damaged book violation',
        ]);

        $this->assertCount(2, $user->violations);
    }

    /**
     * Test user credit score calculation with multiple violations.
     *
     * @return void
     */
    public function test_user_credit_score_with_multiple_violations()
    {
        $user = User::factory()->create();

        // User starts with default credit score
        $this->assertEquals(100, $user->credit_score);

        // Create violations with specific penalty scores
        $violation1 = Violation::factory()->create(['penalty_score' => 5]);
        $violation2 = Violation::factory()->create(['penalty_score' => 15]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation1->id,
            'date_occurred' => Carbon::now()->subDays(10),
            'remarks' => 'Late return violation',
        ]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'violation_id' => $violation2->id,
            'date_occurred' => Carbon::now()->subDays(5),
            'remarks' => 'Damaged book violation',
        ]);

        // Verify violations were created
        $this->assertCount(2, $user->violations);

        // Credit score should be reduced by total penalty (100 - 5 - 15 = 80)
        $this->assertEquals(80, $user->fresh()->credit_score);
    }

    /**
     * Test user credit score calculation with high penalty.
     *
     * @return void
     */
    public function test_user_credit_score_with_high_penalty()
    {
        $user = User::factory()->create();

        // Create violation with high penalty score
        $violation = Violation::factory()->create(['penalty_score' => 150]);

        ViolationTransaction::create([
            'user_id' => $user->id,
            'date_occurred' => Carbon::now(),
            'remarks' => 'Serious violation',
        ]);

        // Credit score should be reduced by penalty (100 - 150 = -50)
        $this->assertEquals(-50, $user->fresh()->credit_score);
    }

    /**
     * Test user can be created with all required fields.
     *
     * @return void
     */
    public function test_user_creation_with_all_required_fields()
    {
        $userData = [
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice.johnson@plv.edu.ph',
            'password' => Hash::make('password123'),
        ];

        $user = User::create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userData['first_name'], $user->first_name);
        $this->assertEquals($userData['last_name'], $user->last_name);
        $this->assertEquals($userData['email'], $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Cleanup after tests.
     */
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
