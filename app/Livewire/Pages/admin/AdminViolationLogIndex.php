<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Violation Management')]
class AdminViolationLogIndex extends AdminComponent
{
    public string $selectedTab = 'violations-tab';

    public function render()
    {
        return view('livewire.pages.admin.admin-violation-log-index');
    }
}
