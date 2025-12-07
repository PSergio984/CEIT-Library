<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6" x-data="{
    password: '',
    touched: false,
    error: '',
    validatePassword() {
        this.touched = true;
        if (!this.password) {
            this.error = 'Password is required to delete your account.';
        } else {
            this.error = '';
        }
    },
    get isFormValid() {
        return this.password && !this.error;
    }
}">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-base-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-base-content">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-base-600">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                    x-on:input="password = $event.target.value"
                    x-on:blur="validatePassword()"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                <template x-if="touched && error && !$wire.__instance.snapshot.memo.errors?.password">
                    <p class="text-red-500 text-xs mt-1" x-text="error"></p>
                </template>
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3"
                    x-bind:disabled="!isFormValid"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
