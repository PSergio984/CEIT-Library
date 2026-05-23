<?php

namespace App\Livewire\Pages\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class AdminComponent extends Component
{
    /**
     * Authorize the user's access to admin pages.
     *
     * @throws AuthorizationException
     */
    public function authorizeAccess(): void
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            throw new AuthorizationException;
        }
    }
}
