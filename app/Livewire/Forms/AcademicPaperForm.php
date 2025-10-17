<?php

namespace App\Livewire\Forms;

use App\Models\AcademicPaper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AcademicPaperForm extends Form
{
    public ?int $academicPaperId = null;

    public ?int $id = null;
    public ?string $catalog_code = null;
    #[Validate('required')]
    public string $title = '';
    #[Validate('required|integer|min:2002|max:2100')]
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
    public array $type_choices = [];
    public array $department_choices = [];

    public array $year_choices = [];
    public ?\Illuminate\Support\Collection $adviser_options = null;
    public ?\Illuminate\Support\Collection $dean_options = null;

    /**
     * Get the academic paper model from the stored ID
     */
    #[Computed]
    public function academicPaper(): ?AcademicPaper
    {
        return $this->academicPaperId
            ? AcademicPaper::with('authors', 'copies')->find($this->academicPaperId)
            : null;
    }

    /**
     * Boot method called after form is hydrated
     */
    public function boot(): void
    {
        // Ensure collections are initialized
        if ($this->adviser_options === null) {
            $this->adviser_options = collect();
        }
        if ($this->dean_options === null) {
            $this->dean_options = collect();
        }

        // Lazy load choices only when needed
        if (empty($this->year_choices)) {
            $this->populateYearChoices();
        }
        if (empty($this->type_choices) || empty($this->department_choices)) {
            $this->loadStaticChoices();
        }
    }

    /**
     * Load static choices with caching
     */
    private function loadStaticChoices(): void
    {
        $this->type_choices = Cache::remember('academic_paper_type_choices', 3600, function () {
            return [
                ['id' => 'Thesis', 'name' => 'Thesis'],
                ['id' => 'Capstone', 'name' => 'Capstone'],
                ['id' => 'Feasib', 'name' => 'Feasib'],
                ['id' => 'Research', 'name' => 'Research'],
                ['id' => 'Practicum', 'name' => 'Practicum'],
                ['id' => 'Report', 'name' => 'Report'],
            ];
        });

        $this->department_choices = Cache::remember('academic_paper_department_choices', 3600, function () {
            $validNames = config('departments.valid_names', [
                'Information Technology',
                'Civil Engineering',
                'Electrical Engineering'
            ]);

            return collect($validNames)->map(function ($name) {
                return ['id' => $name, 'name' => $name];
            })->toArray();
        });
    }

    public function populateYearChoices()
    {
        // Cache year choices since they don't change often
        $currentYear = date('Y');
        $this->year_choices = Cache::remember("academic_paper_year_choices_{$currentYear}", 3600, function () use ($currentYear) {
            $years = [];
            for ($y = $currentYear; $y >= 2002; $y--) {
                $years[] = ['id' => $y, 'name' => $y];
            }
            return $years;
        });
    }


    private function syncAuthors(\App\Models\AcademicPaper $academicPaper)
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
            // Calculate how many copies need to be removed
            $neededToRemove = $currentCount - $desiredCount;
            $availableCount = $academicPaper->copies()->where('status', 'Available')->count();

            // If not enough 'Available' copies exist to remove, surface a clear validation error and do nothing
            if ($availableCount < $neededToRemove) {
                $message = "Only {$availableCount} available copies can be removed; {$neededToRemove} required to reach the desired total.";

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'number_of_copies' => $message,
                ]);
            }

            // Remove exactly the number of needed 'Available' copies (highest copy_numbers first)
            $excessCopies = $academicPaper->copies()
                ->where('status', 'Available')
                ->orderBy('copy_number', 'desc')
                ->limit($neededToRemove)
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

        // Use already loaded relationships to avoid N+1 queries
        $this->author_names = $academicPaper->relationLoaded('authors')
            ? $academicPaper->authors->pluck('name')->filter()->toArray()
            : $academicPaper->authors()->pluck('name')->filter()->toArray();

        $this->number_of_copies = $academicPaper->relationLoaded('copies')
            ? ($academicPaper->copies->count() ?: 1)
            : ($academicPaper->copies()->count() ?: 1);

        // Store only the ID to avoid serialization overhead
        $this->academicPaperId = $academicPaper->id;

        // Only load static choices if they haven't been loaded yet
        if (empty($this->type_choices) || empty($this->department_choices)) {
            $this->loadStaticChoices();
        }

        // Ensure year choices are loaded
        if (empty($this->year_choices)) {
            $this->populateYearChoices();
        }
    }

    /**
     * Get validation rules for academic paper form
     */
    private function validationRules(): array
    {
        return [
            'title' => 'required',
            'publication_year' => 'required',
            'paper_type' => 'required',
            'department' => 'required',
            'author_names' => 'required|array|min:1',
            'number_of_copies' => 'required|integer|min:1|max:100',
            'research_project_adviser' => 'required|string',
            'dean' => 'required|string',
        ];
    }

    /**
     * Get custom validation messages
     */
    private function validationMessages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'publication_year.required' => 'The publication year field is required.',
            'paper_type.required' => 'The paper type field is required.',
            'department.required' => 'The department field is required.',
            'author_names.required' => 'At least one author is required.',
            'author_names.min' => 'At least one author must be specified.',
            'number_of_copies.required' => 'The number of copies field is required.',
            'number_of_copies.integer' => 'The number of copies must be a valid number.',
            'number_of_copies.min' => 'The number of copies must be at least 1.',
            'number_of_copies.max' => 'The number of copies cannot exceed 100.',
            'research_project_adviser.required' => 'The research project adviser field is required.',
            'research_project_adviser.string' => 'The research project adviser must be a valid text.',
            'dean.required' => 'The dean field is required.',
            'dean.string' => 'The dean must be a valid text.',
        ];
    }

    /**
     * Get all form data as an array
     */
    public function all(): array
    {
        return [
            'title' => $this->title,
            'publication_year' => $this->publication_year,
            'paper_type' => $this->paper_type,
            'department' => $this->department,
            'research_project_adviser' => $this->research_project_adviser,
            'dean' => $this->dean,
            'author_names' => $this->author_names,
            'number_of_copies' => $this->number_of_copies,
        ];
    }

    /**
     * Safe validation that doesn't require component to be initialized
     */
    public function validateSafely(array $rules = [])
    {
        if (empty($rules)) {
            $rules = $this->validationRules();
        }

        // Use Livewire's validate() method which properly handles error bag keys
        $this->validate($rules, $this->validationMessages());

        return true;
    }

    public function store()
    {
        $this->validateSafely();

        return DB::transaction(function () {
            $paper = AcademicPaper::create([
                'title' => $this->title,
                'publication_year' => $this->publication_year,
                'paper_type' => $this->paper_type,
                'research_project_adviser' => $this->research_project_adviser,
                'department' => $this->department,
                'dean' => $this->dean,
            ]);

            // Sync authors
            $this->syncAuthors($paper);

            // Create inventory copies
            $this->createInventoryCopies($paper);

            return $paper;
        });
    }

    public function update()
    {
        $paper = $this->academicPaper();

        if (!$paper) {
            throw new \RuntimeException('No academic paper set for update.');
        }

        $this->validateSafely();

        return DB::transaction(function () use ($paper) {
            $updateData = $this->only(['title', 'publication_year', 'paper_type', 'department']);
            $updateData['research_project_adviser'] = $this->research_project_adviser ?? '';
            $updateData['dean'] = $this->dean ?? '';
            $paper->update($updateData);

            // Sync authors
            $this->syncAuthors($paper);

            // Update inventory copies
            $this->createInventoryCopies($paper);

            // Refresh the data from the updated paper
            $this->setAcademicPaper($paper->refresh());

            return $paper;
        });
    }

    public function reset(...$properties)
    {
        // Reset form properties without calling parent::reset() to avoid component access issues
        $properties = count($properties) && is_array($properties[0]) ? $properties[0] : $properties;

        if (empty($properties)) {
            // Reset all properties
            $this->title = '';
            $this->publication_year = 0;
            $this->paper_type = '';
            $this->research_project_adviser = '';
            $this->department = '';
            $this->dean = '';
            $this->author_names = [];
            $this->number_of_copies = 1;
        } else {
            // Reset only specified properties
            foreach ($properties as $property) {
                switch ($property) {
                    case 'title':
                        $this->title = '';
                        break;
                    case 'publication_year':
                        $this->publication_year = 0;
                        break;
                    case 'paper_type':
                        $this->paper_type = '';
                        break;
                    case 'research_project_adviser':
                        $this->research_project_adviser = '';
                        break;
                    case 'department':
                        $this->department = '';
                        break;
                    case 'dean':
                        $this->dean = '';
                        break;
                    case 'author_names':
                        $this->author_names = [];
                        break;
                    case 'number_of_copies':
                        $this->number_of_copies = 1;
                        break;
                }
            }
        }

        // Reset additional properties
        $this->academicPaperId = null;
        $this->id = null;
        $this->catalog_code = null;
        $this->adviser_options = collect();
        $this->dean_options = collect();
        $this->populateYearChoices();
        $this->loadStaticChoices();
    }
}
