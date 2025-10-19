<?php

namespace App\Livewire\Pages\Admin;


use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.Admin')]
class AdminComponent extends Component
{

    public function render()
    {
        return view('livewire.admin-dashboard');
    }
}


