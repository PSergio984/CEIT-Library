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
    isEditing: false,
    fields: {
        first_name: '{{ $first_name }}',
        last_name: '{{ $last_name }}'
    },
    originalFields: {
        first_name: '{{ $first_name }}',
        last_name: '{{ $last_name }}'
    },
    touched: {
        first_name: false,
        last_name: false
    },
    errors: {
        first_name: '',
        last_name: ''
    },
    get isDirty() {
        return this.fields.first_name !== this.originalFields.first_name || 
               this.fields.last_name !== this.originalFields.last_name;
    },
    formatName(val) {
        if (!val) return '';
        val = val.trim().replace(/\s+/g, ' ');
        return val.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
    },
    validateField(field) {
        this.touched[field] = true;
        const value = this.fields[field] || '';
        
        if (!value.trim()) {
            this.errors[field] = 'This field is required.';
        } else if (value.length < 2) {
            this.errors[field] = 'Must be at least 2 characters.';
        } else if (value.length > 50) {
            this.errors[field] = 'Must not exceed 50 characters.';
        } else if (!/^[\p{L}\s\-']+$/u.test(value)) {
            this.errors[field] = 'Only letters, spaces, hyphens, and apostrophes allowed.';
        } else {
            this.errors[field] = '';
        }
    },
    cancelEdit() {
        this.isEditing = false;
        this.fields.first_name = this.originalFields.first_name;
        this.fields.last_name = this.originalFields.last_name;
        $wire.first_name = this.originalFields.first_name;
        $wire.last_name = this.originalFields.last_name;
        this.touched = { first_name: false, last_name: false };
        this.errors = { first_name: '', last_name: '' };
    },
    get isFormValid() {
        return this.isDirty && this.fields.first_name && this.fields.last_name && 
               !this.errors.first_name && !this.errors.last_name &&
               this.fields.first_name.length >= 2 && this.fields.last_name.length >= 2;
    }
}"
x-on:profile-updated.window="
    isEditing = false;
    originalFields.first_name = fields.first_name;
    originalFields.last_name = fields.last_name;
    touched = { first_name: false, last_name: false };
    errors = { first_name: '', last_name: '' };
">
    <header class="flex justify-between items-start">
        <div>
            <h2 class="text-lg text-base-content font-bold">
                {{ __('Profile Information') }}
            </h2>

            <p class="mt-1 text-sm text-base-content">
                {{ __("Update your name. Email cannot be changed.") }}
            </p>
        </div>
        
        <button 
            type="button"
            x-show="!isEditing"
            x-on:click="isEditing = true"
            class="btn btn-sm btn-outline btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
            </svg>
            Edit
        </button>
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
                x-bind:disabled="!isEditing"
                x-bind:class="{ 'opacity-60 cursor-not-allowed bg-base-200': !isEditing }"
                x-on:input="fields.first_name = $event.target.value"
                x-on:blur="
                    if (!isEditing) return;
                    const formatted = formatName($event.target.value);
                    $event.target.value = formatted;
                    $wire.first_name = formatted;
                    fields.first_name = formatted;
                    validateField('first_name');
                " 
            />
            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            <div x-show="isEditing && touched.first_name && errors.first_name" x-cloak class="text-red-500 text-xs mt-1" x-text="errors.first_name"></div>
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
                x-bind:disabled="!isEditing"
                x-bind:class="{ 'opacity-60 cursor-not-allowed bg-base-200': !isEditing }"
                x-on:input="fields.last_name = $event.target.value"
                x-on:blur="
                    if (!isEditing) return;
                    const formatted = formatName($event.target.value);
                    $event.target.value = formatted;
                    $wire.last_name = formatted;
                    fields.last_name = formatted;
                    validateField('last_name');
                " 
            />
            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
            <div x-show="isEditing && touched.last_name && errors.last_name" x-cloak class="text-red-500 text-xs mt-1" x-text="errors.last_name"></div>
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

        <div x-show="isEditing" x-cloak class="flex items-center gap-4">
            <div x-show="!isDirty && !errors.first_name && !errors.last_name" x-cloak class="text-base-content/50 text-sm">
                Make changes to enable save
            </div>
            
            <button 
                type="button"
                x-on:click="cancelEdit()"
                class="btn btn-ghost">
                {{ __('Cancel') }}
            </button>
            
            <x-primary-button 
                x-bind:disabled="!isFormValid"
                x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                {{ __('Save Changes') }}
            </x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
