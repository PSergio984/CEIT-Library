<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;

#[Title('Violation Management')]
#[Lazy]
class AdminViolationLogIndex extends AdminComponent
{
    public string $selectedTab = 'violations-tab';

    public function placeholder()
    {
        return view('components.loading-placeholder');
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-violation-log-index');
    }
}
