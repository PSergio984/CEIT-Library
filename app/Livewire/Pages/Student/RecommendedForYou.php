<?php

namespace App\Livewire\Pages\Student;

use App\Services\AvailabilityService;
use App\Services\PersonalRecommendationsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class RecommendedForYou extends Component
{
    /**
     * The dashboard shelf in one payload: a render state, the hydrated
     * papers in rank order, and live availability keyed by paper id.
     * PersonalRecommendationsService already fails closed — this only
     * maps its flags to a state the view can render.
     */
    #[Computed]
    public function shelf(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return ['state' => 'empty', 'papers' => collect(), 'availability' => []];
        }

        $service = new PersonalRecommendationsService;
        $papers = $service->for($user);

        return [
            'state' => match (true) {
                $service->unavailable => 'unavailable',
                $papers->isEmpty() => 'empty',
                $service->coldStart => 'cold_start',
                default => 'populated',
            },
            'papers' => $papers,
            'availability' => (new AvailabilityService)->forPapers($papers->pluck('id')->all()),
        ];
    }

    /**
     * Skeleton shown while the lazy child loads — recommendations never
     * block the rest of the dashboard.
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading recommendations...',
            'subtext' => null,
            'minHeight' => '10rem',
            'size' => 'sm',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.student.recommended-for-you');
    }
}
