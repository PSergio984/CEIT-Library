<?php

namespace App\Livewire\Pages\Admin;

use App\Imports\CatalogImport;
use App\Jobs\ImportCatalogJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * Import/Export dropdown on the Academic Papers page. Import runs two
 * phases: a zero-write dry-run preview (per-sheet counts + row errors)
 * shown synchronously, then a queued commit that re-parses the stored
 * upload and notifies the uploader on completion.
 *
 * #81 adds the export/template items to this same dropdown and wires
 * the failure-report download slot left in the preview modal.
 */
class CatalogImportDropdown extends AdminComponent
{
    use Toast;
    use WithFileUploads;

    public bool $showImportModal = false;

    #[Validate('required|mimes:xlsx,xls|max:10240')]
    public $importFile = null;

    /** @var array{sheets: array<string, mixed>}|null */
    #[Locked]
    public ?array $preview = null;

    public function mount(): void
    {
        Gate::authorize('manage-academic-papers');
    }

    public function openImport(): void
    {
        Gate::authorize('manage-academic-papers');

        $this->resetImport();
        $this->showImportModal = true;
    }

    /**
     * Runs automatically once the workbook finishes uploading: validate
     * and dry-run it, then hold the report for the preview table.
     */
    public function updatedImportFile(): void
    {
        Gate::authorize('manage-academic-papers');

        $this->validate();
        $this->preview = null;

        try {
            $this->preview = (new CatalogImport)->preview($this->importFile->getRealPath());
        } catch (\Throwable) {
            $this->reset('importFile');
            $this->error('The workbook could not be read. Upload a valid .xlsx catalog file.');

            return;
        }

        // Bound the payload: only the first issues per sheet are rendered.
        foreach ($this->preview['sheets'] as &$sheet) {
            $sheet['message_count'] = count($sheet['messages']);
            $sheet['messages'] = array_slice($sheet['messages'], 0, 150);
        }
    }

    /**
     * Commit phase: hand the stored upload to the queued job, which
     * re-parses it rather than trusting preview state held in the
     * component.
     */
    public function confirmImport(): void
    {
        Gate::authorize('manage-academic-papers');

        if (! $this->importFile || ! $this->preview) {
            return;
        }

        $path = $this->importFile->store('catalog-imports', 'local');

        ImportCatalogJob::dispatch($path, (int) Auth::id());

        $this->resetImport();
        $this->showImportModal = false;

        $this->success('The import is running in the background — you will be notified when it finishes.', 'Import Queued');
    }

    public function closeImportModal(): void
    {
        $this->resetImport();
        $this->showImportModal = false;
    }

    private function resetImport(): void
    {
        $this->reset(['importFile', 'preview']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.pages.admin.catalog-import-dropdown');
    }
}
