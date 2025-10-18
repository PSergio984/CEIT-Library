<?php

namespace App\Livewire\Pages\Student;

use App\Models\RuleHeader;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Rules and Regulations')]
class RuleAndRegulationIndex extends Component
{
    #[Computed]
    public function ruleHeaders(): Collection
    {
        return RuleHeader::query()
            // remove any default/global ordering on RuleHeader
            ->reorder()
            ->with([
                'ruleRegulations' => function ($q) {
                    // remove any default ordering on the relation
                    $q->reorder()
                        ->orderByRaw('CASE WHEN content IS NULL OR TRIM(content) = \'\' THEN 1 ELSE 0 END')
                        ->orderByRaw('LOWER(TRIM(content)) ASC');
                },
            ])
            ->orderByRaw('CASE WHEN title IS NULL OR TRIM(title) = \'\' THEN 1 ELSE 0 END')
            ->orderByRaw('LOWER(TRIM(title)) ASC')
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.student.rule-and-regulation-index');
    }
}
