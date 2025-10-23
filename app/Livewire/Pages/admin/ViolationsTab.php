<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\Violation;
use App\Livewire\Forms\ViolationForm;

class ViolationsTab extends AdminComponent
{
    use WithPagination, Toast;

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
            ->when($this->search, fn($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->orderBy($this->sortBy['column'], $this->sortBy['direction']);

        return $query->paginate($this->perPage);
    }

    public function openCreateDrawer()
    {
        $this->form->reset();
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

    public function save()
    {
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

    public function render()
    {
        return view('livewire.pages.admin.violations-tab');
    }
}

