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
    public ?string $research_project_adviser = '';
    #[Validate('required')]
    public string $department = '';
    public ?string $dean = '';
    #[Validate('required|array|min:1')]
    public array $author_names = [];
    #[Validate('required|integer|min:1|max:100')]
    public int $number_of_copies = 1;
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
    public ?\Illuminate\Support\Collection $adviser_options = null;
    public ?\Illuminate\Support\Collection $dean_options = null;

    public function mount()
    {
        $this->adviser_options = collect();
        $this->dean_options = collect();
        $this->populateYearChoices();
    }

    public function populateYearChoices()
    {
        $currentYear = date('Y');
        $years = [];
        for ($y = $currentYear; $y >= 2002; $y--) {
            $years[] = ['id' => $y, 'name' => $y];
        }
        $this->year_choices = $years;
    }


    private function syncAuthors($academicPaper)
    {
        if (empty($this->author_names)) {
            $academicPaper->authors()->detach();
            return;
        }

        $authorIds = [];
        foreach ($this->author_names as $authorName) {
            $authorName = trim($authorName);
            if (empty($authorName)) continue;

            // Find existing author or create new one
            $author = \App\Models\Author::firstOrCreate(
                ['name' => $authorName],
                ['name' => $authorName]
            );
            $authorIds[] = $author->id;
        }

        $academicPaper->authors()->sync($authorIds);
    }

    private function createInventoryCopies($academicPaper)
    {
        $currentCount = $academicPaper->copies()->count();
        $desiredCount = $this->number_of_copies;

        if ($desiredCount > $currentCount) {
            // Create additional copies
            for ($i = $currentCount + 1; $i <= $desiredCount; $i++) {
                \App\Models\Inventory::create([
                    'academic_paper_id' => $academicPaper->id,
                    'copy_number' => $i,
                    'status' => 'Available',
                ]);
            }
        } elseif ($desiredCount < $currentCount) {
            // Remove excess copies (only if they're available)
            $excessCopies = $academicPaper->copies()
                ->where('status', 'Available')
                ->orderBy('copy_number', 'desc')
                ->limit($currentCount - $desiredCount)
                ->get();

            foreach ($excessCopies as $copy) {
                $copy->delete();
            }
        }
    }

    public function setAcademicPaper(AcademicPaper $academicPaper)
    {
        $this->id = $academicPaper->id;
        $this->catalog_code = $academicPaper->catalog_code;
        $this->title = $academicPaper->title;
        $this->publication_year = $academicPaper->publication_year;
        $this->paper_type = $academicPaper->paper_type;
        $this->research_project_adviser = $academicPaper->research_project_adviser ?? '';
        $this->department = $academicPaper->department;
        $this->dean = $academicPaper->dean ?? '';
        $this->author_names = $academicPaper->authors()->pluck('name')->filter()->toArray();
        $this->number_of_copies = $academicPaper->copies()->count() ?: 1;
        $this->academicPaper = $academicPaper;
    }

    public function store()
    {
        $this->validate([
            'title' => 'required',
            'publication_year' => 'required',
            'paper_type' => 'required',
            'department' => 'required',
            'author_names' => 'required|array|min:1',
            'number_of_copies' => 'required|integer|min:1|max:100',
            'research_project_adviser' => 'required',
            'dean' => 'required',
        ]);
        $paper = AcademicPaper::create([
            'title' => $this->title,
            'publication_year' => $this->publication_year,
            'paper_type' => $this->paper_type,
            'research_project_adviser' => $this->research_project_adviser ?? '',
            'department' => $this->department,
            'dean' => $this->dean ?? '',
        ]);

        // Sync authors
        $this->syncAuthors($paper);

        // Create inventory copies
        $this->createInventoryCopies($paper);

        return $paper;
    }

    public function update()
    {
        if (!$this->academicPaper) {
            throw new \RuntimeException('No academic paper set for update.');
        }

        $this->validate([
            'title' => 'required',
            'publication_year' => 'required',
            'paper_type' => 'required',
            'department' => 'required',
            'author_names' => 'required|array|min:1',
            'number_of_copies' => 'required|integer|min:1|max:100',
            'research_project_adviser' => 'required',
            'dean' => 'required',
        ]);
        $updateData = $this->only(['title', 'publication_year', 'paper_type', 'department']);
        $updateData['research_project_adviser'] = $this->research_project_adviser ?? '';
        $updateData['dean'] = $this->dean ?? '';
        $this->academicPaper->update($updateData);

        // Sync authors
        $this->syncAuthors($this->academicPaper);

        // Update inventory copies
        $this->createInventoryCopies($this->academicPaper);

        $this->setAcademicPaper($this->academicPaper->refresh());
        return $this->academicPaper;
    }

    public function reset(...$properties)
    {
        parent::reset(...$properties);
        $this->academicPaper = null;
        $this->id = null;
        $this->catalog_code = null;
        $this->author_names = [];
        $this->number_of_copies = 1;
        $this->adviser_options = collect();
        $this->dean_options = collect();
        $this->populateYearChoices();
    }
}
