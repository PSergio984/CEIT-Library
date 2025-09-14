<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-based-content leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-based-content ">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
