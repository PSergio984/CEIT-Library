<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use App\Models\AcademicPaper;
use Mary\Traits\Toast;

class EditAcademicPaper extends AdminComponent
{
    use Toast;

    public AcademicPaperForm $form;

    public function mount(AcademicPaper $academicPaper)
    {
        $this->authorizeAccess();
        $this->form->setAcademicPaper($academicPaper);
    }

    public function save()
    {
        $paper = $this->form->update();
        $this->success("{$paper->title} updated", 'Updated Successfully!', redirectTo: '/admin/academic-papers');
    }

    public function render()
    {
        return view('livewire.pages.admin.edit-academic-paper');
    }
}
