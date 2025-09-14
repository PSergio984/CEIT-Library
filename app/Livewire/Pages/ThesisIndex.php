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
            ['key' => 'total_copies', 'label' => 'Total Copies'],
            ['key' => 'available_copies', 'label' => 'Available'],
            ['key' => 'status_display', 'label' => 'Status'],
        ];
    }

    #[Computed]
    public function theses()
    {
        return Thesis::query()
            ->with(['copies' => function($query) {
                $query->select('thesis_id', 'status');
            }])
            ->withCount([
                'copies as total_copies',
                'copies as available_copies' => function($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage, pageName: 'theses-index')
            ->through(function ($thesis) {
                // Add computed status display
                $thesis->status_display = $this->getStatusDisplay($thesis);
                return $thesis;
            });
    }

    private function getStatusDisplay($thesis)
    {
        if ($thesis->available_copies > 0) {
            return "Available ({$thesis->available_copies}/{$thesis->total_copies})";
        } elseif ($thesis->total_copies > 0) {
            return "All Reserved ({$thesis->total_copies} copies)";
        } else {
            return "No Copies";
        }
    }

    public function render()
    {
        return view('livewire.pages.thesis-index');
    }
}
