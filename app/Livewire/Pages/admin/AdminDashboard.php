<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Attributes\Title;

#[Title('Admin Dashboard')]
class AdminDashboard extends AdminComponent
{
    public function render()
    {
        return view('livewire.pages.admin.admin-dashboard');
    }
}
