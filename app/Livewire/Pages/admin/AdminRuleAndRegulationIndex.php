<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\RuleAndRegulationForm;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class AdminRuleAndRegulationIndex extends AdminComponent
{
    use WithPagination, Toast;

    public RuleAndRegulationForm $form;

    public array $sortBy = ['column' => 'rule_header_id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;
    public bool $showEditModal = false;
    public ?int $editingRuleId = null;

    public function mount(): void
    {
        $this->headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'ruleHeader.title', 'label' => 'Header', 'sortable' => true],
            ['key' => 'content', 'label' => 'Content', 'sortable' => false],
            ['key' => 'order', 'label' => 'Order', 'class' => 'w-20'],
        ];
    }

    #[Computed]
    public function rules()
    {
        return RuleRegulation::query()
            ->with('ruleHeader')
            ->orderBy(...array_values($this->sortBy))
            ->paginate($this->perPage);
    }

    public function edit(int $id): void
    {
        $rule = RuleRegulation::findOrFail($id);

        $this->editingRuleId = $id;
        $this->form->rule_header_id = $rule->rule_header_id;
        $this->form->content = $rule->content;

        $this->showEditModal = true;
    }

    public function update(): void
    {
        $this->form->update($this->editingRuleId);

        $this->success('Rule updated successfully');
        $this->showEditModal = false;
        $this->editingRuleId = null;
        $this->form->reset();
    }

    public function delete(int $id): void
    {
        $rule = RuleRegulation::findOrFail($id);
        $rule->delete();

        $this->warning('Rule deleted successfully');
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-rule-and-regulation-index', [
            'headers_list' => RuleHeader::orderBy('order')->get()
        ]);
    }
}
