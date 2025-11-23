<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\AcademicPaperForm;
use App\Models\AcademicPaper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Livewire\Attributes\Lazy;
use App\Traits\CreatesQrCanonicalMessage;
use App\Models\Inventory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


#[Title('Academic Paper List')]
#[Lazy]
class AdminAcademicPaperIndex extends AdminComponent
{
    use Toast;
    use WithPagination;
    use CreatesQrCanonicalMessage;
    // QR Code properties for admin QR modal
    public ?string $qrCode = null;
    public ?int $selectedCopyId = null;
    /**
     * Request QR code for a specific inventory copy (admin context)
     */
    public function requestQr(int $inventoryId): void
    {
        $copy = Inventory::with('academicPaper')->find($inventoryId);

        if (! $copy) {
            session()->flash('error', 'Copy not found.');
            return;
        }

        if (! $copy->isAvailable()) {
            session()->flash('error', 'This copy is not available.');
            return;
        }

        $this->selectedCopyId = $copy->id;

        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(5);
        $payload = [
            'inventory_id' => $copy->id,
            'paper_id' => $copy->academic_paper_id,
            'catalog_code' => $copy->academicPaper->catalog_code,
            'title' => $copy->academicPaper->title,
            'requested_by' => Auth::id(),
            'lat' => Auth::user()->email,
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $qrPayload = $this->createEncryptedQrMessage($payload);

        $svg = app(\SimpleSoftwareIO\QrCode\Generator::class)->size(300)->generate($qrPayload);
        $this->qrCode = base64_encode($svg);

        $this->dispatch('open-qr-modal');
    }

    /**
     * Close the QR modal and clear state
     */
    public function closeQrModal(): void
    {
        $this->qrCode = null;
        $this->selectedCopyId = null;
        $this->dispatch('close-qr-modal');
    }

    /**
     * Get the download URL for the QR code (admin context)
     */
    public function getDownloadUrl()
    {
        if (! $this->selectedCopy()) {
            return null;
        }

        // Generate a URL for download
        return route('qr-code.download', [
            'inventoryId' => $this->selectedCopyId,
        ]);
    }

    #[Computed]
    public function downloadUrl()
    {
        return $this->getDownloadUrl();
    }

    /**
     * Get the selected copy model for QR actions
     */
    #[Computed]
    public function selectedCopy()
    {
        if (! $this->selectedCopyId) {
            return null;
        }
        return Inventory::with('academicPaper')->find($this->selectedCopyId);
    }

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public array $headers = [];

    public int $perPage = 10;

    #[Url]
    public string $search = '';

    // Filters
    public string $statusFilter = '';

    public string $yearFilter = '';

    public string $departmentFilter = '';

    public string $paperTypeFilter = '';

    public string $yearFromFilter = '';

    public string $yearToFilter = '';

    public function updatingPerPage(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public ?string $dept = null;

    // Store IDs for modal content (modals controlled by Alpine.js)
    #[Locked]
    public ?int $deleteId = null;

    public bool $formDrawer = false;

    public bool $isEditing = false;

    public AcademicPaperForm $form;

    #[Locked]
    public ?int $selectedPaperId = null;

    #[Locked]
    public ?int $copyToDelete = null;

    // Cache properties for memoization
    private ?array $cachedResearchAdvisers = null;

    private ?array $cachedTechnicalAdvisers = null;

    private ?array $cachedDeans = null;

    private ?array $cachedAuthors = null;

    private ?string $lastResearchAdviserSearch = null;

    private ?string $lastTechnicalAdviserSearch = null;

    private ?string $lastDeanSearch = null;

    private ?string $lastAuthorSearch = null;

    // Flag to prevent duplicate cache queries
    private bool $researchAdvisersLoaded = false;

    private bool $technicalAdvisersLoaded = false;

    private bool $deansLoaded = false;

    private bool $authorsLoaded = false;

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        // Initialize empty collections to avoid null references
        $this->form->research_adviser_options = collect();
        $this->form->technical_adviser_options = collect();
        $this->form->dean_options = collect();
        $this->form->author_options = collect();
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
            'academic_papers_%s_%s_%s_%s_%s_%s_%s_%s_%s_%s_%d_%d_v%d',
            $this->dept ?? 'all',
            $this->search ?: 'no_search',
            $this->statusFilter ?: 'all_status',
            $this->yearFilter ?: 'all_years',
            $this->departmentFilter ?: 'all_depts',
            $this->paperTypeFilter ?: 'all_types',
            $this->yearFromFilter ?: 'no_from',
            $this->yearToFilter ?: 'no_to',
            $this->sortBy['column'],
            $this->sortBy['direction'],
            $this->perPage,
            $this->paginators['academic-papers-index'] ?? 1,
            $version
        );

        // Use cache for non-search/filter queries with short TTL
        if (empty($this->search) && empty($this->statusFilter) && empty($this->yearFilter) && empty($this->departmentFilter) && empty($this->paperTypeFilter) && empty($this->yearFromFilter) && empty($this->yearToFilter)) {
            $paginated = Cache::remember($cacheKey, 60, function () {
                return $this->buildAcademicPapersQuery()->paginate($this->perPage, pageName: 'academic-papers-index');
            });
        } else {
            // For search/filter queries, don't cache as they're more dynamic
            $paginated = $this->buildAcademicPapersQuery()->paginate($this->perPage, pageName: 'academic-papers-index');
        }

        // Transform items to include status and borrowability as direct properties
        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
            // Check if any copies are borrowed - if so, the paper cannot be deleted
            $paper->has_borrowed_copies = $paper->copies->contains('status', 'Unavailable');
            $paper->can_delete = ! $paper->has_borrowed_copies;

            return $paper;
        });

        return $paginated;
    }

    #[Computed(persist: true, cache: true)]
    public function availableYears()
    {
        // Lazy-loaded and cached for better initial load performance
        // Get min and max years from database
        $minYear = AcademicPaper::min('publication_year');
        $maxYear = AcademicPaper::max('publication_year');

        if (! $minYear || ! $maxYear) {
            return collect();
        }

        // Generate complete range from min to max (no gaps)
        return collect(range($maxYear, $minYear))->values();
    }

    #[Computed(persist: true, cache: true)]
    public function availableDepartments()
    {
        // Lazy-loaded and cached for better initial load performance
        return AcademicPaper::distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values();
    }

    #[Computed(persist: true, cache: true)]
    public function availablePaperTypes()
    {
        // Lazy-loaded and cached for better initial load performance
        return AcademicPaper::distinct()
            ->orderBy('paper_type')
            ->pluck('paper_type')
            ->filter()
            ->values();
    }

    /**
     * Build the academic papers query with filters and relationships
     */
    private function buildAcademicPapersQuery()
    {
        // Optimize: Only eager load what's displayed in table view
        // Authors, advisers, and full copy details are loaded lazily in detail modal
        return AcademicPaper::query()
            ->with([
                'copies' => function ($query) {
                    $query->select('id', 'academic_paper_id', 'status');
                },
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
                        ->orWhere('department', 'like', $search)
                        ->orWhereHas('authors', function ($authorQuery) use ($search) {
                            $authorQuery->where('name', 'like', $search);
                        })
                        ->orWhereHas('researchAdviser', function ($adviserQuery) use ($search) {
                            $adviserQuery->where('name', 'like', $search);
                        })
                        ->orWhereHas('technicalAdviser', function ($adviserQuery) use ($search) {
                            $adviserQuery->where('name', 'like', $search);
                        })
                        ->orWhereHas('dean', function ($deanQuery) use ($search) {
                            $deanQuery->where('name', 'like', $search);
                        });
                });
            })
            ->when($this->yearFilter, function ($query) {
                $query->where('publication_year', $this->yearFilter);
            })
            ->when($this->departmentFilter, function ($query) {
                $query->where('department', $this->departmentFilter);
            })
            ->when($this->paperTypeFilter, function ($query) {
                $query->where('paper_type', $this->paperTypeFilter);
            })
            ->when($this->yearFromFilter, function ($query) {
                $query->where('publication_year', '>=', $this->yearFromFilter);
            })
            ->when($this->yearToFilter, function ($query) {
                $query->where('publication_year', '<=', $this->yearToFilter);
            })
            // Apply status filter at query level for better performance
            ->when($this->statusFilter, function ($query) {
                if ($this->statusFilter === 'Available') {
                    $query->whereHas('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                } elseif ($this->statusFilter === 'Unavailable') {
                    $query->whereDoesntHave('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                }
            })
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                },
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

    public function updatedStatusFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedPaperTypeFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearFromFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    public function updatedYearToFilter(): void
    {
        $this->resetPage('academic-papers-index');
    }

    // Clear all filters and reset to default state
    public function clearFilters(): void
    {
        $this->reset([
            'statusFilter',
            'paperTypeFilter',
            'departmentFilter',
            'yearFromFilter',
            'yearToFilter',
        ]);
        $this->resetPage('academic-papers-index');
    }

    // Perform deletion (called from modal)
    public function performDelete(?int $paperId = null): void
    {
        // Only admin and super_admin can delete academic papers
        if (!Auth::check() || !Auth::user()->hasAdminAccess()) {
            $this->error('Only administrators can delete academic papers.');
            $this->deleteId = null;
            $this->dispatch('close-delete-modal');

            return;
        }

        // Use parameter if provided, otherwise fall back to property
        $deleteId = $paperId ?? $this->deleteId;

        if (! $deleteId) {
            return;
        }

        try {
            $academicPaper = AcademicPaper::with('copies')->findOrFail($deleteId);

            // Check if any copies are borrowed (status = 'Unavailable')
            $borrowedCopies = $academicPaper->copies()->where('status', 'Unavailable')->count();

            if ($borrowedCopies > 0) {
                $this->error(
                    "Cannot delete this academic paper. {$borrowedCopies} " .
                        ($borrowedCopies === 1 ? 'copy is' : 'copies are') .
                        ' currently borrowed.',
                    'Deletion Not Allowed'
                );
                $this->deleteId = null;
                $this->dispatch('close-delete-modal');

                return;
            }

            // Delete the paper (will cascade delete available copies)
            $academicPaper->delete();

            // Invalidate ALL related caches
            $this->incrementAcademicPapersVersion();

            // Clear all academic papers cache keys
            $cacheKeys = Cache::get('academic_papers_cache_keys', []);
            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            // Reset state
            $this->deleteId = null;

            // Reset pagination to first page
            $this->resetPage('academic-papers-index');

            // Show success message
            $this->success('Academic paper deleted successfully');

            // Dispatch event to close modal (only on success)
            $this->dispatch('close-delete-modal');
        } catch (\Exception $e) {
            // On error, show message but don't close modal
            $this->error('Failed to delete academic paper: ' . $e->getMessage());
            $this->deleteId = null;
        }
    }

    /**
     * Get initial copy count for edit mode (computed to avoid payload)
     */
    #[Computed]
    public function initialCopyCount(): ?int
    {
        if (! $this->isEditing || ! $this->form->academicPaperId) {
            return null;
        }

        // Use cached count to avoid extra query
        return Cache::remember(
            "academic_paper_{$this->form->academicPaperId}_copy_count",
            60, // 1 minute cache
            fn() => AcademicPaper::find($this->form->academicPaperId)?->copies()->count()
        );
    }

    // Open drawer for creating new academic paper
    public function create(): void
    {
        // Only admin and super_admin can create academic papers
        if (!Auth::check() || !Auth::user()->hasAdminAccess()) {
            $this->error('Only administrators can create academic papers.');

            return;
        }

        $this->isEditing = false;
        $this->form->reset(); // This already calls populateYearChoices() and loadStaticChoices()
        $this->resetErrorBag(); // Clear any previous validation errors

        // Only load search options if not already cached
        if ($this->cachedResearchAdvisers === null) {
            $this->searchResearchAdvisers('');
        }
        if ($this->cachedTechnicalAdvisers === null) {
            $this->searchTechnicalAdvisers('');
        }
        if ($this->cachedDeans === null) {
            $this->searchDeans('');
        }
        if ($this->cachedAuthors === null) {
            $this->searchAuthors('');
        }

        $this->formDrawer = true;
    }

    // Open drawer for editing existing academic paper
    public function edit(int $id): void
    {
        // Only admin and super_admin can edit academic papers
        if (!Auth::check() || !Auth::user()->hasAdminAccess()) {
            $this->error('Only administrators can edit academic papers.');

            return;
        }

        $this->resetErrorBag(); // Clear any previous validation errors

        // Always reload the paper data to ensure fresh state
        $academicPaper = AcademicPaper::with([
            'authors' => function ($query) {
                $query->select('authors.id', 'authors.name');
            },
            'copies' => function ($query) {
                $query->select('id', 'academic_paper_id', 'status');
            },
        ])->findOrFail($id);
        $this->form->setAcademicPaper($academicPaper);

        $this->isEditing = true;

        // Only load search options if not already cached
        if ($this->cachedResearchAdvisers === null) {
            $this->searchResearchAdvisers('');
        }
        if ($this->cachedTechnicalAdvisers === null) {
            $this->searchTechnicalAdvisers('');
        }
        if ($this->cachedDeans === null) {
            $this->searchDeans('');
        }
        if ($this->cachedAuthors === null) {
            $this->searchAuthors('');
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

        // Clear copy count cache for this paper
        if ($this->form->academicPaperId) {
            Cache::forget("academic_paper_{$this->form->academicPaperId}_copy_count");
        }

        $this->formDrawer = false;
        $this->isEditing = false;
        $this->form->reset();
        $this->form->populateYearChoices();
        $this->resetPage('academic-papers-index');
    }

    // Search method for research advisers with caching
    public function searchResearchAdvisers(string $value = '')
    {
        // Check if we have cached results for the same search
        if ($this->lastResearchAdviserSearch === $value && $this->cachedResearchAdvisers !== null) {
            $this->form->research_adviser_options = collect($this->cachedResearchAdvisers);

            return $this->cachedResearchAdvisers;
        }

        // Prevent duplicate loading for empty searches - more aggressive check
        if ($value === '' && ($this->researchAdvisersLoaded || $this->cachedResearchAdvisers !== null)) {
            $this->form->research_adviser_options = collect($this->cachedResearchAdvisers);

            return $this->cachedResearchAdvisers;
        }

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'research_advisers_all' : null;
        if ($cacheKey) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                $advisers = collect($cachedData);
            } else {
                // Get search results from database
                $advisers = \App\Models\ResearchAdviser::query()
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
            $advisers = \App\Models\ResearchAdviser::query()
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
        if (! empty($this->form->research_adviser_id)) {
            $selectedAdviser = \App\Models\ResearchAdviser::find($this->form->research_adviser_id);
            if ($selectedAdviser) {
                $selectedOption = collect([['id' => $selectedAdviser->id, 'name' => $selectedAdviser->name]]);
                $advisers = $advisers->merge($selectedOption)->unique('id');
            }
        }

        // Cache the results for this request
        $this->cachedResearchAdvisers = $advisers->toArray();
        $this->lastResearchAdviserSearch = $value;
        $this->form->research_adviser_options = $advisers;

        // Mark as loaded for empty searches
        if ($value === '') {
            $this->researchAdvisersLoaded = true;
        }

        return $this->cachedResearchAdvisers;
    }

    // Search method for technical advisers with caching
    public function searchTechnicalAdvisers(string $value = '')
    {
        // Check if we have cached results for the same search
        if ($this->lastTechnicalAdviserSearch === $value && $this->cachedTechnicalAdvisers !== null) {
            $this->form->technical_adviser_options = collect($this->cachedTechnicalAdvisers);

            return $this->cachedTechnicalAdvisers;
        }

        // Prevent duplicate loading for empty searches - more aggressive check
        if ($value === '' && ($this->technicalAdvisersLoaded || $this->cachedTechnicalAdvisers !== null)) {
            $this->form->technical_adviser_options = collect($this->cachedTechnicalAdvisers);

            return $this->cachedTechnicalAdvisers;
        }

        // Use cache for common searches (empty or very short searches)
        $cacheKey = $value === '' ? 'technical_advisers_all' : null;
        if ($cacheKey) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                $advisers = collect($cachedData);
            } else {
                // Get search results from database
                $advisers = \App\Models\TechnicalAdviser::query()
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
            $advisers = \App\Models\TechnicalAdviser::query()
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
        if (! empty($this->form->technical_adviser_id)) {
            $selectedAdviser = \App\Models\TechnicalAdviser::find($this->form->technical_adviser_id);
            if ($selectedAdviser) {
                $selectedOption = collect([['id' => $selectedAdviser->id, 'name' => $selectedAdviser->name]]);
                $advisers = $advisers->merge($selectedOption)->unique('id');
            }
        }

        // Cache the results for this request
        $this->cachedTechnicalAdvisers = $advisers->toArray();
        $this->lastTechnicalAdviserSearch = $value;
        $this->form->technical_adviser_options = $advisers;

        // Mark as loaded for empty searches
        if ($value === '') {
            $this->technicalAdvisersLoaded = true;
        }

        return $this->cachedTechnicalAdvisers;
    }

    // Search method for authors with caching
    public function searchAuthors(string $value = '')
    {
        // Request-level caching for repeated searches within the same request
        if ($this->lastAuthorSearch === $value && $this->cachedAuthors !== null && $this->authorsLoaded) {
            return $this->cachedAuthors;
        }

        $this->lastAuthorSearch = $value;

        // Use persistent cache with short TTL for search results
        $cacheKey = 'author_search_' . md5(strtolower(trim($value)));

        $results = Cache::remember($cacheKey, 300, function () use ($value) {
            $query = \App\Models\Author::query()
                ->select('id', 'name')
                ->orderBy('name');

            if (!empty($value)) {
                $query->where('name', 'like', '%' . $value . '%');
            }

            return $query->limit(50)->get();
        });

        // Always include selected authors in options for correct tag rendering
        $selectedIds = $this->form->author_ids ?? [];
        $selectedAuthors = !empty($selectedIds)
            ? \App\Models\Author::whereIn('id', $selectedIds)
            ->select('id', 'name')
            ->get()
            : collect();

        // Merge search results and selected authors, remove duplicates
        $merged = $results->concat($selectedAuthors)->unique('id')->values();

        // Transform to MaryUI format
        $options = $merged->map(function ($author) {
            return [
                'id' => $author->id,
                'name' => $author->name,
            ];
        });

        // Update form options
        $this->form->author_options = $options;

        // Cache in memory for this request
        $this->cachedAuthors = $options->toArray();
        $this->authorsLoaded = true;

        return $this->cachedAuthors;
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
        if (! empty($this->form->dean_id)) {
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
        Cache::forget('research_advisers_all');
        Cache::forget('technical_advisers_all');
        Cache::forget('deans_all');
        Cache::forget('authors_all');
        $this->cachedAuthors = null;

        // Increment version token to invalidate all academic papers caches
        $this->incrementAcademicPapersVersion();
    }

    /**
     * Clear request-level caches to force fresh data
     */
    private function clearRequestCaches(): void
    {
        $this->cachedResearchAdvisers = null;
        $this->cachedTechnicalAdvisers = null;
        $this->cachedDeans = null;
        $this->cachedAuthors = null;
        $this->lastResearchAdviserSearch = null;
        $this->lastTechnicalAdviserSearch = null;
        $this->lastDeanSearch = null;
        $this->lastAuthorSearch = null;
        $this->researchAdvisersLoaded = false;
        $this->technicalAdvisersLoaded = false;
        $this->deansLoaded = false;
        $this->authorsLoaded = false;
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
        if (! $this->selectedPaperId) {
            return null;
        }

        return AcademicPaper::with([
            'authors' => fn($q) => $q->select('authors.id', 'authors.name'),
            'researchAdviser:id,name',
            'technicalAdviser:id,name',
            'dean:id,name',
            'copies' => fn($q) => $q->select('id', 'academic_paper_id', 'copy_number', 'status'),
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
        $this->dispatch('paper-modal');
    }

    /**
     * Set deleteId and open delete modal
     */
    public function confirmDelete(int $paperId): void
    {
        // Only admin and super_admin can delete academic papers
        if (!Auth::check() || !Auth::user()->hasAdminAccess()) {
            $this->error('Only administrators can delete academic papers.');

            return;
        }

        $this->deleteId = $paperId;
        $this->dispatch('delete-modal');
    }

    /**
     * Set copyToDelete and open copy delete modal
     */
    public function confirmCopyDelete(int $copyId): void
    {
        $this->copyToDelete = $copyId;
        $this->dispatch('copy-delete-modal');
    }

    public function performCopyDelete(?int $copyId = null): void
    {
        // Use parameter if provided, otherwise fall back to property
        $copyToDelete = $copyId ?? $this->copyToDelete;

        if (! $copyToDelete) {
            return;
        }

        try {
            $copy = \App\Models\Inventory::findOrFail($copyToDelete);

            // Admin pages are already protected by middleware
            if ($copy->status !== 'Available') {
                // On validation error, show message but don't close modal
                $this->error("Cannot delete copy #{$copyToDelete}. It may be borrowed or not found.", 'Delete Failed!');
                $this->copyToDelete = null;

                return;
            }

            $copy->delete();

            // Invalidate caches to reflect the change
            $this->invalidateSearchCaches();

            // Clear copy count cache for this paper
            if ($copy->academic_paper_id) {
                Cache::forget("academic_paper_{$copy->academic_paper_id}_copy_count");

                // If the deleted copy belongs to the currently loaded form paper, rehydrate the form
                if ($this->isEditing && $this->form->academicPaperId === $copy->academic_paper_id) {
                    $freshPaper = AcademicPaper::with([
                        'authors' => function ($query) {
                            $query->select('authors.id', 'authors.name');
                        },
                        'copies' => function ($query) {
                            $query->select('id', 'academic_paper_id', 'status');
                        },
                    ])->find($copy->academic_paper_id);

                    if ($freshPaper) {
                        $this->form->setAcademicPaper($freshPaper);
                    }
                }
            }

            $this->copyToDelete = null;

            $this->success("Copy #{$copy->copy_number} deleted successfully", 'Copy Deleted!');

            // Dispatch event to close modal (only on success)
            $this->dispatch('close-copy-delete-modal');
        } catch (\Exception $e) {
            // On error, show message but don't close modal
            $this->error('Failed to delete copy: ' . $e->getMessage());
            $this->copyToDelete = null;
        }
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (! $this->selectedPaper || ! $this->selectedPaper->department) {
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
     * Check if current user has admin access
     */
    #[Computed]
    public function hasAdminAccess(): bool
    {
        return Auth::check() && Auth::user()->hasAdminAccess();
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

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('livewire.pages.admin.admin-academic-paper-index-placeholder');
    }

    public function render()
    {
        return view('livewire.pages.Admin.admin-academic-paper-index');
    }
}
