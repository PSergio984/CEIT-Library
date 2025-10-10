<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
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

    public function updatingPerPage(): void
    {
        $this->resetPage('theses-index');
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
            ['key' => 'total_copies', 'label' => 'Total Copies'],
            ['key' => 'available_copies', 'label' => 'Available'],
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
                'copies as total_copies',
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage, pageName: 'academic-papers-index');
    }

    // Reset pagination when dept changes
    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function render()
    {
        return view('livewire.pages.student.academic-paper-index');
    }
}
