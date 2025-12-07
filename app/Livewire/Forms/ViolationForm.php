<?php

namespace App\Livewire\Forms;

use App\Models\Violation;
use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

class ViolationForm extends Form
{
    public string $name = '';

    public string $description = '';

    public int $penalty_score = 1;

    /**
     * Get validation rules for violation form
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s\-\'\.&,()]+$/u', // Letters, spaces, hyphens, apostrophes, periods, &, commas, parentheses
                new NoHtmlTags,
                new SafeText,
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:1000',
                new NoHtmlTags,
                new SafeText,
            ],
            'penalty_score' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The violation name is required.',
            'name.string' => 'The violation name must be valid text.',
            'name.min' => 'The violation name must be at least 3 characters.',
            'name.max' => 'The violation name cannot exceed 255 characters.',
            'name.regex' => 'The violation name can only contain letters, spaces, hyphens, and basic punctuation.',

            'description.required' => 'The description is required.',
            'description.string' => 'The description must be valid text.',
            'description.min' => 'The description must be at least 10 characters.',
            'description.max' => 'The description cannot exceed 1000 characters.',

            'penalty_score.required' => 'The penalty score is required.',
            'penalty_score.integer' => 'The penalty score must be a whole number.',
            'penalty_score.min' => 'The penalty score must be at least 1.',
            'penalty_score.max' => 'The penalty score cannot exceed 100.',
        ];
    }

    public function store(): void
    {
        $this->validate();

        DB::transaction(function () {
            Violation::create([
                'name' => trim($this->name),
                'description' => trim($this->description),
                'penalty_score' => $this->penalty_score,
            ]);
        });
    }

    public function update(int $id): void
    {
        $this->validate();

        $violation = Violation::findOrFail($id);
        $violation->update([
            'name' => trim($this->name),
            'description' => trim($this->description),
            'penalty_score' => $this->penalty_score,
        ]);
    }

    public function setViolation(Violation $violation): void
    {
        $this->name = $violation->name;
        $this->description = $violation->description;
        $this->penalty_score = $violation->penalty_score;
    }
}
