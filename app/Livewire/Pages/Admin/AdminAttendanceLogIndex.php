<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Attendance Logs')]
#[Lazy]
class AdminAttendanceLogIndex extends AdminComponent
{
    use CreatesQrCanonicalMessage, Toast, WithPagination;

    public $perPage = 20;

    public $search = '';

    public $statusFilter = '';

    public $roleFilter = '';

    public $selectedDate = '';

    // QR Scanner modal properties
    public $showQrModal = false;

    public $scannedQrData = '';

    public $isProcessingQr = false;

    // Listeners for QR scanner events
    protected $listeners = ['attendanceRecorded'];

    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'w-40'],
        ['key' => 'role_name', 'label' => 'Role', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'scanned_by_name', 'label' => 'Scanned By', 'sortable' => true, 'class' => 'w-40'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'duration_minutes', 'label' => 'Duration', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-24'],
    ];

    public array $sortBy = ['column' => 'status', 'direction' => 'asc'];

    protected function getAttendancesQuery()
    {
        $search = trim($this->search);

        return Attendance::with(['user', 'scannedByLibrarian.user', 'scannedByAdmin', 'role'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    // Search student name (match full name or individual parts)
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                        // Search librarian name (match full name or individual parts)
                        ->orWhereHas('scannedByLibrarian.user', function ($librarianQuery) use ($search) {
                            $librarianQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        // Search admin name (match full name or individual parts)
                        ->orWhereHas('scannedByAdmin', function ($adminQuery) use ($search) {
                            $adminQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('attendances.status', $this->statusFilter))
            ->when($this->roleFilter, fn ($q) => $q->where('attendances.role_id', $this->roleFilter))
            ->when($this->selectedDate, fn ($q) => $q->whereDate('attendances.time_in', $this->selectedDate));
    }

    protected function applySorting($query)
    {
        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $column = $this->sortBy['column'];
            $direction = $this->sortBy['direction'];

            if ($column === 'user_name') {
                $query->join('users', 'attendances.user_id', '=', 'users.id')
                    ->orderBy('users.first_name', $direction)
                    ->select('attendances.*');
            } elseif ($column === 'role_name') {
                $query->leftJoin('roles', 'attendances.role_id', '=', 'roles.id')
                    ->orderBy('roles.name', $direction)
                    ->select('attendances.*');
            } elseif ($column === 'scanned_by_name') {
                $query->leftJoin('librarians', 'attendances.scanned_by', '=', 'librarians.id')
                    ->leftJoin('users as librarian_users', 'librarians.user_id', '=', 'librarian_users.id')
                    ->orderBy('librarian_users.first_name', $direction)
                    ->select('attendances.*');
            } else {
                $query->orderBy($column, $direction);
            }
        } else {
            $query->orderBy('time_in', 'desc');
        }

        return $query;
    }

    public function getAttendancesProperty()
    {
        $query = $this->getAttendancesQuery();

        $query = $this->applySorting($query);

        return $query->paginate($this->perPage)
            ->through(function ($attendance) {

                return [
                    'id' => $attendance->id,
                    'user_name' => trim(($attendance->user?->first_name ?? '').' '.($attendance->user?->last_name ?? '')) ?: 'N/A',
                    'role_name' => $attendance->role?->name ?? 'N/A',
                    'role_badge_color' => match (strtolower($attendance->role?->name ?? '')) {
                        'student' => 'badge-success',
                        'librarian' => 'badge-info',
                        'admin', 'super_admin', 'super admin' => 'badge-error',
                        default => 'badge-outline'
                    },
                    'scanned_by_name' => $attendance->scanned_by_name,
                    'user' => $attendance->user,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'duration_minutes' => $attendance->duration_minutes,
                    'status' => $attendance->status,
                    'original' => $attendance,
                ];
            });
    }

    public function getCurrentlyInLibraryProperty()
    {
        return Attendance::where('status', 'active')
            ->whereNotNull('time_in')
            ->whereDate('time_in', today())
            ->whereNull('time_out')
            ->count();
    }

    public function getTimedOutTodayProperty()
    {
        return Attendance::where('status', 'completed')
            ->whereDate('time_out', today())
            ->count();
    }

    public function getRolesProperty()
    {
        return Role::orderBy('name')->get();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedDate()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedDate = '';
        $this->statusFilter = '';
        $this->roleFilter = '';
        $this->sortBy = ['column' => 'status', 'direction' => 'desc'];
        $this->resetPage();
    }

    public function openScanner()
    {
        $this->showQrModal = true;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-opened');
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-closed');
    }

    public function processScannedQr($qrData)
    {
        Log::info('=== Attendance QR Processing Started ===');
        Log::info('Raw QR Data:', ['data' => $qrData]);

        $this->isProcessingQr = true;

        try {
            $this->scannedQrData = $qrData;

            // Basic validation
            $data = trim($qrData);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            // Decrypt and validate the attendance data
            $decryptedData = $this->decryptAndValidateAttendanceData($data);

            if ($decryptedData === 'invalid') {
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or network issues. Please try generating a new QR code.', 'Invalid QR Code');
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            // Process the attendance
            $result = $this->processAttendance($decryptedData);

            if ($result['success']) {
                Log::info('Attendance recorded successfully', [
                    'user_id' => $decryptedData['user_id'],
                    'action' => $result['action'],
                ]);

                $this->success($result['message'], $result['title']);
                $this->dispatch('attendanceRecorded', attendance: $result['attendance']);
                $this->resetPage();
                $this->isProcessingQr = false;

                return ['found' => true];
            } else {
                $this->error($result['message'], $result['title']);
                $this->isProcessingQr = false;

                return ['found' => false];
            }
        } catch (\Exception $e) {
            Log::error('QR Scanner Error: '.$e->getMessage(), [
                'exception' => $e,
                'data_length' => strlen($qrData ?? ''),
            ]);

            $this->error('An error occurred while processing the QR code', 'System Error');
            $this->isProcessingQr = false;

            return ['found' => false];
        }
    }

    /**
     * Decrypt and validate the attendance QR code data
     * Updated to match v5 QR format (no timestamp, permanent QR codes)
     * Note: Nonce is used for HMAC integrity verification only, not for replay prevention.
     * The QR code is permanent and can be used unlimited times.
     *
     * @return array|string 'invalid' for validation failures, array for valid data
     */
    private function decryptAndValidateAttendanceData(string $encryptedData)
    {
        try {
            // Decrypt the data
            $decryptedJson = Crypt::decryptString($encryptedData);
            $data = json_decode($decryptedJson, true);

            // Validate HMAC secret
            $secret = config('app.qr_hmac_secret');
            if (! is_string($secret) || strlen($secret) < 16) {
                Log::error('QR HMAC secret missing or insecure');

                return 'invalid';
            }

            // Validate JSON structure (v5 format: user_id, user, hash, nonce - no timestamp)
            if (! is_array($data) || ! isset($data['user_id'], $data['user'], $data['hash'], $data['nonce'])) {
                Log::warning('Invalid QR code structure', ['data_keys' => array_keys($data ?? [])]);

                return 'invalid';
            }

            // Note: Nonce is used for HMAC integrity verification only
            // QR codes are now permanent (unlimited use) - no replay attack check needed
            // The attendance logic itself prevents duplicate active sessions

            // Verify hash for tamper protection covering entire payload
            // Remove hash from data before creating canonical message to avoid circular dependency
            $dataForCanonical = $data;
            unset($dataForCanonical['hash']);
            $canonicalMessage = $this->createCanonicalMessage($dataForCanonical);
            $expectedHash = hash_hmac('sha256', $canonicalMessage, $secret);
            if (! hash_equals($expectedHash, $data['hash'])) {
                Log::warning('QR code hash mismatch - possible tampering detected', [
                    'expected' => substr($expectedHash, 0, 16),
                    'received' => substr($data['hash'], 0, 16),
                ]);

                return 'invalid';
            }

            $user = User::find($data['user_id']);
            if (! $user) {
                Log::warning('User not found in QR code', ['user_id' => $data['user_id']]);

                return 'invalid';
            }

            // Check rate limiting per user (prevent rapid repeated scans)
            $rateLimitKey = 'qr_rate_limit:'.$data['user_id'];
            $recentScans = Cache::get($rateLimitKey, 0);

            if ($recentScans >= 60) { // Max 60 scans per minute for testing
                Log::warning('Rate limit exceeded for user', [
                    'user_id' => $data['user_id'],
                    'scan_count' => $recentScans,
                ]);

                return 'invalid';
            }

            // Increment rate limit counter (1 minute TTL)
            Cache::put($rateLimitKey, $recentScans + 1, 60);

            // QR code validated successfully - permanent QR codes have unlimited usage
            Log::info('QR code validated successfully', [
                'user_id' => $user->id,
            ]);

            return [
                'user_id' => $data['user_id'],
                'user' => $user,
            ];
        } catch (DecryptException $e) {
            Log::warning('QR code decryption failed - possible tampering', ['error' => $e->getMessage()]);

            return 'invalid';
        } catch (\Exception $e) {
            Log::error('Unexpected error during QR validation', ['error' => $e->getMessage()]);

            return 'invalid';
        }
    }

    /**
     * Process the attendance based on scanned QR data
     * Copied from QrScanner component
     */
    private function processAttendance(array $data): array
    {
        $userId = $data['user_id'];
        $user = $data['user'];

        // Get the current user who is scanning
        $currentUser = Auth::user();

        // Get the librarian ID if current user has an active librarian duty
        // scanned_by must reference librarians.id, not users.id
        $scannedBy = $currentUser?->getActiveLibrarianDuty()?->id;

        // If no librarian duty but user has admin access, store admin user ID
        $scannedByAdminId = null;
        if (! $scannedBy && $currentUser?->hasAdminAccess()) {
            $scannedByAdminId = $currentUser->id;
        }

        // Check if user has an active session
        $activeSession = Attendance::getActiveSession($userId);

        if ($activeSession) {
            // User is checking out (time out)
            try {
                return DB::transaction(function () use ($activeSession, $user) {
                    $activeSession->time_out = Carbon::now();
                    $activeSession->status = 'completed';
                    $activeSession->calculateDuration();
                    $activeSession->save();

                    $minutes = (int) $activeSession->duration_minutes;
                    $durationText = $this->formatDuration($minutes);

                    // Create check-out notification for the user
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'attendance_checkout',
                        'title' => 'Library Check-out Successful',
                        'message' => "You checked out of the library. Total time: {$durationText}. Thank you for visiting!",
                        'data' => [
                            'attendance_id' => $activeSession->id,
                            'time_in' => $activeSession->time_in->format('M d, Y h:i A'),
                            'time_out' => $activeSession->time_out->format('M d, Y h:i A'),
                            'duration_minutes' => $minutes,
                            'duration_text' => $durationText,
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => "Goodbye, {$user->first_name}! You stayed for {$durationText}.",
                        'title' => 'Check-out Successful',
                        'attendance' => $activeSession,
                        'action' => 'checkout',
                    ];
                });
            } catch (\Exception $e) {
                Log::error('Check-out transaction failed', [
                    'user_id' => $activeSession->user_id,
                    'attendance_id' => $activeSession->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-out: {$e->getMessage()}. Please contact the librarian for assistance.",
                    'title' => 'Check-out Failed',
                ];
            }
        } else {
            // User is checking in (time in)
            try {
                return DB::transaction(function () use ($userId, $scannedBy, $scannedByAdminId, $user) {
                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'role_id' => $user->role_id,
                        'scanned_by' => $scannedBy,
                        'scanned_by_admin_id' => $scannedByAdminId,
                        'time_in' => Carbon::now(),
                        'status' => 'active',
                    ]);

                    // Create check-in notification for the user
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => 'attendance_checkin',
                        'title' => 'Library Check-in Successful',
                        'message' => "Welcome to the library! You checked in at {$attendance->time_in->format('h:i A')}. Enjoy your time!",
                        'data' => [
                            'attendance_id' => $attendance->id,
                            'time_in' => $attendance->time_in->format('M d, Y h:i A'),
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => "Welcome, {$user->first_name}! You've been checked in successfully.",
                        'title' => 'Check-in Successful',
                        'attendance' => $attendance,
                        'action' => 'checkin',
                    ];
                });
            } catch (\Exception $e) {
                Log::error('Check-in transaction failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => "Database error during check-in: {$e->getMessage()}. Please contact the librarian for assistance.",
                    'title' => 'Check-in Failed',
                ];
            }
        }
    }

    /**
     * Format duration in a human-readable way
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return 'less than 1 minute';
        } elseif ($minutes < 60) {
            return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        } else {
            $hours = (int) floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            $hoursText = $hours.' '.($hours === 1 ? 'hour' : 'hours');
            if ($remainingMinutes > 0) {
                return $hoursText.' and '.$remainingMinutes.' '.($remainingMinutes === 1 ? 'minute' : 'minutes');
            }

            return $hoursText;
        }
    }

    public function attendanceRecorded()
    {
        // Refresh the attendance list after successful recording
        $this->resetPage();

        // The toast notification is already handled by the QrScanner component
        // Just refresh the page data
        $this->dispatch('$refresh');
    }

    public function exportPdf()
    {
        // Get all attendances matching current filters (no pagination)
        $query = $this->getAttendancesQuery();

        $query = $this->applySorting($query);

        $attendances = $query->get()->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'user_name' => trim(($attendance->user?->first_name ?? '').' '.($attendance->user?->last_name ?? '')) ?: 'N/A',
                'role_name' => $attendance->role?->name ?? 'N/A',
                'scanned_by_name' => $attendance->scanned_by_name,
                'time_in' => $attendance->time_in,
                'time_out' => $attendance->time_out,
                'duration_minutes' => $attendance->duration_minutes,
                'status' => $attendance->status,
            ];
        });

        // Build filter description for PDF
        $filterDescription = [];
        if ($this->search) {
            $filterDescription[] = 'Search: '.$this->search;
        }
        if ($this->statusFilter) {
            $statusName = $this->statusFilter === 'active' ? 'Active' : 'Completed';
            $filterDescription[] = 'Status: '.$statusName;
        }
        if ($this->roleFilter) {
            $role = Role::find($this->roleFilter);
            $filterDescription[] = 'Role: '.($role?->name ?? 'Unknown');
        }
        if ($this->selectedDate) {
            $filterDescription[] = 'Date: '.date('M d, Y', strtotime($this->selectedDate));
        }

        $filterText = ! empty($filterDescription) ? implode(' | ', $filterDescription) : 'All Records';

        // Generate PDF
        $pdf = Pdf::loadView('pdf.attendance-log', [
            'attendances' => $attendances,
            'filterText' => $filterText,
            'generatedAt' => now()->format('M d, Y h:i A'),
            'totalRecords' => $attendances->count(),
        ]);

        $filename = 'attendance-log-'.now()->format('Y-m-d-His').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading attendance logs...',
            'subtext' => 'Please wait while we fetch the attendance data',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-attendance-log-index');
    }
}
