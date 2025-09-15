<?php

namespace App\Livewire\Pages;

use App\Models\Thesis;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class ShowThesis extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc',];

    public int $perPage = 2;

    public array $headers = [
        ['key' => 'id', 'label' => 'Copy Id'],
        ['key' => 'status', 'label' => 'Availability'],
        ['key' => 'action', 'label' => 'Action'],
    ];

    public Thesis $thesis;

    public function mount(Thesis $thesis)
    {
        $this->thesis = $thesis;
    }

    #[Computed]
    public function rows(): array
    {
        $copies = $this->thesis->copies()
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
        return view('livewire.pages.show-thesis');
    }
}
