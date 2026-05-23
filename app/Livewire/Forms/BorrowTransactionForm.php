<?php

namespace App\Livewire\Forms;

use App\Models\BorrowTransaction;
use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
use Carbon\Carbon;
use Livewire\Form;

class BorrowTransactionForm extends Form
{
    public ?int $transactionId = null;

    public string $status = 'started';

    public ?string $time_out = null;

    public string $notes = '';

    /**
     * Set form values from a transaction model.
     */
    public function setTransaction(BorrowTransaction $transaction): void
    {
        $this->transactionId = $transaction->id;
        $this->status = $transaction->status;
        $this->time_out = $transaction->time_out ? $transaction->time_out->format('Y-m-d\TH:i') : null;
        $this->notes = $transaction->notes ?? '';
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:started,completed',
            'time_out' => 'nullable|date',
            'notes' => ['nullable', 'string', 'max:255', new NoHtmlTags, new SafeText],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The transaction status is required.',
            'status.in' => 'The status must be either "started" or "completed".',
            'time_out.date' => 'Please provide a valid date and time for the timeout.',
            'notes.max' => 'The notes must not exceed 255 characters.',
        ];
    }

    /**
     * Update the transaction in the database.
     */
    public function update(): void
    {
        $this->validate();

        $transaction = BorrowTransaction::findOrFail($this->transactionId);

        if ($this->status === 'completed' && empty($this->time_out)) {
            $this->addError('time_out', 'Time Out is required when status is completed!');
            return;
        }

        \DB::transaction(function () use ($transaction) {
            $transaction->update([
                'status' => $this->status,
                'time_out' => $this->status === 'completed'
                    ? Carbon::parse($this->time_out)
                    : null,
                'notes' => $this->notes ?: null,
            ]);

            $inventory = $transaction->inventory()->lockForUpdate()->first();

            if ($inventory) {
                $inventory->update([
                    'status' => $this->status === 'completed' ? 'Available' : 'Unavailable',
                ]);
            }
        });
    }
}
