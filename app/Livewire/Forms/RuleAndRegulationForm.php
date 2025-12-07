<?php

namespace App\Livewire\Forms;

use App\Models\RuleRegulation;
use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

class RuleAndRegulationForm extends Form
{
    public ?int $rule_header_id = null;

    public string $content = '';

    /**
     * Get validation rules for rule and regulation form
     */
    public function rules(): array
    {
        return [
            'rule_header_id' => [
                'required',
                'integer',
                'min:1',
                'exists:rule_headers,id',
            ],
            'content' => [
                'required',
                'string',
                'min:5',
                'max:2000',
                new NoHtmlTags,
                new SafeText,
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'rule_header_id.required' => 'Please select a rule header.',
            'rule_header_id.integer' => 'Invalid rule header selection.',
            'rule_header_id.min' => 'Invalid rule header selection.',
            'rule_header_id.exists' => 'The selected rule header does not exist.',

            'content.required' => 'The rule content is required.',
            'content.string' => 'The content must be valid text.',
            'content.min' => 'The rule content must be at least 5 characters.',
            'content.max' => 'The rule content cannot exceed 2000 characters.',
        ];
    }

    public function store(): void
    {
        $this->validate();

        DB::transaction(function () {
            RuleRegulation::create([
                'rule_header_id' => $this->rule_header_id,
                'content' => trim($this->content),
            ]);
        });
    }

    public function update(int $id): void
    {
        $this->validate();

        $rule = RuleRegulation::findOrFail($id);
        $rule->update([
            'rule_header_id' => $this->rule_header_id,
            'content' => trim($this->content),
        ]);
    }
}
