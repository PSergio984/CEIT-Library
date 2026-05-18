<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\ViolationForm;
use App\Models\Violation;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ViolationsTab extends AdminComponent
{
    use Toast, WithPagination;

    public ViolationForm $form;

    public $search = '';

    public $perPage = 10;

    public $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public $openDrawer = false;

    public $confirmDeleteModal = false;

    public $isEdit = false;

    public $editingId = null;

    public array $headers = [
        ['key' => 'id', 'label' => '#'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'description', 'label' => 'Description', 'sortable' => true],
        ['key' => 'penalty_score', 'label' => 'Penalty'],
        ['key' => 'updated_at', 'label' => 'Last Updated'],
    ];

    public function getViolationsProperty()
    {
        $query = Violation::query()
            ->when($this->search, fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"));

        $allowed = ['id', 'name', 'description', 'penalty_score', 'updated_at'];
        $column = in_array($this->sortBy['column'] ?? 'name', $allowed, true) ? $this->sortBy['column'] : 'name';
        $direction = in_array(strtolower($this->sortBy['direction'] ?? 'asc'), ['asc', 'desc'], true) ? $this->sortBy['direction'] : 'asc';
        $query->orderBy($column, $direction);

        return $query->paginate($this->perPage);
    }

    public function openCreateDrawer()
    {
        $this->form->reset();
        $this->form->penalty_score = null; // Use null as sentinel value for "not set" in create mode
        $this->isEdit = false;
        $this->openDrawer = true;
    }

    public function openEditDrawer(int $id)
    {
        $this->form->setViolation(Violation::findOrFail($id));
        $this->isEdit = true;
        $this->editingId = $id;
        $this->openDrawer = true;
    }

    public function updatedFormPenaltyScore()
    {
        // Only clamp if value is not null (the sentinel value in create mode)
        // This preserves the sentinel value for validation
        if ($this->form->penalty_score !== null) {
            if ($this->form->penalty_score < 1) {
                $this->form->penalty_score = 1;
            } elseif ($this->form->penalty_score > 100) {
                $this->form->penalty_score = 100;
            }
        }
    }

    public function save()
    {
        // Let the form's validate() method handle all validation including penalty_score range
        // This ensures all validation errors are shown together, not one at a time
        $this->isEdit
            ? $this->form->update($this->editingId)
            : $this->form->store();

        $this->success($this->isEdit ? 'Violation updated!' : 'Violation created!');
        $this->openDrawer = false;
    }

    public function confirmDelete(int $id)
    {
        $this->editingId = $id;
        $this->confirmDeleteModal = true;
    }

    public function deleteConfirmed()
    {
        Violation::findOrFail($this->editingId)->delete();
        $this->success('Violation deleted!');
        $this->confirmDeleteModal = false;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->sortBy = ['column' => 'name', 'direction' => 'asc'];
    }

    public function getIsFormValidProperty(): bool
    {
        $name = trim($this->form->name ?? '');
        $description = trim($this->form->description ?? '');
        $penaltyScore = $this->form->penalty_score;

        // Check if fields are filled and valid
        // Name: must be 3-255 chars AND match the regex pattern (letters, spaces, hyphens, apostrophes, periods, &, commas, parentheses)
        $nameRegex = '/^[\p{L}\s\-\'\.&,()]+$/u';
        $nameValid = ! empty($name)
            && strlen($name) >= 3
            && strlen($name) <= 255
            && preg_match($nameRegex, $name);

        $descValid = ! empty($description) && strlen($description) >= 10 && strlen($description) <= 1000;

        // Strictly enforce 1-100 range (null means not set, which is invalid)
        $penaltyValid = $penaltyScore !== null && is_numeric($penaltyScore) && $penaltyScore >= 1 && $penaltyScore <= 100;

        // Check for validation errors from Livewire
        $hasErrors = $this->getErrorBag()->hasAny(['form.name', 'form.description', 'form.penalty_score']);

        $fieldsValid = $nameValid && $descValid && $penaltyValid && ! $hasErrors;

        // For edit mode, also require form to be dirty
        if ($this->isEdit) {
            return $fieldsValid && $this->isFormDirty();
        }

        return $fieldsValid;
    }

    private function isFormDirty(): bool
    {
        if (! $this->isEdit || ! $this->editingId) {
            return false;
        }

        $violation = Violation::find($this->editingId);
        if (! $violation) {
            return false;
        }

        return trim($this->form->name) !== trim($violation->name) ||
            trim($this->form->description) !== trim($violation->description) ||
            $this->form->penalty_score !== $violation->penalty_score;
    }

    public function render()
    {
        return view('livewire.pages.admin.violations-tab');
    }
}
