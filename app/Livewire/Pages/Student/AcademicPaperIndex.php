<?php

namespace App\Livewire\Pages\Student;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Inventory;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use App\Services\AiService;
use App\Services\AvailabilityService;
use App\Services\SimilarPapersService;
use App\Traits\CreatesQrCanonicalMessage;
use Auth;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Title('Academic Paper List')]
#[Layout('components.layouts.app')]
#[Lazy]
class AcademicPaperIndex extends Component
{
    use CreatesQrCanonicalMessage, WithPagination;

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public array $headers = [];

    public int $perPage = 10;

    public ?string $dept = null;

    #[Validate('string|max:100|nullable')]
    public string $search = '';

    // Filters
    #[Validate('string|max:20|nullable')]
    public string $statusFilter = '';

    #[Validate('string|max:20|nullable')]
    public string $yearFilter = '';

    #[Validate('string|max:100|nullable')]
    public string $departmentFilter = '';

    #[Validate('string|max:20|nullable')]
    public string $paperTypeFilter = '';

    #[Validate('string|max:20|nullable')]
    public string $yearFromFilter = '';

    #[Validate('string|max:20|nullable')]
    public string $yearToFilter = '';

    #[Validate('string|max:100|nullable')]
    public string $authorFilter = '';

    #[Validate('string|max:100|nullable')]
    public string $adviserFilter = '';

    public bool $paperTabActive = false;

    // Hybrid search state (sidecar-ordered results + fallback flag)
    public ?array $hybridResults = null;

    public bool $aiSearchFailed = false;

    // Recommendations mode state (seed paper + sidecar-ordered results)
    public ?int $recommendedFor = null;

    public ?array $recommendations = null;

    public bool $recommendationsUnavailable = false;

    public ?string $recommendedTitle = null;

    public array $recommendationsSnapshot = [];

    // Store IDs only (modals controlled by Alpine.js) — synced to ?paper= for canonical citation URLs.
    #[Url(as: 'paper')]
    public ?int $selectedPaperId = null;

    // QR Code properties
    public ?string $qrCode = null;

    public ?int $selectedCopyId = null;

    public function updatingPerPage(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
    }

