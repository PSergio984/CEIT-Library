<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use App\Models\AcademicPaper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Academic Paper List')]
class AdminAcademicPaperIndex extends AdminComponent
{
    use WithPagination;
    use Toast;


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
    public bool $deleteModal = false;
    public ?int $deleteId = null;
    public bool $formDrawer = false;
    public bool $isEditing = false;
    public AcademicPaperForm $form;

    // Modal properties
    public bool $showModal = false;
    public ?AcademicPaper $selectedPaper = null;

    // Copy deletion modal properties
    public bool $copyDeleteModal = false;
    public ?int $copyToDelete = null;

    // Cache properties for memoization
    private ?array $cachedAdvisers = null;
    private ?array $cachedDeans = null;
    private ?string $lastAdviserSearch = null;
    private ?string $lastDeanSearch = null;

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->searchAdvisers();
        $this->searchDeans();
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
            $this->getPage('academic-papers-index'),
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
            ->with(['copies' => function ($query) {
                $query->select('academic_paper_id', 'status');
            }])
            // filter by department if provided via route slug
            ->when($this->dept, function ($q) {
                $value = $this->getDepartmentName($this->dept);
                $q->where('department', $value);
            })
            ->when($this->search, function ($query) {
                $search = '%' . $this->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('research_project_adviser', 'like', $search)
                        ->orWhere('catalog_code', 'like', $search);
                });
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                }
            ])
            ->orderBy(...array_values($this->sortBy));
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

    public function deleteAcademicPaper($id)
    {
        $academicPaper = AcademicPaper::find($id);
        if ($academicPaper) {
            $academicPaper->delete();
            $this->warning("$academicPaper->title deleted", 'Good bye!');
        } else {
            $this->warning("Academic paper not found", 'Error!');
        }
    }

    // Open confirmation modal
    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->deleteModal = true;
    }

    // Perform deletion after confirmation
    public function performDelete(): void
    {
        if ($this->deleteId) {
            $academicPaper = AcademicPaper::find($this->deleteId);
            if ($academicPaper) {
                $title = $academicPaper->title;
                $academicPaper->delete();
                $this->invalidateSearchCaches();
                $this->warning("$title deleted", 'Good bye!');
            }
        }
        $this->deleteModal = false;
        $this->deleteId = null;
        $this->resetPage('academic-papers-index');
    }

    // Open drawer for creating new academic paper
    public function create(): void
    {
        $this->isEditing = false;
        $this->form->reset();
        $this->form->populateYearChoices();
        $this->searchAdvisers();
        $this->searchDeans();
        $this->formDrawer = true;
    }

    // Open drawer for editing existing academic paper
    public function edit(int $id): void
    {
        $academicPaper = AcademicPaper::with('authors')->findOrFail($id);
        $this->isEditing = true;
        $this->form->setAcademicPaper($academicPaper);
        $this->searchAdvisers();
        $this->searchDeans();
        $this->formDrawer = true;
    }

    // Save academic paper (create or update)
    public function saveAcademicPaper(): void
    {
        if ($this->isEditing) {
            $this->form->update();
            $this->success("{$this->form->academicPaper->catalog_code} updated", 'Updated Successfully!');
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
        $this->searchAdvisers();
        $this->searchDeans();
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

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'advisers_all' : null;
        if ($cacheKey && Cache::has($cacheKey)) {
            $advisers = collect(Cache::get($cacheKey));
        } else {
            // Get search results from database
            $advisers = \App\Models\AcademicPaper::whereNotNull('research_project_adviser')
                ->when($value !== '', function ($query) use ($value) {
                    $query->where('research_project_adviser', 'like', "%{$value}%");
                })
                ->distinct()
                ->pluck('research_project_adviser')
                ->filter()
                ->map(function ($adviser) {
                    return ['id' => $adviser, 'name' => $adviser];
                })
                ->take(10);

            // Cache empty search results for 5 minutes
            if ($value === '') {
                Cache::put($cacheKey, $advisers->toArray(), 300);
            }
        }

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->research_project_adviser)) {
            $selectedOption = collect([['id' => $this->form->research_project_adviser, 'name' => $this->form->research_project_adviser]]);
            $advisers = $advisers->merge($selectedOption)->unique('id');
        }

        // Cache the results for this request
        $this->cachedAdvisers = $advisers->toArray();
        $this->lastAdviserSearch = $value;
        $this->form->adviser_options = $advisers;

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

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'deans_all' : null;
        if ($cacheKey && Cache::has($cacheKey)) {
            $deans = collect(Cache::get($cacheKey));
        } else {
            // Get search results from database
            $deans = \App\Models\AcademicPaper::whereNotNull('dean')
                ->when($value !== '', function ($query) use ($value) {
                    $query->where('dean', 'like', "%{$value}%");
                })
                ->distinct()
                ->pluck('dean')
                ->filter()
                ->map(function ($dean) {
                    return ['id' => $dean, 'name' => $dean];
                })
                ->take(10);

            // Cache empty search results for 5 minutes
            if ($value === '') {
                Cache::put($cacheKey, $deans->toArray(), 300);
            }
        }

        // Include selected option if it exists and is not in search results
        if (!empty($this->form->dean)) {
            $selectedOption = collect([['id' => $this->form->dean, 'name' => $this->form->dean]]);
            $deans = $deans->merge($selectedOption)->unique('id');
        }

        // Cache the results for this request
        $this->cachedDeans = $deans->toArray();
        $this->lastDeanSearch = $value;
        $this->form->dean_options = $deans;

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
     * Get department name from slug with caching
     */
    private function getDepartmentName(string $dept): string
    {
        return Cache::remember("dept_mapping_{$dept}", 3600, function () use ($dept) {
            $map = [
                'it' => 'Information Technology',
                'ce' => 'Civil Engineering',
                'ee' => 'Electrical Engineering',
            ];
            return $map[$dept] ?? $dept;
        });
    }

    public function showPaperDetails(AcademicPaper $academicPaper): void
    {
        $this->selectedPaper = $academicPaper->load('authors', 'copies');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedPaper = null;
    }

    public function requestQr(): void
    {
        // TODO: Implement QR code request functionality
        // This could generate a QR code for the specific copy
        // or redirect to a QR generation page
    }

    public function confirmCopyDelete(int $copyId): void
    {
        $this->copyToDelete = $copyId;
        $this->copyDeleteModal = true;
    }

    public function performCopyDelete(): void
    {
        if ($this->copyToDelete) {
            $copy = \App\Models\Inventory::find($this->copyToDelete);
            if ($copy && $copy->status === 'Available') {
                $copy->delete();
                $this->success("Copy #{$this->copyToDelete} deleted successfully", 'Copy Deleted!');

                // Refresh the selected paper data
                if ($this->selectedPaper) {
                    $this->selectedPaper = $this->selectedPaper->fresh(['authors', 'copies']);
                }

                // Invalidate caches to reflect the change
                $this->invalidateSearchCaches();
            } else {
                $this->error("Cannot delete copy #{$this->copyToDelete}. It may be borrowed or not found.", 'Delete Failed!');
            }
        }

        $this->copyDeleteModal = false;
        $this->copyToDelete = null;
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (!$this->selectedPaper || !$this->selectedPaper->department) {
            return '';
        }

        return match ($this->selectedPaper->department) {
            'Civil Engineering' => Vite::asset('public/images/aces.png'),
            'Electrical Engineering' => Vite::asset('public/images/ees.png'),
            'Information Technology' => Vite::asset('public/images/vits.png'),
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

    public function render()
    {
        return view('livewire.pages.Admin.admin-academic-paper-index');
    }
}
