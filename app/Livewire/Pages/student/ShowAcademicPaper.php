<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ShowAcademicPaper extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc',];
    public int $perPage = 2;
    public bool $showModal = false;

    public array $headers = [
        ['key' => 'id', 'label' => 'Copy Id'],
        ['key' => 'status', 'label' => 'Availability'],
        ['key' => 'action', 'label' => 'Action'],
    ];

    public ?AcademicPaper $academicPaper = null;

    public function mount(AcademicPaper $academicPaper = null)
    {
        $this->academicPaper = $academicPaper;
    }

    public function showModal(AcademicPaper $academicPaper): void
    {
        $this->academicPaper = $academicPaper->load('authors', 'copies');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->academicPaper = null;
    }

    public function updatedShowModal(): void
    {
        if (!$this->showModal) {
            $this->academicPaper = null;
        }
    }

    #[Computed]
    public function rows(): array
    {
        if (!$this->academicPaper) {
            return [];
        }

        $copies = $this->academicPaper->copies()
            ->orderBy(...array_values($this->sortBy))
            ->get();

        return $copies->map(function ($copy) {
            return [
                'id' => $copy->id,
                'status' => $copy->status,
            ];
        })->toArray();
    }

    public function requestQr($id)
    {
    }
    public function render()
    {
        // Only render the view if we have an academic paper selected
        if ($this->academicPaper) {
            return view('livewire.pages.student.show-academic-paper');
        }

        // Return empty view if no paper selected
        return view('livewire.pages.student.show-academic-paper');
    }
}
