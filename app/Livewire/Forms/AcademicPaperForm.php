<?php

namespace App\Livewire\Forms;

use App\Models\AcademicPaper;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AcademicPaperForm extends Form
{
    public AcademicPaper $academicPaper;

    public int $id;
    public ?string $catalog_code=null;
    #[Validate('required')]
    public string $title;
    #[Validate('required')]
    public int $publication_year;
    #[Validate('required')]
    public string $paper_type;
    #[Validate('required')]
    public string $research_project_adviser;
    #[Validate('required')]
    public string $department;
    #[Validate('required')]
    public string $dean;

    public function setAcademicPaper(AcademicPaper $academicPaper)
    {
        $this->id = $academicPaper->id;
        $this->catalog_code = $academicPaper->catalog_code;
        $this->title = $academicPaper->title;
        $this->publication_year = $academicPaper->publication_year;
        $this->paper_type = $academicPaper->paper_type;
        $this->research_project_adviser = $academicPaper->research_project_adviser;
        $this->department = $academicPaper->department;
        $this->dean = $academicPaper->dean;
        $this->academicPaper = $academicPaper;
    }

    public function store()
    {
        $this->validate();
        AcademicPaper::create([
            'title' => $this->title,
            'publication_year' => $this->publication_year,
            'paper_type' => $this->paper_type,
            'research_project_adviser' => $this->research_project_adviser,
            'department' => $this->department,
            'dean' => $this->dean,
        ]);
    }

    public function update()
    {
        if (!$this->academicPaper) {
            throw new \RuntimeException('No academic paper set for update.');
        }

        $this->validate();
        $this->academicPaper->update($this->only(['title', 'publication_year','paper_type', 'research_project_adviser','department','dean']));
        $this->setAcademicPaper($this->academicPaper->refresh());
        return $this->academicPaper;
    }
}
