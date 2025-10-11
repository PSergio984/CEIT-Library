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
    public bool $isModalOpen = false;

    public array $headers = [
        ['key' => 'id', 'label' => 'Copy Id'],
        ['key' => 'status', 'label' => 'Availability'],
        ['key' => 'action', 'label' => 'Action'],
    ];

    public ?AcademicPaper $academicPaper = null;

    public function mount(AcademicPaper $academicPaper = null)
    {
        if ($academicPaper) {
            $this->academicPaper = $academicPaper->load('authors', 'copies');
            $this->isModalOpen = true;
        }
    }

    public function openModal(AcademicPaper $academicPaper): void
    {
        $this->academicPaper = $academicPaper->load('authors', 'copies');
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->academicPaper = null;
    }

    public function updatedIsModalOpen(): void
    {
        if (!$this->isModalOpen) {
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

    public function requestQr($_id) {}
    public function render()
    {
        return view('livewire.pages.student.show-academic-paper');
    }
}
