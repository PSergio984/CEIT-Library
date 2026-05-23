<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use Mary\Traits\Toast;

class CreateAcademicPaper extends AdminComponent
{
    use Toast;

    public AcademicPaperForm $form;

    public function save()
    {
        // returns the created academic paper
        $this->form->store();

        // using maryui toast, notify the user of success, then redirects them to their previous page
        $this->success(
            'New Academic Paper created',
            'Academic Paper Created Successfully!',
            redirectTo: '/admin/academic-papers'
        );
    }

    public function mount()
    {
        $this->authorizeAccess();
        $this->form->populateYearChoices();
    }

    public function render()
    {
        return view('livewire.pages.admin.create-academic-paper');
    }
}
