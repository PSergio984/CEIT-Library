<?php

namespace App\Livewire\Pages\admin;

use App\Livewire\Forms\RuleAndRegulationForm;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Livewire\Attributes\Title;

#[Title('Rules and Regulations')]
class AdminRuleAndRegulationIndex extends AdminComponent
{
    use WithPagination, Toast, AuthorizesRequests;

    public RuleAndRegulationForm $form;

    public array $sortBy = ['column' => 'rule_header_id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;
    public ?int $editingRuleId = null;
    public bool $openDrawer = false;
    public bool $openModal = false;
    public bool $isEdit = false;
    public string $search = '';
    #[Url(as: 'header')]
    public ?int $filterHeaderId = null;

    public bool $confirmDeleteModal = false;
    public ?int $deletingRuleId = null;
    public bool $myModal1 = false;

    // Check if user can edit (admin only)
    public function getCanEditProperty(): bool
    {
        return Gate::allows('manage-rules');
    }

    public function mount(): void
    {
        // Anyone with view-rules permission can access (Librarian or Admin)
        $this->authorize('view-rules');

        $this->headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16', 'sortable' => false],
            ['key' => 'ruleHeader.title', 'label' => 'Header', 'sortable' => true],
            ['key' => 'content', 'label' => 'Content', 'sortable' => true],
            ['key' => 'updated_at', 'label' => 'Updated', 'sortable' => true, 'class' => 'w-40'],
        ];
    }


    public function openCreateDrawer(): void
    {
        // Only admins can create rules
        $this->authorize('manage-rules');

        $this->isEdit = false;
        $this->editingRuleId = null;
        $this->form->reset();
        $this->openDrawer = true;
    }

    public function openEditDrawer(int $id): void
    {
        // Only admins can edit rules
        $this->authorize('manage-rules');

        $rule = RuleRegulation::findOrFail($id);
        $this->isEdit = true;
        $this->editingRuleId = $id;
        $this->form->rule_header_id = $rule->rule_header_id;
        $this->form->content = $rule->content;
        $this->openDrawer = true;
    }

    public function save(): void
    {
        // Only admins can save rules
        $this->authorize('manage-rules');

        if ($this->isEdit && $this->editingRuleId) {
            $this->form->update($this->editingRuleId);
            $this->success('Rule updated successfully');
        } else {
            $this->form->store();
            $this->success('Rule created successfully');
        }
        $this->openDrawer = false;
        $this->editingRuleId = null;
        $this->form->reset();
    }

    #[Computed]
    public function rules(): LengthAwarePaginator
    {
        $query = RuleRegulation::query()
            ->reorder()
            ->with(['ruleHeader' => fn($q) => $q->reorder()])
            ->when($this->filterHeaderId, fn($q, $id) => $q->where('rule_header_id', $id))
            ->when($this->search !== '', function ($q) {
                $s = "%{$this->search}%";
                $q->where(function ($q) use ($s) {
                    $q->where('content', 'like', $s)
                        ->orWhereHas('ruleHeader', fn($hq) => $hq->where('title', 'like', $s));
                });
            });

        $column = $this->sortBy['column'] ?? 'rule_header_id';
        $direction = $this->sortBy['direction'] ?? 'asc';

        if ($column === 'ruleHeader.title') {
            $query->orderBy(
                RuleHeader::select('title')
                    ->whereColumn('rule_headers.id', 'rule_regulations.rule_header_id'),
                $direction
            );
        } elseif ($column === 'content') {
            $query->orderByRaw('CASE WHEN content IS NULL OR TRIM(content) = "" THEN 1 ELSE 0 END')
                ->orderByRaw('LOWER(TRIM(content)) ' . ($direction === 'desc' ? 'DESC' : 'ASC'));
        } else {
            $query->orderBy($column, $direction);
        }

        return $query->paginate($this->perPage);
    }

    public function edit(int $id): void
    {
        // Only admins can edit rules
        $this->authorize('manage-rules');

        $rule = RuleRegulation::findOrFail($id);

        $this->isEdit = true;
        $this->editingRuleId = $id;
        $this->form->rule_header_id = $rule->rule_header_id;
        $this->form->content = $rule->content;

        $this->openDrawer = true;
    }

    public function update(): void
    {
        // Only admins can update rules
        $this->authorize('manage-rules');

        $this->form->update($this->editingRuleId);

        $this->success('Rule updated successfully');
        $this->openDrawer = false;
        $this->editingRuleId = null;
        $this->form->reset();
    }

    // Open confirmation modal
    public function confirmDelete(int $id): void
    {
        // Only admins can delete rules
        $this->authorize('manage-rules');

        $this->deletingRuleId = $id;
        $this->confirmDeleteModal = true;
    }

    // Perform deletion after confirmation
    public function deleteConfirmed(): void
    {
        // Only admins can delete rules
        $this->authorize('manage-rules');

        if ($this->deletingRuleId) {
            $rule = RuleRegulation::findOrFail($this->deletingRuleId);
            $rule->delete();
            $this->warning('Rule deleted successfully');
        }
        $this->confirmDeleteModal = false;
        $this->deletingRuleId = null;
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-rule-and-regulation-index', [
            'headers_list' => RuleHeader::orderBy('order')->get()
        ]);
    }
}
