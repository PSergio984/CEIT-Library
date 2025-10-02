<?php

namespace App\Livewire\Pages\Admin;

use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AdminRuleAndRegulationIndex extends Component
{
    #[Computed]
    public function ruleHeaders(): Collection
    {
        return RuleHeader::with([
            'rules' => fn($query) => $query->orderBy('order', 'asc'),
        ])
            ->orderBy('order', 'asc')
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-rule-and-regulation-index');
    }
}
