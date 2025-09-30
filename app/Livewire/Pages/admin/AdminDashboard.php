<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Component;

class AdminDashboard extends Component
{
    public function render()
    {
        return view('livewire.pages.admin.admin-dashboard');
    }

    public function __invoke()
    {
        return $this->render();
    }
}
