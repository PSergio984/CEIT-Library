<?php

namespace App\Livewire\Pages\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Manage Advisers & Deans')]
#[Lazy]
class AdminAdvisersDeans extends AdminComponent
{
    use WithPagination, Toast;

    // Active tab: 'research', 'technical', or 'deans'
    public string $activeTab = 'research';

    // Search and filters
    public string $search = '';

    // Modal visibility
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    // Form data (primitives only)
    public ?int $editingId = null;
    public string $name = '';
    public ?int $deleteId = null;

    // Table headers
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'papers_count', 'label' => 'Papers Count', 'class' => 'w-32'],
        ['key' => 'created_at', 'label' => 'Added', 'sortable' => true, 'class' => 'w-40'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-32'],
    ];

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    #[Computed(persist: true, seconds: 5)]
    public function entries()
    {
        $table = $this->getTableName();
        $relatedColumn = $this->getRelatedColumn();

        return DB::table($table)
            ->select([
                "{$table}.id",
                "{$table}.name",
                "{$table}.created_at",
                DB::raw("COUNT(academic_papers.id) as papers_count")
            ])
            ->leftJoin('academic_papers', "academic_papers.{$relatedColumn}", '=', "{$table}.id")
            ->when($this->search, function ($query) use ($table) {
                $query->where("{$table}.name", 'like', "%{$this->search}%");
            })
            ->groupBy("{$table}.id", "{$table}.name", "{$table}.created_at")
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(20);
    }

    #[Computed(persist: true, seconds: 60)]
    public function totalCount()
    {
        return Cache::remember(
            "advisers_deans_total_{$this->activeTab}",
            300,
            fn() => DB::table($this->getTableName())->count()
        );
    }

    private function getTableName(): string
    {
        return match ($this->activeTab) {
            'research' => 'research_advisers',
            'technical' => 'technical_advisers',
            'deans' => 'deans',
        };
    }

    private function getRelatedColumn(): string
    {
        return match ($this->activeTab) {
            'research' => 'research_adviser_id',
            'technical' => 'technical_adviser_id',
            'deans' => 'dean_id',
        };
    }

    public function updatedActiveTab()
    {
        $this->reset(['search', 'sortBy']);
        $this->resetPage();
        unset($this->entries);
    }

    public function updatedSearch()
    {
        $this->resetPage();
        unset($this->entries);
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'editingId']);
        $this->showCreateModal = true;
        $this->skipRender();
    }

    public function openEditModal(int $id)
    {
        $entry = DB::table($this->getTableName())->find($id);

        if (!$entry) {
            $this->error('Entry not found');
            return;
        }

        $this->editingId = $id;
        $this->name = $entry->name;
        $this->showEditModal = true;
        $this->skipRender();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:' . $this->getTableName() . ',name,' . $this->editingId,
        ]);

        $table = $this->getTableName();

        if ($this->editingId) {
            DB::table($table)->where('id', $this->editingId)->update([
                'name' => $this->name,
                'updated_at' => now(),
            ]);
            $this->success('Updated successfully!');
        } else {
            DB::table($table)->insert([
                'name' => $this->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->success('Created successfully!');
        }

        $this->clearCaches();
        $this->closeModals();
    }

    public function confirmDelete(int $id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
        $this->skipRender();
    }

    public function delete()
    {
        if (!$this->deleteId) return;

        $table = $this->getTableName();
        $relatedColumn = $this->getRelatedColumn();

        // Check if entry is being used
        $inUse = DB::table('academic_papers')
            ->where($relatedColumn, $this->deleteId)
            ->exists();

        if ($inUse) {
            $this->error('Cannot delete: This entry is being used by academic papers');
            $this->showDeleteModal = false;
            $this->deleteId = null;
            return;
        }

        DB::table($table)->where('id', $this->deleteId)->delete();

        $this->success('Deleted successfully!');
        $this->clearCaches();
        $this->closeModals();
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->reset(['name', 'editingId', 'deleteId']);
        $this->skipRender();
    }

    private function clearCaches()
    {
        Cache::forget("advisers_deans_total_{$this->activeTab}");
        unset($this->entries);
        unset($this->totalCount);
    }

    public function placeholder()
    {
        return <<<'HTML'
        <div class="p-6">
            <div class="flex items-center justify-center h-96">
                <span class="loading loading-spinner loading-lg"></span>
            </div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-advisers-deans');
    }
}
