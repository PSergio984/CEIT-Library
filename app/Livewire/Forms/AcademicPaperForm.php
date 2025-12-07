<?php

namespace App\Livewire\Forms;

use App\Models\AcademicPaper;
use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
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
    public string $paper_type = 'Thesis';

    #[Validate('required|integer|exists:research_advisers,id')]
    public ?int $research_adviser_id = null;

    #[Validate('required|integer|exists:technical_advisers,id')]
    public ?int $technical_adviser_id = null;

    #[Validate('required')]
    public string $department = 'Information Technology';

    #[Validate('required|integer|exists:deans,id')]
    public ?int $dean_id = null;

    #[Validate('required|array|min:1')]
    public array $author_ids = [];

    #[Validate('required|integer|min:1|max:100')]
    public int $number_of_copies = 1;

    // Track initial copy count for edit mode (prevents reducing)
    private ?int $initialCopyCount = null;

    public array $type_choices = [];

    public array $department_choices = [];

    public array $year_choices = [];

    public ?array $research_adviser_options = null;

    public ?array $technical_adviser_options = null;

    public ?array $dean_options = null;

    public ?array $author_options = null;

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
        // Ensure arrays are initialized
        if ($this->research_adviser_options === null) {
            $this->research_adviser_options = [];
        }
        if ($this->technical_adviser_options === null) {
            $this->technical_adviser_options = [];
        }
        if ($this->dean_options === null) {
            $this->dean_options = [];
        }
        if ($this->author_options === null) {
            $this->author_options = [];
        }

        // Set default publication year if not set
        if ($this->publication_year === null) {
            $this->publication_year = (int) date('Y');
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
                'Electrical Engineering',
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

    /**
     * Handle author_ids updates - prevent removing last author when editing
     */
    public function updatedAuthorIds($value): void
    {
        // If editing and trying to remove all authors, restore at least one
        if ($this->academicPaperId !== null && empty($value)) {
            // Get the original authors from the paper
            $paper = AcademicPaper::find($this->academicPaperId);
            if ($paper && $paper->authors()->count() > 0) {
                // Restore the first author to prevent empty selection
                $firstAuthor = $paper->authors()->first();
                if ($firstAuthor) {
                    $this->author_ids = [$firstAuthor->id];
                    $this->addError('author_ids', 'At least one author is required. Cannot remove all authors.');
                }
            }
        }
    }

    private function syncAuthors(\App\Models\AcademicPaper $academicPaper)
    {
        if (empty($this->author_ids)) {
            $academicPaper->authors()->detach();

            return;
        }

        // Filter out any invalid IDs and sync
        $validAuthorIds = array_filter($this->author_ids, fn($id) => is_int($id) && $id > 0);
        $academicPaper->authors()->sync($validAuthorIds);
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
        $this->research_adviser_id = $academicPaper->research_adviser_id;
        $this->technical_adviser_id = $academicPaper->technical_adviser_id;
        $this->department = $academicPaper->department;
        $this->dean_id = $academicPaper->dean_id;

        // Use already loaded relationships to avoid N+1 queries
        $this->author_ids = $academicPaper->relationLoaded('authors')
            ? $academicPaper->authors->pluck('id')->filter()->toArray()
            : $academicPaper->authors()->pluck('id')->filter()->toArray();

        $copyCount = $academicPaper->relationLoaded('copies')
            ? ($academicPaper->copies->count() ?: 1)
            : ($academicPaper->copies()->count() ?: 1);

        $this->number_of_copies = $copyCount;
        $this->initialCopyCount = $copyCount; // Store initial count for validation

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
        // Current year for publication year validation
        $currentYear = (int) date('Y');

        // Build paper_type rules - include 'in:' validation only if choices exist
        $paperTypeRules = [
            'required',
            'string',
            'max:50',
        ];
        $validPaperTypes = array_column($this->type_choices, 'id');
        if (! empty($validPaperTypes)) {
            $paperTypeRules[] = 'in:' . implode(',', $validPaperTypes);
        }

        // Build department rules - include 'in:' validation only if config exists
        $departmentRules = [
            'required',
            'string',
            'max:100',
        ];
        $validDepartments = config('departments.valid_names', []);
        if (! empty($validDepartments)) {
            $departmentRules[] = 'in:' . implode(',', $validDepartments);
        }

        $rules = [
            'title' => [
                'required',
                'string',
                'min:5',
                'max:500',
                new NoHtmlTags,
                new SafeText,
            ],
            'publication_year' => [
                'required',
                'integer',
                'min:2002',
                "max:{$currentYear}",
            ],
            'paper_type' => $paperTypeRules,
            'research_adviser_id' => [
                'required',
                'integer',
                'min:1',
                'exists:research_advisers,id',
            ],
            'technical_adviser_id' => [
                'required',
                'integer',
                'min:1',
                'exists:technical_advisers,id',
            ],
            'department' => $departmentRules,
            'dean_id' => [
                'required',
                'integer',
                'min:1',
                'exists:deans,id',
            ],
            'author_ids' => [
                'required',
                'array',
                'min:1',
                'max:20', // Reasonable max number of authors
            ],
            'author_ids.*' => [
                'integer',
                'min:1',
                'distinct', // No duplicate author IDs
                'exists:authors,id',
            ],
            'number_of_copies' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];

        // In edit mode, enforce minimum to prevent reduction via form
        if ($this->initialCopyCount !== null) {
            $rules['number_of_copies'] = [
                'required',
                'integer',
                "min:{$this->initialCopyCount}",
                'max:100',
            ];
        }

        return $rules;
    }

    /**
     * Get custom validation messages
     */
    private function validationMessages(): array
    {
        $currentYear = date('Y');

        return [
            // Title validation messages
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a valid text string.',
            'title.min' => 'The title must be at least 5 characters.',
            'title.max' => 'The title cannot exceed 500 characters.',

            // Publication year validation messages
            'publication_year.required' => 'The publication year field is required.',
            'publication_year.integer' => 'The publication year must be a valid year.',
            'publication_year.min' => 'The publication year cannot be before 2002.',
            'publication_year.max' => "The publication year cannot be after {$currentYear}.",

            // Paper type validation messages
            'paper_type.required' => 'The paper type field is required.',
            'paper_type.string' => 'The paper type must be valid text.',
            'paper_type.max' => 'The paper type is too long.',
            'paper_type.in' => 'Please select a valid paper type from the list.',

            // Research adviser validation messages
            'research_adviser_id.required' => 'The research adviser field is required.',
            'research_adviser_id.integer' => 'Invalid research adviser selection.',
            'research_adviser_id.min' => 'Invalid research adviser selection.',
            'research_adviser_id.exists' => 'The selected research adviser is invalid.',

            // Technical adviser validation messages
            'technical_adviser_id.required' => 'The technical adviser field is required.',
            'technical_adviser_id.integer' => 'Invalid technical adviser selection.',
            'technical_adviser_id.min' => 'Invalid technical adviser selection.',
            'technical_adviser_id.exists' => 'The selected technical adviser is invalid.',

            // Department validation messages
            'department.required' => 'The department field is required.',
            'department.string' => 'The department must be valid text.',
            'department.max' => 'The department name is too long.',
            'department.in' => 'Please select a valid department from the list.',

            // Dean validation messages
            'dean_id.required' => 'The dean field is required.',
            'dean_id.integer' => 'Invalid dean selection.',
            'dean_id.min' => 'Invalid dean selection.',
            'dean_id.exists' => 'The selected dean is invalid.',

            // Authors validation messages
            'author_ids.required' => 'At least one author is required.',
            'author_ids.array' => 'Invalid author selection format.',
            'author_ids.min' => 'At least one author must be specified.',
            'author_ids.max' => 'You cannot add more than 20 authors.',
            'author_ids.*.integer' => 'Invalid author selection.',
            'author_ids.*.min' => 'Invalid author selection.',
            'author_ids.*.distinct' => 'Each author can only be selected once.',
            'author_ids.*.exists' => 'One or more selected authors are invalid.',

            // Number of copies validation messages
            'number_of_copies.required' => 'The number of copies field is required.',
            'number_of_copies.integer' => 'The number of copies must be a valid number.',
            'number_of_copies.min' => $this->initialCopyCount !== null
                ? "Cannot reduce copies below current count ({$this->initialCopyCount}). Use the copy deletion modal instead."
                : 'The number of copies must be at least 1.',
            'number_of_copies.max' => 'The number of copies cannot exceed 100.',
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
            'research_adviser_id' => $this->research_adviser_id,
            'technical_adviser_id' => $this->technical_adviser_id,
            'dean_id' => $this->dean_id,
            'author_ids' => $this->author_ids,
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
                'research_adviser_id' => $this->research_adviser_id,
                'technical_adviser_id' => $this->technical_adviser_id,
                'department' => $this->department,
                'dean_id' => $this->dean_id,
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

        if (! $paper) {
            throw new \RuntimeException('No academic paper set for update.');
        }

        $this->validateSafely();

        return DB::transaction(function () use ($paper) {
            $updateData = $this->only(['title', 'publication_year', 'paper_type', 'department']);
            $updateData['research_adviser_id'] = $this->research_adviser_id;
            $updateData['technical_adviser_id'] = $this->technical_adviser_id;
            $updateData['dean_id'] = $this->dean_id;
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
            $this->publication_year = (int) date('Y'); // Default to current year
            $this->paper_type = 'Thesis'; // Default to Thesis
            $this->research_adviser_id = null;
            $this->technical_adviser_id = null;
            $this->department = 'Information Technology'; // Default to Information Technology
            $this->dean_id = null;
            $this->author_ids = [];
            $this->number_of_copies = 1;
        } else {
            // Reset only specified properties
            foreach ($properties as $property) {
                switch ($property) {
                    case 'title':
                        $this->title = '';
                        break;
                    case 'publication_year':
                        $this->publication_year = (int) date('Y'); // Default to current year
                        break;
                    case 'paper_type':
                        $this->paper_type = 'Thesis'; // Default to Thesis
                        break;
                    case 'research_adviser_id':
                        $this->research_adviser_id = null;
                        break;
                    case 'technical_adviser_id':
                        $this->technical_adviser_id = null;
                        break;
                    case 'department':
                        $this->department = 'Information Technology'; // Default to Information Technology
                        break;
                    case 'dean_id':
                        $this->dean_id = null;
                        break;
                    case 'author_ids':
                        $this->author_ids = [];
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
        $this->initialCopyCount = null; // Reset initial count tracker
        $this->research_adviser_options = [];
        $this->technical_adviser_options = [];
        $this->dean_options = [];
        $this->author_options = [];
        $this->populateYearChoices();
        $this->loadStaticChoices();
    }
}
