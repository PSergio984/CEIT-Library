<?php

namespace App\Livewire\Pages;

use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AcademicPaper;

#[Title('Academic Paper List')]
class AcademicPaperIndex extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;

    public function updatingPerPage(): void
    {
        $this->resetPage('theses-index');
    }

    public function mount(){
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'publication_year', 'label' => 'Year'],
            ['key' => 'research_project_adviser', 'label' => 'Adviser'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'total_copies', 'label' => 'Total Copies'],
            ['key' => 'available_copies', 'label' => 'Available'],
        ];
    }

   #[Computed]
    public function academicPapers()
    {

        $query = AcademicPaper::query()
            ->with(['copies' => function($query) {
                $query->select('academic_paper_id', 'status');
            }])
            ->withCount([
                'copies as total_copies',
                'copies as available_copies' => function($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage, pageName: 'academic-papers-index');
    }

    public function render()
    {
        return view('livewire.pages.academic-paper-index');
    }
}
