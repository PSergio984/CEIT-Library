<?php

namespace App\Livewire\Pages\Admin;

use App\Rules\ProperName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Manage Advisers, Deans & Authors')]
#[Lazy]
class AdminAdvisersDeans extends AdminComponent
{
    use Toast, WithPagination;

    public function mount()
    {
        $this->authorizeAccess();
    }

    // Active tab: 'research', 'technical', 'deans', or 'authors'
    public string $activeTab = 'research';

    // Search and filters
    public string $search = '';

    // Modal visibility
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    // Form data (primitives only)
    public ?int $editingId = null;

    #[Validate]
    public string $name = '';

    public ?int $deleteId = null;

    // Store original name for dirty checking (must be public to survive hydration)
    public ?string $originalName = null;

    // Table headers
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'papers_count', 'label' => 'Papers Count', 'class' => 'w-32'],
        ['key' => 'created_at', 'label' => 'Added', 'sortable' => true, 'class' => 'w-40'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-32'],
    ];

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    #[Computed]
    public function entries()
    {
        // Validate table name before use
        $allowedTables = ['research_advisers', 'technical_advisers', 'deans', 'authors'];
        $table = $this->getTableName();

        if (! in_array($table, $allowedTables, true)) {
            \Log::warning('Invalid table name requested', ['table' => $table, 'activeTab' => $this->activeTab]);
            throw new \InvalidArgumentException('Invalid data source requested.');
        }

        $relatedColumn = $this->getRelatedColumn();

        // Whitelist allowed columns and directions
        $allowedColumns = ['id', 'name', 'papers_count', 'created_at'];
        $allowedDirections = ['asc', 'desc'];

        // Normalize and validate requested column
        $requestedColumn = $this->sortBy['column'] ?? 'name';
        $column = in_array($requestedColumn, $allowedColumns, true) ? $requestedColumn : 'name';

        // Normalize and validate requested direction
        $requestedDirection = strtolower($this->sortBy['direction'] ?? 'asc');
        $direction = in_array($requestedDirection, $allowedDirections, true) ? $requestedDirection : 'asc';

        // Use parameter binding for search pattern
        $searchPattern = $this->search ? '%'.$this->search.'%' : null;

        // Authors use a pivot table, so join differently
        if ($this->activeTab === 'authors') {
            return DB::table($table)
                ->select([
                    "{$table}.id",
                    "{$table}.name",
                    "{$table}.created_at",
                    DB::raw('COUNT(academic_paper_authors.academic_paper_id) as papers_count'),
                ])
                ->leftJoin('academic_paper_authors', 'academic_paper_authors.author_id', '=', "{$table}.id")
                ->when($searchPattern, function ($query) use ($table, $searchPattern) {
                    $query->where("{$table}.name", 'like', $searchPattern);
                })
                ->groupBy("{$table}.id", "{$table}.name", "{$table}.created_at")
                ->orderBy($column, $direction)
                ->paginate(20);
        }

        return DB::table($table)
            ->select([
                "{$table}.id",
                "{$table}.name",
                "{$table}.created_at",
                DB::raw('COUNT(academic_papers.id) as papers_count'),
            ])
            ->leftJoin('academic_papers', "academic_papers.{$relatedColumn}", '=', "{$table}.id")
            ->when($searchPattern, function ($query) use ($table, $searchPattern) {
                $query->where("{$table}.name", 'like', $searchPattern);
            })
            ->groupBy("{$table}.id", "{$table}.name", "{$table}.created_at")
            ->orderBy($column, $direction)
            ->paginate(20);
    }

    #[Computed]
    public function totalCount()
    {
        // Don't cache - needs to be accurate per tab
        return DB::table($this->getTableName())->count();
    }

    private function getTableName(): string
    {
        return match ($this->activeTab) {
            'research' => 'research_advisers',
            'technical' => 'technical_advisers',
            'deans' => 'deans',
            'authors' => 'authors',
            default => 'research_advisers',
        };
    }

    private function getRelatedColumn(): string
    {
        return match ($this->activeTab) {
            'research' => 'research_adviser_id',
            'technical' => 'technical_adviser_id',
            'deans' => 'dean_id',
            'authors' => 'author_id',
            default => 'research_adviser_id',
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
        // Close any open modals first
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->reset(['name', 'editingId', 'originalName']);
        $this->resetValidation(); // Clear any lingering validation errors
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id)
    {
        // Close any open modals first
        $this->showCreateModal = false;
        $this->showDeleteModal = false;
        $this->resetValidation(); // Clear any lingering validation errors

        $entry = DB::table($this->getTableName())->find($id);

        if (! $entry) {
            $this->error('Entry not found');

            return;
        }

        $this->editingId = $id;
        $this->name = $entry->name;
        $this->originalName = trim($entry->name); // Store trimmed original for consistent comparison
        $this->showEditModal = true;
    }

    public function save()
    {
        $this->authorize('manage-advisers-deans');

        // Trim the name before validation to prevent whitespace-only changes from being considered dirty
        $this->name = trim($this->name);

        $this->validate();

        $table = $this->getTableName();
        $trimmedName = $this->name; // Already trimmed above

        if ($this->editingId) {
            DB::table($table)->where('id', $this->editingId)->update([
                'name' => $trimmedName,
                'updated_at' => now(),
            ]);
            $this->success('Updated successfully!');
        } else {
            DB::table($table)->insert([
                'name' => $trimmedName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->success('Created successfully!');
        }

        $this->clearCaches();
        $this->closeModals();
    }

    /**
     * Get human-readable entity type for messages
     */
    private function getEntityType(): string
    {
        return match ($this->activeTab) {
            'research' => 'research adviser',
            'technical' => 'technical adviser',
            'deans' => 'dean',
            'authors' => 'author',
            default => 'entry',
        };
    }

    public function confirmDelete(int $id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorize('manage-advisers-deans');

        if (! $this->deleteId) {
            $this->closeModals();

            return;
        }

        $table = $this->getTableName();
        $relatedColumn = $this->getRelatedColumn();

        // Check if entry is being used
        if ($this->activeTab === 'authors') {
            // Authors use a pivot table
            $inUse = DB::table('academic_paper_authors')
                ->where('author_id', $this->deleteId)
                ->exists();
        } else {
            $inUse = DB::table('academic_papers')
                ->where($relatedColumn, $this->deleteId)
                ->exists();
        }

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
        $this->reset(['name', 'editingId', 'deleteId', 'originalName']);
        $this->resetValidation(); // Clear validation errors when closing modals
    }

    public function rules(): array
    {
        $entityType = $this->getEntityType();

        // Build unique rule conditionally: exclude ID only when editing
        $uniqueRule = 'unique:'.$this->getTableName().',name';
        if ($this->editingId) {
            $uniqueRule .= ','.$this->editingId.',id';
        }

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                new ProperName,
                $uniqueRule,
            ],
        ];
    }

    public function messages(): array
    {
        $entityType = $this->getEntityType();

        return [
            'name.required' => "The {$entityType} name is required.",
            'name.string' => "The {$entityType} name must be valid text.",
            'name.min' => "The {$entityType} name must be at least 2 characters.",
            'name.max' => "The {$entityType} name cannot exceed 255 characters.",
            'name.unique' => "This {$entityType} name already exists.",
        ];
    }

    #[Computed]
    public function isFormValid(): bool
    {
        $name = trim($this->name ?? '');

        // Check if name is filled and valid
        // Must be 2-255 characters AND contain at least 2 letters (matching ProperName rule)
        $lettersOnly = preg_replace('/[^\p{L}]/u', '', $name);
        $letterCount = mb_strlen($lettersOnly);

        $nameValid = ! empty($name)
            && strlen($name) >= 2
            && strlen($name) <= 255
            && $letterCount >= 2; // ProperName requires at least 2 letters, not just 2 characters

        // Check for validation errors
        $hasErrors = $this->getErrorBag()->has('name');

        $fieldsValid = $nameValid && ! $hasErrors;

        // For edit mode, also require form to be dirty
        if ($this->editingId) {
            return $fieldsValid && $this->isFormDirty();
        }

        return $fieldsValid;
    }

    #[Computed]
    public function isFormDirty(): bool
    {
        if (! $this->editingId || $this->originalName === null) {
            return false;
        }

        // Compare trimmed values to match the save() method behavior
        // This ensures consistency: if trimmed values are the same, form is not dirty
        return trim($this->name) !== $this->originalName;
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
