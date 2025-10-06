<?php

namespace App\Livewire\Forms;

use App\Models\RuleRegulation;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RuleAndRegulationForm extends Form
{
    #[Validate('required|integer|exists:rule_headers,id')]
    public ?int $rule_header_id = null;

    #[Validate('required|string|min:1')]
    public string $content = '';

    public function store(): void
    {
        $this->validate();

        // Get the last order for this specific header
        $lastOrder = RuleRegulation::where('rule_header_id', $this->rule_header_id)->max('order') ?? 0;

        RuleRegulation::create([
            'rule_header_id' => $this->rule_header_id,
            'content' => $this->content,
            'order' => $lastOrder + 1,
        ]);
    }
    public function update(int $id): void
    {
        $this->validate();

        $rule = RuleRegulation::findOrFail($id);
        $rule->update([
            'rule_header_id' => $this->rule_header_id,
            'content' => $this->content,
        ]);
    }
}
