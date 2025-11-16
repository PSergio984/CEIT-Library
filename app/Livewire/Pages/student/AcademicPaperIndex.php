<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use App\Models\Inventory;
use App\Traits\CreatesQrCanonicalMessage;
use Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Title('Academic Paper List')]
class AcademicPaperIndex extends Component
{
    use CreatesQrCanonicalMessage, WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public array $headers = [];

    public int $perPage = 10;

    public ?string $dept = null;

    public string $search = '';

    // Filters
    public string $statusFilter = '';

    public string $yearFilter = '';

    public string $departmentFilter = '';

    public string $paperTypeFilter = '';

    public string $yearFromFilter = '';

    public string $yearToFilter = '';

    // Store IDs only (modals controlled by Alpine.js)
    public ?int $selectedPaperId = null;

    // QR Code properties
    public ?string $qrCode = null;

    public ?int $selectedCopyId = null;

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
        // Optimize: Only eager load what's displayed in list view
        // Authors and full copy details are loaded lazily in detail modal
        $query = AcademicPaper::query()
            ->when($this->dept, function ($q) {
                $departmentName = $this->resolveDepartmentName($this->dept);
                if ($departmentName) {
                    $q->where('department', $departmentName);
                }
            })
            ->when($this->search, function ($q) {
                $search = '%' . $this->search . '%';
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', $search)
                        ->orWhere('catalog_code', 'like', $search)
                        ->orWhere('department', 'like', $search)
                        ->orWhereHas('authors', function ($q) use ($search) {
                            $q->where('name', 'like', $search);
                        });
                });
            })
            ->when($this->yearFilter, function ($q) {
                $q->where('publication_year', $this->yearFilter);
            })
            ->when($this->departmentFilter, function ($q) {
                $q->where('department', $this->departmentFilter);
            })
            ->when($this->paperTypeFilter, function ($q) {
                $q->where('paper_type', $this->paperTypeFilter);
            })
            ->when($this->yearFromFilter, function ($q) {
                $q->where('publication_year', '>=', $this->yearFromFilter);
            })
            ->when($this->yearToFilter, function ($q) {
                $q->where('publication_year', '<=', $this->yearToFilter);
            })
            // Apply status filter at query level for better performance
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'Available') {
                    $q->whereHas('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                } elseif ($this->statusFilter === 'Unavailable') {
                    $q->whereDoesntHave('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                }
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                },
            ]);

        if ($this->sortBy['column'] === 'status') {
            $query->orderBy('available_copies', $this->sortBy['direction']);
        } else {
            $query->orderBy(...array_values($this->sortBy));
        }

        $paginated = $query->paginate($this->perPage, pageName: 'academic-papers-index');

        // Transform to add computed status property
        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';

            return $paper;
        });

        return $paginated;
    }

    #[Computed(persist: true, cache: true)]
    public function availableYears()
    {
        // Lazy-loaded and cached for better initial load performance
        // Get min and max years from database
        $minYear = AcademicPaper::min('publication_year');
        $maxYear = AcademicPaper::max('publication_year');

        if (! $minYear || ! $maxYear) {
            return collect();
        }

        // Generate complete range from min to max (no gaps)
        return collect(range($maxYear, $minYear))->values();
    }

    #[Computed(persist: true, cache: true)]
    public function availableDepartments()
    {
        // Lazy-loaded and cached for better initial load performance
        return AcademicPaper::distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values();
    }

    #[Computed(persist: true, cache: true)]
    public function availablePaperTypes()
    {
        // Lazy-loaded and cached for better initial load performance
        return AcademicPaper::distinct()
            ->orderBy('paper_type')
            ->pluck('paper_type')
            ->filter()
            ->values();
    }

    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedPaperTypeFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearFromFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearToFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    // Clear all filters and reset to default state
    public function clearFilters(): void
    {
        $this->reset([
            'statusFilter',
            'paperTypeFilter',
            'departmentFilter',
            'yearFromFilter',
            'yearToFilter',
        ]);
        $this->resetPage('academic-papers-index');
    }

    public function showPaperDetails(int $paperId): void
    {
        $this->selectedPaperId = $paperId;
        $this->dispatch('open-paper-modal');
    }

    #[Computed]
    public function selectedPaper(): ?AcademicPaper
    {
        if (! $this->selectedPaperId) {
            return null;
        }

        return AcademicPaper::with([
            'authors' => fn($q) => $q->select('authors.id', 'authors.name'),
            'researchAdviser:id,name',
            'technicalAdviser:id,name',
            'dean:id,name',
            'copies' => fn($q) => $q->select('id', 'academic_paper_id', 'copy_number', 'status'),
        ])->find($this->selectedPaperId);
    }

    public function requestQr(int $inventoryId): void
    {
        // 1. grab the copy (inventory row)
        $copy = Inventory::with('academicPaper')->find($inventoryId);

        if (! $copy) {
            session()->flash('error', 'Copy not found.');

            return;
        }

        if (! $copy->isAvailable()) {
            session()->flash('error', 'This copy is not available.');

            return;
        }

        // Store only the copy ID to avoid serializing models in Livewire state
        $this->selectedCopyId = $copy->id;

        // 3) Build encrypted payload with TTL (e.g., 5 minutes)
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(5);
        $payload = [
            'inventory_id' => $copy->id,
            'paper_id' => $copy->academic_paper_id,
            'catalog_code' => $copy->academicPaper->catalog_code,
            'title' => $copy->academicPaper->title,
            'requested_by' => Auth::id(),
            'lat' => Auth::user()->email, // Add email for compatibility
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        // Encrypt the QR payload
        $qrPayload = $this->createEncryptedQrMessage($payload);

        // 4) Create SVG and base64 for modal
        $svg = QrCode::size(300)->generate($qrPayload);
        $this->qrCode = base64_encode($svg);

        $this->dispatch('open-qr-modal');
    }

    public function closeQrModal(): void
    {
        $this->qrCode = null;
        $this->selectedCopyId = null;
        $this->dispatch('close-qr-modal');
    }

    public function downloadQr()
    {
        if (! $this->selectedCopy) {
            abort(400, 'No selected copy.');
        }

        $copy = $this->selectedCopy;

        if (! $copy->isAvailable()) {
            abort(409, 'Copy no longer available.');
        }

        $paper = $copy->academicPaper;

        $payload = [
            'inventory_id' => $copy->id,
            'paper_id' => $paper->id,
            'catalog_code' => $paper->catalog_code,
            'title' => $paper->title,
            'requested_by' => Auth::id(),
            'lat' => Auth::user()->email, // Add email for compatibility
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ];

        // Encrypt the QR payload
        $qrPayload = $this->createEncryptedQrMessage($payload);
        $filename = 'qr-code-inv-' . $copy->id . '.png';

        return response()->streamDownload(
            fn() => print QrCode::size(500)->format('png')->generate($qrPayload),
            $filename,
            ['Content-Type' => 'image/png']
        );
    }

    private function resolveDepartmentName(?string $dept): ?string
    {
        if (! $dept) {
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
    public function selectedCopy()
    {
        if (! $this->selectedCopyId) {
            return null;
        }

        return Inventory::with('academicPaper')->find($this->selectedCopyId);
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (! $this->selectedPaper || ! $this->selectedPaper->department) {
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

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('livewire.pages.student.academic-paper-index-placeholder');
    }

    public function render()
    {
        return view('livewire.pages.student.academic-paper-index');
    }
}
