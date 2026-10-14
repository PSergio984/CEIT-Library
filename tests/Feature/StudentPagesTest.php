<?php

namespace Tests\Feature;

use App\Livewire\Pages\Student\AcademicPaperIndex;
use App\Livewire\Pages\Student\CreditScoreHistory;
use App\Livewire\Pages\Student\ShowAcademicPaper;
use App\Livewire\Pages\Student\StudentDashboard;
use App\Livewire\QrScanner;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use App\Services\AttendanceService;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPagesTest extends TestCase
{
    protected bool $disableLivewireLazyLoading = true;

    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $studentRole = Role::firstOrCreate(
            ['name' => 'student'],
            ['display_name' => 'Student', 'description' => 'Student']
        );
        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);

        $this->student = User::factory()->create(['role_id' => $studentRole->id]);
    }

    public function test_credit_score_history_lists_violation_entries(): void
    {
        ViolationTransaction::factory()->create([
            'user_id' => $this->student->id,
            'violation_id' => Violation::factory()->create([
                'name' => 'Late Return',
                'penalty_score' => 15,
            ])->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(CreditScoreHistory::class)
            ->assertSee('Late Return');
    }

    public function test_student_dashboard_lists_violation_entries(): void
    {
        ViolationTransaction::factory()->create([
            'user_id' => $this->student->id,
            'violation_id' => Violation::factory()->create([
                'name' => 'Damaged Book',
                'penalty_score' => 20,
            ])->id,
        ]);

        Livewire::actingAs($this->student)
            ->test(StudentDashboard::class)
            ->assertSee('Damaged Book');
    }

    public function test_request_qr_builds_payload_for_available_copy(): void
    {
        $paper = AcademicPaper::factory()->create();
        $copy = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
            'status' => 'Available',
        ]);

        Livewire::actingAs($this->student)
            ->test(AcademicPaperIndex::class)
            ->call('requestQr', $copy->id)
            ->assertSet('selectedCopyId', $copy->id);
    }

    public function test_show_academic_paper_return_qr_for_active_borrow(): void
    {
        $paper = AcademicPaper::factory()->create();
        $copy = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
            'status' => 'Unavailable',
        ]);
        BorrowTransaction::factory()->create([
            'user_id' => $this->student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $copy->id,
            'status' => 'started',
            'time_in' => now()->subHour(),
            'time_out' => null,
            'expires_at' => now()->addHours(4),
        ]);

        Livewire::actingAs($this->student)
            ->test(ShowAcademicPaper::class, ['academicPaper' => $paper])
            ->call('showReturnQr', $copy->id)
            ->assertSet('returnQrPaperTitle', $paper->title);
    }

    public function test_qr_scanner_handles_service_exceptions(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $this->mock(AttendanceService::class, function ($mock) {
            $mock->shouldReceive('decryptAndValidateAttendanceData')
                ->andThrow(new \RuntimeException('decrypt blew up'));
        });

        Livewire::actingAs($admin)
            ->test(QrScanner::class)
            ->call('handleScan', 'garbage-data')
            ->assertSet('hasError', true)
            ->call('handleFileUploadScan', 'garbage-data')
            ->assertSet('hasError', true);
    }

    public function test_qr_code_download_streams_response(): void
    {
        $paper = AcademicPaper::factory()->create();
        $copy = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => 1,
            'status' => 'Available',
        ]);

        $this->actingAs($this->student)
            ->get(route('qr-code.download', $copy->id))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
