<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use Illuminate\Support\Facades\Vite;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

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
            ['key' => 'actions', 'label' => 'Actions', 'sortable' => false],
        ];
    }

    #[Computed]
    public function academicPapers()
    {

        $query = AcademicPaper::query()
            ->with(['copies' => function ($query) {
                $query->select('academic_paper_id', 'status');
            }])
            // filter by department if provided via route slug
            ->when($this->dept, function ($q) {
                $departmentName = $this->resolveDepartmentName($this->dept);
                if ($departmentName) {
                    $q->where('department', $departmentName);
                }
            })
            // search functionality
            ->when($this->search, function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%');
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                }
            ]);

        // Handle sorting - if sorting by status, sort by available_copies instead
        if ($this->sortBy['column'] === 'status') {
            $query->orderBy('available_copies', $this->sortBy['direction']);
        } else {
            $query->orderBy(...array_values($this->sortBy));
        }

        $paginated = $query->paginate($this->perPage, pageName: 'academic-papers-index');

        // Transform items to include status as a direct property
        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
            return $paper;
        });

        return $paginated;
    }

    // Reset pagination when dept changes
    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    // Reset pagination when search changes
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

    public function requestQr(): void
    {

        // TODO: Implement QR code request functionality
        // This could generate a QR code for the specific copy
        // or redirect to a QR generation page
    }

    /**
     * Resolve department name from slug or validate existing name
     */
    private function resolveDepartmentName(?string $dept): ?string
    {
        if (!$dept) {
            return null;
        }

        $mapping = config('departments.mapping', []);
        $validNames = config('departments.valid_names', []);

        // Check if it's a known slug
        if (isset($mapping[$dept])) {
            return $mapping[$dept];
        }

        // Check if it's already a valid department name
        if (in_array($dept, $validNames)) {
            return $dept;
        }

        // Invalid input, return null to skip filtering
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

    /**
     * Get the appropriate badge class for a given status.
     */
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
