<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class AdminAcademicPaperIndex extends AdminComponent
{
      use WithPagination;
      use Toast;
    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;

    #[Url]
    public string $search = '';
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
            ['key' => 'paper_type', 'label' => 'Paper Type'],
            ['key' => 'research_project_adviser', 'label' => 'Adviser'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'total_copies', 'label' => 'Total Copies'],
            ['key' => 'available_copies', 'label' => 'Available'],
        ];
    }

    public function search(){

    }
   #[Computed]
    public function academicPapers()
    {
        $query = AcademicPaper::query()
            ->with(['copies' => function($query) {
                $query->select('academic_paper_id', 'status');
            }])
            ->when($this->search, function($query) {
                $search = '%' . $this->search . '%';
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', $search)
                      ->orWhere('catalog_code', 'like', $search)
                      ->orWhere('research_project_adviser', 'like', $search)
                      ->orWhere('department', 'like', $search);
                });
            })
            ->withCount([
                'copies as total_copies',
                'copies as available_copies' => function($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy(...array_values($this->sortBy));

        return $query->paginate($this->perPage, pageName: 'academic-papers-index');
    }

    public function deleteAcademicPaper($id)
    {
        $academicPaper = AcademicPaper::find($id);
        if ($academicPaper) {
            $academicPaper->delete();
             $this->warning("$academicPaper->title deleted", 'Good bye!');
        } else {
            $this->warning("$academicPaper->title Not Found", 'Error!');
        }
    }
    public function render()
    {
        return view('livewire.pages.Admin.admin-academic-paper-index');
    }
}
