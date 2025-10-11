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
                // map short slugs to real department names if needed
                $map = [
                    'it' => 'Information Technology',
                    'ce' => 'Civil Engineering',
                    'ee' => 'Electrical Engineering',
                ];
                $value = $map[$this->dept] ?? $this->dept;
                $q->where('department', $value);
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

    #[Computed]
    public function departmentIcon(): string
    {
        if (!$this->selectedPaper || !$this->selectedPaper->department) {
            return '';
        }

        return match ($this->selectedPaper->department) {
            'Civil Engineering' => Vite::asset('public/images/aces.png'),
            'Electrical Engineering' => Vite::asset('public/images/ees.png'),
            'Information Technology' => Vite::asset('public/images/vits.png'),
            default => '',
        };
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
