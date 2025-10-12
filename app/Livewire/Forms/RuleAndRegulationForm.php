<?php

namespace App\Livewire\Forms;

use App\Models\RuleRegulation;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () {
            $lastOrder = RuleRegulation::where('rule_header_id', $this->rule_header_id)
                ->lockForUpdate();

            RuleRegulation::create([
                'rule_header_id' => $this->rule_header_id,
                'content'        => $this->content,
            ]);
        });
    }

    public function update(int $id): void
    {
        $this->validate();

        $rule = RuleRegulation::findOrFail($id);
        $rule->update([
            'rule_header_id' => $this->rule_header_id,
            'content'        => $this->content,
        ]);
    }
}
