<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\RuleAndRegulationForm;
use App\Models\RuleHeader;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('components.layouts.app')]
class CreateRuleAndRegulation extends AdminComponent
{
    use Toast;

    public RuleAndRegulationForm $form;

    public function save(): void
    {
        $this->form->store();

        $this->toast(
            type: 'success',
            title: 'Rule Created',
            description: 'The rule has been created successfully.',
            position: 'toast-top toast-end',
            icon: 'o-check-circle',
            timeout: 3000
        );

        // Reset form and redirect
        $this->form->reset();
        $this->redirect(route('admin.rule-and-regulation.index'), navigate: true);
    }

    public function render()
    {
        $headers = RuleHeader::all();

        return view('livewire.pages.admin.create-rule-and-regulation', [
            'headers' => $headers
        ]);
    }
}
