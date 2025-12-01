<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\']+$/u', // Letters, spaces, hyphens, apostrophes, Unicode support
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s\-\']+$/u', // Letters, spaces, hyphens, apostrophes, Unicode support
            ],
        ]);

        $user->update($validated);

        $this->dispatch('profile-updated');
    }
}; ?>

<section x-data="{
    formatName(val) {
        if (!val) return '';
        val = val.trim().replace(/\s+/g, ' ');
        return val.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
    }
}">
    <header>
        <h2 class="text-lg text-base-content font-bold">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-base-content">
            {{ __("Update your name. Email cannot be changed.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="first_name" :value="__('First Name')" />
            <x-text-input 
                wire:model="first_name" 
                id="first_name" 
                name="first_name" 
                type="text" 
                class="mt-1 block w-full" 
                required 
                autofocus 
                autocomplete="given-name"
                x-on:blur="
                    const formatted = formatName($event.target.value);
                    $event.target.value = formatted;
                    $wire.first_name = formatted;
                " 
            />
            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
        </div>

        <div>
            <x-input-label for="last_name" :value="__('Last Name')" />
            <x-text-input 
                wire:model="last_name" 
                id="last_name" 
                name="last_name" 
                type="text" 
                class="mt-1 block w-full" 
                required 
                autocomplete="family-name"
                x-on:blur="
                    const formatted = formatName($event.target.value);
                    $event.target.value = formatted;
                    $wire.last_name = formatted;
                " 
            />
            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email Address')" />
            <x-text-input 
                wire:model="email" 
                id="email" 
                name="email" 
                type="email" 
                class="mt-1 block w-full opacity-60 cursor-not-allowed" 
                readonly 
                disabled 
                autocomplete="username" 
            />
            <p class="text-xs text-base-content/60 mt-1">Email address cannot be changed for security reasons.</p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
