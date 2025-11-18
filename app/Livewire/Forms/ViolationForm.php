<?php

namespace App\Livewire\Forms;

use App\Models\Violation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ViolationForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string')]
    public string $description = '';

    #[Validate('required|integer|min:1|max:100')]
    public int $penalty_score = 1;

    public function store(): void
    {
        $this->validate();

        DB::transaction(function () {
            Violation::create([
                'name' => $this->name,
                'description' => $this->description,
                'penalty_score' => $this->penalty_score,
            ]);
        });
    }

    public function update(int $id): void
    {
        $this->validate();

        $violation = Violation::findOrFail($id);
        $violation->update([
            'name' => $this->name,
            'description' => $this->description,
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
