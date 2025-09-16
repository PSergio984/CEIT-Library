<?php

namespace App\Livewire\Pages;

use App\Models\AcademicPaper;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class ShowAcademicPaper extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc',];

    public int $perPage = 2;

    public array $headers = [
        ['key' => 'id', 'label' => 'Copy Id'],
        ['key' => 'status', 'label' => 'Availability'],
        ['key' => 'action', 'label' => 'Action'],
    ];

    public AcademicPaper $academicPaper;

    public function mount(AcademicPaper $academicPaper)
    {
        $this->academicPaper = $academicPaper;
    }

    #[Computed]
    public function rows(): array
    {
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
    public function requestQr($id) {

    }
    public function render()
    {
        return view('livewire.pages.show-academic-paper');
    }
}
