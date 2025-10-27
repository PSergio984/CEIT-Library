<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use App\Models\Inventory;
use Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Title('Academic Paper List')]
class AcademicPaperIndex extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;
    public ?string $dept = null;
    public string $search = '';

    // Modal properties
    public bool $showModal = false;
    public ?AcademicPaper $selectedPaper = null;

    // QR Code Modal properties
    public bool $showQrModal = false;
    public ?string $qrCode = null;
    public $selectedCopy = null; // Use generic type since we don't know the exact copy structure

    public function updatingPerPage(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'publication_year', 'label' => 'Year'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'font-semibold'],
        ];
    }

    #[Computed]
    public function academicPapers()
    {
        $query = AcademicPaper::query()
            ->with(['copies' => function ($query) {
                $query->select('academic_paper_id', 'status');
            }])
            ->when($this->dept, function ($q) {
                $departmentName = $this->resolveDepartmentName($this->dept);
                if ($departmentName) {
                    $q->where('department', $departmentName);
                }
            })
            ->when($this->search, function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%');
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                }
            ]);

        if ($this->sortBy['column'] === 'status') {
            $query->orderBy('available_copies', $this->sortBy['direction']);
        } else {
            $query->orderBy(...array_values($this->sortBy));
        }

        $paginated = $query->paginate($this->perPage, pageName: 'academic-papers-index');

        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
            return $paper;
        });

        return $paginated;
    }

    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function showPaperDetails(AcademicPaper $academicPaper): void
    {
        $this->selectedPaper = $academicPaper->load('authors', 'copies');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedPaper = null;
    }

    public function requestQr(int $inventoryId): void
    {
        // 1. grab the copy (inventory row)
        $copy = Inventory::with('academicPaper')->find($inventoryId);

        if (!$copy) {
            session()->flash('error', 'Copy not found.');
            return;
        }

        // 2. must be available
        if (!$copy->isAvailable()) {
            session()->flash('error', 'This copy is not available.');
            return;
        }

        // Store the entire copy object, not just the ID
        $this->selectedCopy = $copy;

        // 3) Build signed payload with TTL (e.g., 5 minutes)
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(5);
        $payload = [
            'inventory_id' => $copy->id,
            'paper_id'     => $copy->academic_paper_id,
            'catalog_code' => $copy->academicPaper->catalog_code,
            'title'        => $copy->academicPaper->title,
            'requested_by' => Auth::id(),
            'iat'          => $issuedAt->timestamp,
            'exp'          => $expiresAt->timestamp,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig  = hash_hmac('sha256', $json, config('qr.secret', config('app.key')));
        $qrPayload = json_encode(['p' => $payload, 'sig' => $sig], JSON_UNESCAPED_SLASHES);

        // 4) Create SVG and base64 for modal
        $svg = QrCode::size(300)->generate($qrPayload);
        $this->qrCode = base64_encode($svg);

        $this->showQrModal = true;
    }

    public function closeQrModal(): void
    {
        $this->showQrModal = false;
        $this->qrCode = null;
        $this->selectedCopy = null;
    }

    public function downloadQr()
    {
        if (!$this->selectedCopy) {
            abort(400, 'No selected copy.');
        }
        abort_unless(Auth::check(), 403);

        // Get the copy ID from the object
        $copyId = is_object($this->selectedCopy) ? $this->selectedCopy->id : $this->selectedCopy;
        $copy = Inventory::with('academicPaper')->findOrFail($copyId);

        if (!$copy->isAvailable()) {
            abort(409, 'Copy no longer available.');
        }

        $paper = $copy->academicPaper;

        $payload = [
            'inventory_id' => $copy->id,
            'paper_id'     => $paper->id,
            'catalog_code' => $paper->catalog_code,
            'title'        => $paper->title,
            'requested_by' => Auth::id(),
            'iat'          => now()->timestamp,
            'exp'          => now()->addMinutes(5)->timestamp,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig  = hash_hmac('sha256', $json, config('qr.secret', config('app.key')));
        $qrPayload = json_encode(['p' => $payload, 'sig' => $sig], JSON_UNESCAPED_SLASHES);

        $filename = 'qr-code-inv-' . $copy->id . '.png';

        return response()->streamDownload(
            fn () => print QrCode::size(500)->format('png')->generate($qrPayload),
            $filename,
            ['Content-Type' => 'image/png']
        );
    }

    private function resolveDepartmentName(?string $dept): ?string
    {
        if (!$dept) {
            return null;
        }

        $mapping = config('departments.mapping', []);
        $validNames = config('departments.valid_names', []);

        if (isset($mapping[$dept])) {
            return $mapping[$dept];
        }

        if (in_array($dept, $validNames)) {
            return $dept;
        }

        return null;
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (!$this->selectedPaper || !$this->selectedPaper->department) {
            return '';
        }

        $icons = config('departments.icons', []);
        $department = $this->selectedPaper->department;

        return isset($icons[$department]) ? asset($icons[$department]) : '';
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'Available' => 'badge-success',
            'Borrowed' => 'badge-warning',
            default => 'badge-error',
        };
    }

    public function render()
    {
        return view('livewire.pages.student.academic-paper-index');
    }
}
