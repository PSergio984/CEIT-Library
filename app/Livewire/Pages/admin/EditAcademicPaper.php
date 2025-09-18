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
        $this->form->setAcademicPaper($academicPaper);
    }

    public function save() {
        $this->form->update();
        $this->success("{$this->form->academicPaper->title} updated", 'Updated Successfully!', redirectTo: "/admin/academic-papers");
    }

    public function render()
    {
        return view('livewire.pages.Admin.edit-academic-paper');
    }
}
