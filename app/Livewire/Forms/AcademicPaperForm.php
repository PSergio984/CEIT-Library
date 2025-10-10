<?php

namespace App\Livewire\Forms;

use App\Models\AcademicPaper;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AcademicPaperForm extends Form
{
    public ?AcademicPaper $academicPaper = null;

    public ?int $id = null;
    public ?string $catalog_code = null;
    #[Validate('required')]
    public string $title = '';
    #[Validate('required')]
    public ?int $publication_year = null;
    #[Validate('required')]
    public string $paper_type = '';
    #[Validate('required')]
    public string $research_project_adviser = '';
    #[Validate('required')]
    public string $department = '';
    #[Validate('required')]
    public string $dean = '';
    public array $type_choices = [
        ['id' => 'Thesis', 'name' => 'Thesis'],
        ['id' => 'Capstone', 'name' => 'Capstone'],
        ['id' => 'Feasib', 'name' => 'Feasib'],
        ['id' => 'Research', 'name' => 'Research'],
        ['id' => 'Practicum', 'name' => 'Practicum'],
        ['id' => 'Report', 'name' => 'Report'],
    ];
    public array $department_choices = [
        ['id' => 'Civil Engineering', 'name' => 'Civil Engineering'],
        ['id' => 'Information Technology', 'name' => 'Information Technology'],
        ['id' => 'Electrical Engineering', 'name' => 'Electrical Engineering'],

    ];

    public array $year_choices = [];

    public function populateYearChoices()
    {
        $currentYear = date('Y');
        $years = [];
        for ($y = $currentYear; $y >= 2002; $y--) {
            $years[] = ['id' => $y, 'name' => $y];
        }
        $this->year_choices = $years;
    }

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
        $this->academicPaper->update($this->only(['title', 'publication_year', 'paper_type', 'research_project_adviser', 'department', 'dean']));
        $this->setAcademicPaper($this->academicPaper->refresh());
        return $this->academicPaper;
    }
}
