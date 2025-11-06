<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use App\Models\AcademicPaper;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Academic Paper List')]
class AdminAcademicPaperIndex extends AdminComponent
{
    use WithPagination;
    use Toast;
    use AuthorizesRequests;


    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];
    public array $headers = [];
    public int $perPage = 10;

    #[Url]
    public string $search = '';


    public function updatingPerPage(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public ?string $dept = null;

    // Only store IDs, not boolean states (Alpine handles modal visibility)
    #[Locked]
    public ?int $deleteId = null;

    public bool $formDrawer = false;
    public bool $isEditing = false;
    public AcademicPaperForm $form;

    // Only store ID, not modal state (Alpine handles visibility)
    #[Locked]
    public ?int $selectedPaperId = null;

    // Copy deletion - only store ID (Alpine handles modal visibility)
    #[Locked]
    public ?int $copyToDelete = null;

    // Cache properties for memoization
    private ?array $cachedAdvisers = null;
    private ?array $cachedDeans = null;
    private ?string $lastAdviserSearch = null;
    private ?string $lastDeanSearch = null;

    // Flag to prevent duplicate cache queries
    private bool $advisersLoaded = false;
    private bool $deansLoaded = false;

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        // Initialize empty collections to avoid null references
        $this->form->adviser_options = collect();
        $this->form->dean_options = collect();
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'publication_year', 'label' => 'Year'],
            ['key' => 'paper_type', 'label' => 'Paper Type'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'font-semibold'],
        ];
        $this->form->populateYearChoices();
    }

    public function search() {}


    #[Computed]
    public function academicPapers()
    {
        // Get current version token for cache busting
        $version = $this->getAcademicPapersVersion();

        // Create a cache key based on current filters, pagination, and version
        $cacheKey = sprintf(
            'academic_papers_%s_%s_%s_%s_%d_%d_v%d',
            $this->dept ?? 'all',
            $this->search ?: 'no_search',
            $this->sortBy['column'],
            $this->sortBy['direction'],
            $this->perPage,
            $this->paginators['academic-papers-index'] ?? 1,
            $version
        );

        // Use cache for non-search queries (empty search) with short TTL
        if (empty($this->search)) {
            $paginated = Cache::remember($cacheKey, 60, function () {
                return $this->buildAcademicPapersQuery()->paginate($this->perPage, pageName: 'academic-papers-index');
            });
        } else {
            // For search queries, don't cache as they're more dynamic
            $paginated = $this->buildAcademicPapersQuery()->paginate($this->perPage, pageName: 'academic-papers-index');
        }

        // Transform items to include status as a direct property
        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
            return $paper;
        });

        return $paginated;
    }

    /**
     * Build the academic papers query with filters and relationships
     */
    private function buildAcademicPapersQuery()
    {
        return AcademicPaper::query()
            ->with([
                'authors' => function ($query) {
                    $query->select('authors.id', 'authors.name');
                },
                'adviser:id,name',
                'dean:id,name',
                'copies' => function ($query) {
                    $query->select('id', 'academic_paper_id', 'status');
                }
            ])
            // filter by department if provided via route slug
            ->when($this->dept, function ($q) {
                $departmentName = $this->getDepartmentName($this->dept);
                // Only apply filter if we have a valid department name
                if ($departmentName && in_array($departmentName, config('departments.valid_names', []))) {
                    $q->where('department', $departmentName);
                }
            })
            ->when($this->search, function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('catalog_code', 'like', $search)
                        ->orWhereHas('adviser', function ($adviserQuery) use ($search) {
                            $adviserQuery->where('name', 'like', $search);
                        })
                        ->orWhereHas('dean', function ($deanQuery) use ($search) {
                            $deanQuery->where('name', 'like', $search);
                        });
                });
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy($this->getSortColumn(), $this->getSortDirection());
    }

    // Reset pagination when dept or search changes
    public function updatedDept(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('academic-papers-index');
    }

    // Perform deletion (called from Alpine modal)
    public function performDelete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        $academicPaper = AcademicPaper::findOrFail($this->deleteId);

        // Authorization check
        $this->authorize('delete', $academicPaper);

        $academicPaper->delete();
        $this->success('Academic paper deleted successfully');

        // Invalidate caches
        $this->incrementAcademicPapersVersion();

        $this->deleteId = null;
        $this->resetPage('academic-papers-index');

        // Dispatch event to close modal on frontend
        $this->dispatch('close-modals');
    }

    // Open drawer for creating new academic paper
    public function create(): void
    {
        $this->isEditing = false;
        $this->form->reset(); // This already calls populateYearChoices() and loadStaticChoices()
        $this->resetErrorBag(); // Clear any previous validation errors

        // Only load search options if not already cached
        if ($this->cachedAdvisers === null) {
            $this->searchAdvisers();
        }
        if ($this->cachedDeans === null) {
            $this->searchDeans();
        }

        $this->formDrawer = true;
    }

    // Open drawer for editing existing academic paper
    public function edit(int $id): void
    {
        $this->resetErrorBag(); // Clear any previous validation errors

        // Always reload the paper data to ensure fresh state
        $academicPaper = AcademicPaper::with([
            'authors' => function ($query) {
                $query->select('authors.id', 'authors.name');
            },
            'copies' => function ($query) {
                $query->select('id', 'academic_paper_id', 'status');
            }
        ])->findOrFail($id);
        $this->form->setAcademicPaper($academicPaper);

        $this->isEditing = true;

        // Only load search options if not already cached
        if ($this->cachedAdvisers === null) {
            $this->searchAdvisers();
        }
        if ($this->cachedDeans === null) {
            $this->searchDeans();
        }

        $this->formDrawer = true;
    }

    // Save academic paper (create or update)
    public function saveAcademicPaper(): void
    {
        if ($this->isEditing) {
            $paper = $this->form->update();
            $this->success("{$paper->catalog_code} updated", 'Updated Successfully!');
        } else {
            $paper = $this->form->store();
            $this->success("{$paper->catalog_code} created", 'Academic Paper Created Successfully!');
        }

        // Invalidate caches when data changes
        $this->invalidateSearchCaches();
        $this->clearRequestCaches();

        $this->formDrawer = false;
        $this->isEditing = false;
        $this->form->reset();
        $this->form->populateYearChoices();
        $this->resetPage('academic-papers-index');
    }


    // Search method for advisers with caching
    public function searchAdvisers(string $value = '')
    {
        // Check if we have cached results for the same search
        if ($this->lastAdviserSearch === $value && $this->cachedAdvisers !== null) {
            $this->form->adviser_options = collect($this->cachedAdvisers);
            return $this->cachedAdvisers;
        }

        // Prevent duplicate loading for empty searches - more aggressive check
        if ($value === '' && ($this->advisersLoaded || $this->cachedAdvisers !== null)) {
            $this->form->adviser_options = collect($this->cachedAdvisers);
            return $this->cachedAdvisers;
        }

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'advisers_all' : null;
        if ($cacheKey) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                $advisers = collect($cachedData);
            } else {
                // Get search results from database - now from Adviser model
                $advisers = \App\Models\Adviser::query()
                    ->when($value !== '', function ($query) use ($value) {
                        $query->where('name', 'like', "%{$value}%");
                    })
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->take(10)
                    ->get()
                    ->map(function ($adviser) {
                        return ['id' => $adviser->id, 'name' => $adviser->name];
                    });

                // Cache empty search results for 5 minutes
                if ($value === '') {
                    Cache::put($cacheKey, $advisers->toArray(), 300);
                }
            }
        } else {
            // Get search results from database for non-empty searches
            $advisers = \App\Models\Adviser::query()
                ->when($value !== '', function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%");
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->take(10)
                ->get()
                ->map(function ($adviser) {
                    return ['id' => $adviser->id, 'name' => $adviser->name];
                });
        }

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->adviser_id)) {
            $selectedAdviser = \App\Models\Adviser::find($this->form->adviser_id);
            if ($selectedAdviser) {
                $selectedOption = collect([['id' => $selectedAdviser->id, 'name' => $selectedAdviser->name]]);
                $advisers = $advisers->merge($selectedOption)->unique('id');
            }
        }

        // Cache the results for this request
        $this->cachedAdvisers = $advisers->toArray();
        $this->lastAdviserSearch = $value;
        $this->form->adviser_options = $advisers;

        // Mark as loaded for empty searches
        if ($value === '') {
            $this->advisersLoaded = true;
        }

        return $this->cachedAdvisers;
    }

    // Search method for deans with caching
    public function searchDeans(string $value = '')
    {
        // Check if we have cached results for the same search
        if ($this->lastDeanSearch === $value && $this->cachedDeans !== null) {
            $this->form->dean_options = collect($this->cachedDeans);
            return $this->cachedDeans;
        }

        // Prevent duplicate loading for empty searches - more aggressive check
        if ($value === '' && ($this->deansLoaded || $this->cachedDeans !== null)) {
            $this->form->dean_options = collect($this->cachedDeans);
            return $this->cachedDeans;
        }

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'deans_all' : null;
        if ($cacheKey) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                $deans = collect($cachedData);
            } else {
                // Get search results from database - now from Dean model
                $deans = \App\Models\Dean::query()
                    ->when($value !== '', function ($query) use ($value) {
                        $query->where('name', 'like', "%{$value}%");
                    })
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->take(10)
                    ->get()
                    ->map(function ($dean) {
                        return ['id' => $dean->id, 'name' => $dean->name];
                    });

                // Cache empty search results for 5 minutes
                if ($value === '') {
                    Cache::put($cacheKey, $deans->toArray(), 300);
                }
            }
        } else {
            // Get search results from database for non-empty searches
            $deans = \App\Models\Dean::query()
                ->when($value !== '', function ($query) use ($value) {
                    $query->where('name', 'like', "%{$value}%");
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->take(10)
                ->get()
                ->map(function ($dean) {
                    return ['id' => $dean->id, 'name' => $dean->name];
                });
        }

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->dean_id)) {
            $selectedDean = \App\Models\Dean::find($this->form->dean_id);
            if ($selectedDean) {
                $selectedOption = collect([['id' => $selectedDean->id, 'name' => $selectedDean->name]]);
                $deans = $deans->merge($selectedOption)->unique('id');
            }
        }

        // Cache the results for this request
        $this->cachedDeans = $deans->toArray();
        $this->lastDeanSearch = $value;
        $this->form->dean_options = $deans;

        // Mark as loaded for empty searches
        if ($value === '') {
            $this->deansLoaded = true;
        }

        return $this->cachedDeans;
    }

    /**
     * Invalidate search-related caches when data changes
     */
    private function invalidateSearchCaches(): void
    {
        // Clear individual cache keys
        Cache::forget('advisers_all');
        Cache::forget('deans_all');

        // Increment version token to invalidate all academic papers caches
        $this->incrementAcademicPapersVersion();
    }

    /**
     * Clear request-level caches to force fresh data
     */
    private function clearRequestCaches(): void
    {
        $this->cachedAdvisers = null;
        $this->cachedDeans = null;
        $this->lastAdviserSearch = null;
        $this->lastDeanSearch = null;
        $this->advisersLoaded = false;
        $this->deansLoaded = false;
    }

    /**
     * Get current version token for academic papers cache busting
     */
    private function getAcademicPapersVersion(): int
    {
        return Cache::get('academic_papers_version', 1);
    }

    /**
     * Increment version token to invalidate all academic papers caches
     */
    private function incrementAcademicPapersVersion(): void
    {
        $currentVersion = $this->getAcademicPapersVersion();
        Cache::put('academic_papers_version', $currentVersion + 1, 86400); // 24 hours
    }

    /**
     * Get selected paper by ID with relationships (computed to avoid payload bloat)
     */
    #[Computed]
    public function selectedPaper(): ?AcademicPaper
    {
        if (!$this->selectedPaperId) {
            return null;
        }

        return AcademicPaper::with([
            'authors' => fn($q) => $q->select('authors.id', 'authors.name'),
            'adviser:id,name',
            'dean:id,name',
            'copies' => fn($q) => $q->select('id', 'academic_paper_id', 'copy_number', 'status')
        ])->find($this->selectedPaperId);
    }

    /**
     * Get department name from slug with caching
     */
    private function getDepartmentName(string $dept): string
    {
        return Cache::remember("dept_mapping_{$dept}", 3600, function () use ($dept) {
            $mapping = config('departments.mapping', []);
            $validNames = config('departments.valid_names', []);

            // Check if it's a known slug
            if (isset($mapping[$dept])) {
                return $mapping[$dept];
            }

            // Check if it's already a valid department name
            if (in_array($dept, $validNames)) {
                return $dept;
            }

            // Return original value as fallback
            return $dept;
        });
    }

    public function showPaperDetails(int $paperId): void
    {
        $this->selectedPaperId = $paperId;
        // Dispatch event for Alpine to open modal
        $this->dispatch('open-paper-modal');
    }

    public function requestQr(int $copyId): void
    {
        // TODO: Implement QR code request functionality
        // This could generate a QR code for the specific copy
        // or redirect to a QR generation page
        $this->info("QR generation for copy #{$copyId} is not yet implemented");
    }

    public function performCopyDelete(): void
    {
        if (!$this->copyToDelete) {
            return;
        }

        $copy = \App\Models\Inventory::findOrFail($this->copyToDelete);

        // Authorization check - verify user can manage academic papers
        $this->authorize('update', $copy->academicPaper);

        if ($copy->status !== 'Available') {
            $this->error("Cannot delete copy #{$this->copyToDelete}. It may be borrowed or not found.", 'Delete Failed!');
            $this->copyToDelete = null;
            // Close modal via event
            $this->dispatch('close-modals');
            return;
        }

        $copy->delete();
        $this->success("Copy #{$this->copyToDelete} deleted successfully", 'Copy Deleted!');

        // Invalidate caches to reflect the change
        $this->invalidateSearchCaches();

        $this->copyToDelete = null;
        // Close modal via event
        $this->dispatch('close-modals');
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (!$this->selectedPaper || !$this->selectedPaper->department) {
            return '';
        }

        return match ($this->selectedPaper->department) {
            'Civil Engineering' => asset('images/aces.png'),
            'Electrical Engineering' => asset('images/ees.png'),
            'Information Technology' => asset('images/vits.png'),
            default => '',
        };
    }

    /**
     * Get the appropriate badge class for a given status.
     */
    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'Available' => 'badge-success',
            'Borrowed' => 'badge-warning',
            default => 'badge-error',
        };
    }

    /**
     * Get the sanitized sort column, mapping virtual columns to real database columns
     */
    private function getSortColumn(): string
    {
        $allowedColumns = [
            'id',
            'catalog_code',
            'title',
            'publication_year',
            'paper_type',
            'research_project_adviser',
            'available_copies', // maps to the withCount field
        ];

        $column = $this->sortBy['column'] ?? 'id';

        // Map virtual columns to real database columns
        $columnMapping = [
            'status' => 'available_copies', // Map virtual 'status' to real 'available_copies'
        ];

        $mappedColumn = $columnMapping[$column] ?? $column;

        // Validate against whitelist
        return in_array($mappedColumn, $allowedColumns) ? $mappedColumn : 'id';
    }

    /**
     * Get the sanitized sort direction
     */
    private function getSortDirection(): string
    {
        $direction = $this->sortBy['direction'] ?? 'asc';
        return in_array(strtolower($direction), ['asc', 'desc']) ? strtolower($direction) : 'asc';
    }

    public function render()
    {
        return view('livewire.pages.Admin.admin-academic-paper-index');
    }
}
