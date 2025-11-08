<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AcademicPaperDetailModal extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public mixed $selectedPaper = null,
        public bool $isAdmin = false
    ) {
        //
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
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.academic-paper-detail-modal');
    }
}
