<?php

namespace App\Livewire\Pages;

use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Thesis;

#[Title('Thesis List')]
class ThesisIndex extends Component
{
    use WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;

    public function mount(){
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'year', 'label' => 'Year'],
            ['key' => 'research_project_adviser', 'label' => 'Adviser'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    #[Computed]
    public function theses()
    {
        return Thesis::query()
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage, pageName: 'theses-index');
    }

    public function render()
    {
        return view('livewire.pages.thesis-index');
    }
}