    public function mount(?string $dept = null)
    {
        $this->dept = $dept;
        $this->sortBy = ['column' => 'id', 'direction' => 'asc'];
        $this->headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'catalog_code', 'label' => 'Catalog Code'],
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'publication_year', 'label' => 'Year'],
            ['key' => 'status', 'label' => 'Status', 'class' => 'font-semibold'],
            ['key' => 'actions', 'label' => ''],
        ];

        // Canonical ?paper= param: open the detail modal inline instead of a separate page.
        // #[Url] hydrates selectedPaperId from ?paper= automatically; we only need to trigger the Alpine modal.
        $paperId = $this->selectedPaperId ?? request()->query('paper');
        if ($paperId !== null && ctype_digit((string) $paperId) && AcademicPaper::whereKey((int) $paperId)->exists()) {
            $this->selectedPaperId = (int) $paperId;
            $this->dispatch('open-paper-modal');
        } elseif ($paperId !== null) {
            $this->selectedPaperId = null;
        }
    }

    /**
     * Check if the current user can borrow papers
     */
    public function getCanBorrowProperty(): bool
    {
        return Auth::check() && Auth::user()->credit_score > 0;
    }

    #[Computed]
    public function academicPapers()
    {
        // Optimize: Eager load primary relations displayed in list view
        $query = AcademicPaper::query()
            ->with(['authors:id,name', 'copies:id,academic_paper_id,status'])
            ->when($this->dept, function ($q) {
                $departmentName = $this->resolveDepartmentName($this->dept);
                if ($departmentName) {
                    $q->where('department', $departmentName);
                }
            })
            ->when($this->search, function ($q) {
                $search = '%'.$this->search.'%';
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', $search)
                        ->orWhere('catalog_code', 'like', $search)
                        ->orWhere('department', 'like', $search)
                        ->orWhereHas('authors', function ($q) use ($search) {
                            $q->where('name', 'like', $search);
                        });
                });
            })
            ->when($this->yearFilter, function ($q) {
                $q->where('publication_year', $this->yearFilter);
            })
            ->when($this->departmentFilter, function ($q) {
                $q->where('department', $this->departmentFilter);
            })
            ->when($this->paperTypeFilter, function ($q) {
                $q->where('paper_type', $this->paperTypeFilter);
            })
            ->when($this->yearFromFilter, function ($q) {
                $q->where('publication_year', '>=', $this->yearFromFilter);
            })
            ->when($this->yearToFilter, function ($q) {
                $q->where('publication_year', '<=', $this->yearToFilter);
            })
            // Apply status filter at query level for better performance
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'Available') {
                    $q->whereHas('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                } elseif ($this->statusFilter === 'Unavailable') {
                    $q->whereDoesntHave('copies', function ($copyQuery) {
                        $copyQuery->where('status', 'Available');
                    });
                }
            })

            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                },
            ]);

        if ($this->sortBy['column'] === 'status') {
            $query->orderBy('available_copies', $this->sortBy['direction']);
        } else {
            $query->orderBy(...array_values($this->sortBy));
        }

        $paginated = $query->paginate($this->perPage, pageName: 'academic-papers-index');

        // Transform to add computed status property
        $paginated->getCollection()->transform(function ($paper) {
            $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';

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

    #[Computed(persist: true, cache: true)]
    public function availableAuthors(): Collection
    {
        // Lazy-loaded and cached for better initial load performance
        return Author::distinct()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
    }

    #[Computed(persist: true, cache: true)]
    public function availableAdvisers(): Collection
    {
        // Lazy-loaded and cached for better initial load performance
        return collect(ResearchAdviser::orderBy('name')->pluck('name'))
            ->concat(TechnicalAdviser::orderBy('name')->pluck('name'))
            ->unique()
            ->sort()
            ->values();
    }

    public function updatedDept(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
    }

    public function updatedSearch(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedStatusFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');

        // The sidecar cannot filter by availability (it is resolved live),
        // so exit hybrid mode and let the SQL path apply the status filter.
        $this->exitHybridMode();
    }

    public function updatedYearFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedPaperTypeFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedYearFromFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedYearToFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedAuthorFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    public function updatedAdviserFilter(): void
    {
        $this->exitRecommendationsMode();
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    // Clear all filters and reset to default state
    public function clearFilters(): void
    {
        $this->exitRecommendationsMode();
        $this->reset([
            'statusFilter',
            'paperTypeFilter',
            'departmentFilter',
            'yearFromFilter',
            'yearToFilter',
            'authorFilter',
            'adviserFilter',
        ]);
        $this->resetPage('academic-papers-index');
        $this->runHybridSearch();
    }

    /**
     * Run hybrid search against the AI sidecar for the current query +
     * filter state. Falls back silently to the local SQL search on failure.
     *
     * The ≥3-char topic gate stands, but in the paper tab an author or
     * adviser selection is a standalone search axis (UI-SPEC empty state:
     * "choose an author or adviser to find papers"): with no usable topic,
     * the selected name becomes the query (title-as-query precedent, ADR
     * 0011), and the name filter still narrows the ranking (Spec review S-4).
     */
    public function runHybridSearch(): void
    {
        $query = trim($this->search);

        if (strlen($query) < 3 && $this->paperTabActive) {
            // Review nit 3: the name-as-query fallback is a paper-tab-only
            // behaviour — browse mode must keep the pre-S-4 verbatim payload,
            // so the trim above is only applied to the tab-mode query below.
            $query = $this->authorFilter ?: $this->adviserFilter;
        }

        if (strlen((string) $query) < 3 || $this->statusFilter !== '') {
            $this->exitHybridMode();

            return;
        }

        // Browse mode ships the user's search verbatim (byte-identical to
        // pre-S-4); only the paper tab sends the normalized query.
        if (! $this->paperTabActive) {
            $query = $this->search;
        }

        $filters = [
            'paper_type' => $this->paperTypeFilter ?: null,
            'department' => $this->departmentFilter ?: null,
            'publication_year' => $this->yearFilter ?: null,
            'year_from' => $this->yearFromFilter ?: null,
            'year_to' => $this->yearToFilter ?: null,
        ];

        if ($this->paperTabActive) {
            $filters['author'] = $this->authorFilter ?: null;
            $filters['adviser'] = $this->adviserFilter ?: null;
        }

        try {
            $results = (new AiService)->search($query, $filters, 'catalog', 10);
        } catch (AiServiceUnavailableException|AiServiceAuthException) {
            $this->hybridResults = null;
            $this->aiSearchFailed = true;

            return;
        }

        $ids = collect($results['results'] ?? [])
            ->map(fn ($result) => (int) str_replace('paper-', '', $result['id']))
            ->filter()
            ->all();

        $papers = AcademicPaper::with(['authors:id,name', 'copies:id,academic_paper_id,status'])
            ->withCount([
                'copies as available_copies' => function ($query) {
                    $query->where('status', 'Available');
                },
            ])
            ->findMany($ids);

        $byId = $papers->keyBy('id');

        // Preserve the sidecar rank order (findMany returns DB order).
        $this->hybridResults = collect($ids)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->map(function ($paper) {
                $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';

                return $paper;
            })
            ->values()
            ->all();

        $this->aiSearchFailed = false;
    }

    /**
     * Leave hybrid mode: clear sidecar results and the failure flag so the
     * SQL path renders instead.
     */
    private function exitHybridMode(): void
    {
        $this->hybridResults = null;
        $this->aiSearchFailed = false;
    }

    private function exitRecommendationsMode(): void
    {
        $this->recommendedFor = null;
        $this->recommendations = null;
        $this->recommendationsUnavailable = false;
        $this->recommendationsSnapshot = [];
    }

    public function showSimilar(int $paperId): void
    {
        $this->recommendationsSnapshot = [
            'search' => $this->search,
            'statusFilter' => $this->statusFilter,
            'yearFilter' => $this->yearFilter,
            'departmentFilter' => $this->departmentFilter,
            'paperTypeFilter' => $this->paperTypeFilter,
            'yearFromFilter' => $this->yearFromFilter,
            'yearToFilter' => $this->yearToFilter,
            'authorFilter' => $this->authorFilter,
            'adviserFilter' => $this->adviserFilter,
            'paperTabActive' => $this->paperTabActive,
            'hybridResults' => $this->hybridResults,
            'aiSearchFailed' => $this->aiSearchFailed,
            'page' => $this->getPage('academic-papers-index'),
            'sortBy' => $this->sortBy,
        ];

        $paper = AcademicPaper::find($paperId);
        if (! $paper) {
            return;
        }

        $this->recommendedFor = $paperId;
        $this->recommendedTitle = $paper->title;

        $service = new SimilarPapersService;
        $this->recommendations = $service->for($paper, 10)->all();
        $this->recommendationsUnavailable = $service->unavailable;

        if ($this->recommendationsUnavailable) {
            $this->recommendations = [];
        }
    }

    public function backToResults(): void
    {
        if ($this->recommendedFor === null) {
            return;
        }

        $snapshot = $this->recommendationsSnapshot;

        $this->search = $snapshot['search'];
        $this->statusFilter = $snapshot['statusFilter'];
        $this->yearFilter = $snapshot['yearFilter'];
        $this->departmentFilter = $snapshot['departmentFilter'];
        $this->paperTypeFilter = $snapshot['paperTypeFilter'];
        $this->yearFromFilter = $snapshot['yearFromFilter'];
        $this->yearToFilter = $snapshot['yearToFilter'];
        $this->authorFilter = $snapshot['authorFilter'];
        $this->adviserFilter = $snapshot['adviserFilter'];
        $this->paperTabActive = $snapshot['paperTabActive'];
        $this->hybridResults = $snapshot['hybridResults'];
        $this->aiSearchFailed = $snapshot['aiSearchFailed'];
        $this->setPage($snapshot['page'], 'academic-papers-index');
        $this->sortBy = $snapshot['sortBy'];

        $this->recommendedFor = null;
        $this->recommendations = null;
        $this->recommendationsUnavailable = false;
        $this->recommendedTitle = null;
        $this->recommendationsSnapshot = [];
    }

    public function showPaperDetails(int $paperId): void
    {
        $this->selectedPaperId = $paperId;
        $this->dispatch('open-paper-modal');
    }

    public function clearPaperSelection(): void
    {
        $this->selectedPaperId = null;
    }

    #[Computed]
    public function availability(): array
    {
        $ids = collect($this->academicPapers->pluck('id'))
            ->merge(collect($this->hybridResults ?? [])->pluck('id'))
            ->merge(collect($this->recommendations ?? [])->pluck('id'))
            ->unique()
            ->values()
            ->all();

        return (new AvailabilityService)->forPapers($ids);
    }

    #[Computed]
    public function selectedPaper(): ?AcademicPaper
    {
        if (! $this->selectedPaperId) {
            return null;
        }

        return AcademicPaper::with([
            'authors' => fn ($q) => $q->select('authors.id', 'authors.name'),
            'researchAdviser:id,name',
            'technicalAdviser:id,name',
            'dean:id,name',
            'copies' => fn ($q) => $q->select('id', 'academic_paper_id', 'copy_number', 'status'),
        ])->find($this->selectedPaperId);
    }

    public function requestQr(int $inventoryId): void
    {
        // 1. grab the copy (inventory row)
        $copy = Inventory::with('academicPaper')->find($inventoryId);

        if (! $copy) {
            session()->flash('error', 'Copy not found.');

            return;
        }

        if (! $copy->isAvailable()) {
            session()->flash('error', 'This copy is not available.');

            return;
        }

        // Store only the copy ID to avoid serializing models in Livewire state
        $this->selectedCopyId = $copy->id;

        // 3) Build encrypted payload with TTL (e.g., 5 minutes)
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addMinutes(5);
        $payload = [
            'inventory_id' => $copy->id,
            'paper_id' => $copy->academic_paper_id,
            'catalog_code' => $copy->academicPaper->catalog_code,
            'title' => $copy->academicPaper->title,
            'requested_by' => Auth::id(),
            'lat' => Auth::user()->email, // Add email for compatibility
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        // Encrypt the QR payload
        $qrPayload = $this->createEncryptedQrMessage($payload);

        // 4) Create SVG and base64 for modal - use same settings as attendance QR for better scannability
        $svg = QrCode::size(400)  // Larger size like attendance QR
            ->margin(8)  // Quiet zone margin for better scanning
            ->errorCorrection('M')  // Medium error correction for better reliability
            ->generate($qrPayload);
        $this->qrCode = base64_encode($svg);

        $this->dispatch('open-qr-modal');
    }

    public function closeQrModal(): void
    {
        $this->qrCode = null;
        $this->selectedCopyId = null;
        $this->dispatch('close-qr-modal');
    }

    public function getDownloadUrl()
    {
        if (! $this->selectedCopy) {
            return null;
        }

        // Generate a temporary signed URL valid for 5 minutes
        return route('qr-code.download', [
            'inventoryId' => $this->selectedCopyId,
        ]);
    }

    #[Computed]
    public function downloadUrl()
    {
        return $this->getDownloadUrl();
    }

    private function resolveDepartmentName(?string $dept): ?string
    {
        if (! $dept) {
            return null;
        }

        $mapping = config('departments.mapping', []);
        $validNames = config('departments.valid_names', []);

        if (isset($mapping[$dept])) {
            return $mapping[$dept];
        }

        if (in_array($dept, $validNames)) {
            return $dept;
        }

        return null;
    }

    #[Computed]
    public function selectedCopy()
    {
        if (! $this->selectedCopyId) {
            return null;
        }

        return Inventory::with('academicPaper')->find($this->selectedCopyId);
    }

    #[Computed]
    public function departmentIcon(): string
    {
        if (! $this->selectedPaper || ! $this->selectedPaper->department) {
            return '';
        }

        $icons = config('departments.icons', []);
        $department = $this->selectedPaper->department;

        return isset($icons[$department]) ? asset($icons[$department]) : '';
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'Available' => 'badge-success',
            'Borrowed' => 'badge-warning',
            default => 'badge-error',
        };
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('livewire.pages.student.academic-paper-index-placeholder');
    }

    public function render()
    {
        return view('livewire.pages.student.academic-paper-index');
    }
}
