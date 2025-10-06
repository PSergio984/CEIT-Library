<?php

namespace App\Livewire\Pages\Student;

use App\Models\RuleHeader;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RuleAndRegulationIndex extends Component
{
    #[Computed]
    public function ruleHeaders(): Collection
    {
        return RuleHeader::with('ruleRegulations')
            ->orderBy('order')
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.student.rule-and-regulation-index');
    }
}
