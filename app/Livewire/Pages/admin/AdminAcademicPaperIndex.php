<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use App\Models\AcademicPaper;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Academic Paper List')]
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
        $this->resetPage('academic-papers-index');
    }

    public ?string $dept = null;
    public bool $deleteModal = false;
    public ?int $deleteId = null;
    public bool $formDrawer = false;
    public bool $isEditing = false;
    public AcademicPaperForm $form;

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->searchAdvisers();
        $this->searchDeans();
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'publication_year', 'label' => 'Year'],
            ['key' => 'paper_type', 'label' => 'Paper Type'],
            ['key' => 'total_copies', 'label' => 'Total Copies'],
            ['key' => 'available_copies', 'label' => 'Available'],
        ];
        $this->form->populateYearChoices();
    }

    public function search() {}


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
            ->when($this->search, function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('research_project_adviser', 'like', $search)
                        ->orWhere('catalog_code', 'like', $search);
                });
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

    // Reset pagination when dept or search changes
    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function deleteAcademicPaper($id)
    {
        $academicPaper = AcademicPaper::find($id);
        if ($academicPaper) {
            $academicPaper->delete();
            $this->warning("$academicPaper->title deleted", 'Good bye!');
        } else {
            $this->warning("Academic paper not found", 'Error!');
        }
    }

    // Open confirmation modal
    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->deleteModal = true;
    }

    // Perform deletion after confirmation
    public function performDelete(): void
    {
        if ($this->deleteId) {
            $academicPaper = AcademicPaper::find($this->deleteId);
            if ($academicPaper) {
                $title = $academicPaper->title;
                $academicPaper->delete();
                $this->warning("$title deleted", 'Good bye!');
            }
        }
        $this->deleteModal = false;
        $this->deleteId = null;
        $this->resetPage('academic-papers-index');
    }

    // Open drawer for creating new academic paper
    public function create(): void
    {
        $this->isEditing = false;
        $this->form->reset();
        $this->form->populateYearChoices();
        $this->searchAdvisers();
        $this->searchDeans();
        $this->formDrawer = true;
    }

    // Open drawer for editing existing academic paper
    public function edit(int $id): void
    {
        $academicPaper = AcademicPaper::with('authors')->findOrFail($id);
        $this->isEditing = true;
        $this->form->setAcademicPaper($academicPaper);
        $this->searchAdvisers();
        $this->searchDeans();
        $this->formDrawer = true;
    }

    // Save academic paper (create or update)
    public function saveAcademicPaper(): void
    {
        if ($this->isEditing) {
            $this->form->update();
            $this->success("{$this->form->academicPaper->catalog_code} updated", 'Updated Successfully!');
        } else {
            $paper = $this->form->store();
            $this->success("{$paper->catalog_code} created", 'Academic Paper Created Successfully!');
        }

        $this->formDrawer = false;
        $this->isEditing = false;
        $this->form->reset();
        $this->form->populateYearChoices();
        $this->searchAdvisers();
        $this->searchDeans();
        $this->resetPage('academic-papers-index');
    }


    // Search method for advisers
    public function searchAdvisers(string $value = '')
    {
        // Get search results
        $advisers = \App\Models\AcademicPaper::whereNotNull('research_project_adviser')
            ->where('research_project_adviser', 'like', "%{$value}%")
            ->distinct()
            ->pluck('research_project_adviser')
            ->filter()
            ->map(function ($adviser) {
                return ['id' => $adviser, 'name' => $adviser];
            })
            ->take(10);

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->research_project_adviser)) {
            $selectedOption = collect([['id' => $this->form->research_project_adviser, 'name' => $this->form->research_project_adviser]]);
            $advisers = $advisers->merge($selectedOption)->unique('id');
        }

        $this->form->adviser_options = $advisers;
        return $advisers->toArray();
    }

    // Search method for deans
    public function searchDeans(string $value = '')
    {
        // Get search results
        $deans = \App\Models\AcademicPaper::whereNotNull('dean')
            ->where('dean', 'like', "%{$value}%")
            ->distinct()
            ->pluck('dean')
            ->filter()
            ->map(function ($dean) {
                return ['id' => $dean, 'name' => $dean];
            })
            ->take(10);

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->dean)) {
            $selectedOption = collect([['id' => $this->form->dean, 'name' => $this->form->dean]]);
            $deans = $deans->merge($selectedOption)->unique('id');
        }

        $this->form->dean_options = $deans;
        return $deans->toArray();
    }

    public function render()
    {
        return view('livewire.pages.Admin.admin-academic-paper-index');
    }
}
