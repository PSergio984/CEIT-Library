<?php

namespace Tests\Unit;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\ScoreIncrement;
use App\Models\TechnicalAdviser;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;
use Tests\Traits\TestHelper;

class ModelRelationsTest extends TestCase
{
    use TestHelper;

    public function test_violation_transaction_relations(): void
    {
        $attendance = Attendance::factory()->create();
        $transaction = ViolationTransaction::factory()->create([
            'attendance_id' => $attendance->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $transaction->user());
        $this->assertInstanceOf(BelongsTo::class, $transaction->violation());
        $this->assertInstanceOf(BelongsTo::class, $transaction->attendance());

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertInstanceOf(Violation::class, $transaction->violation);
        $this->assertTrue($transaction->attendance->is($attendance));
    }

    public function test_score_increment_relations(): void
    {
        $attendance = Attendance::factory()->create();
        $borrowTransaction = $this->makeBorrowTransaction();
        $increment = ScoreIncrement::factory()->create([
            'related_attendance_id' => $attendance->id,
            'related_borrow_transaction_id' => $borrowTransaction->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $increment->user());
        $this->assertInstanceOf(BelongsTo::class, $increment->attendance());
        $this->assertInstanceOf(BelongsTo::class, $increment->borrowTransaction());

        $this->assertTrue($increment->attendance->is($attendance));
        $this->assertTrue($increment->borrowTransaction->is($borrowTransaction));
    }

    public function test_violation_relations(): void
    {
        $violation = Violation::factory()->create();
        $transaction = ViolationTransaction::factory()->create([
            'violation_id' => $violation->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $violation->userViolations());
        $this->assertInstanceOf(BelongsToMany::class, $violation->users());

        $this->assertTrue($violation->userViolations->contains($transaction));
        $this->assertTrue($violation->users->contains($transaction->user));
    }

    public function test_user_attendances_relation(): void
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(HasMany::class, $user->attendances());
        $this->assertTrue($user->attendances->contains($attendance));
    }

    public function test_attendance_role_relation(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student']
        );
        $attendance = Attendance::factory()->create(['role_id' => $role->id]);

        $this->assertInstanceOf(BelongsTo::class, $attendance->role());
        $this->assertTrue($attendance->role->is($role));
    }

    public function test_adviser_and_dean_academic_papers(): void
    {
        $researchAdviser = ResearchAdviser::factory()->create();
        $technicalAdviser = TechnicalAdviser::factory()->create();
        $dean = Dean::factory()->create();

        $paper = AcademicPaper::factory()->create([
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'dean_id' => $dean->id,
        ]);

        foreach ([$researchAdviser, $technicalAdviser, $dean] as $entity) {
            $this->assertInstanceOf(HasMany::class, $entity->academicPapers());
            $this->assertTrue($entity->academicPapers->contains($paper));
        }
    }

    public function test_borrow_transaction_calculates_duration(): void
    {
        $transaction = $this->makeBorrowTransaction([
            'time_in' => now()->subHours(2),
            'time_out' => now(),
        ]);

        $transaction->calculateDuration();

        $this->assertSame(120, $transaction->duration_minutes);
    }
}
