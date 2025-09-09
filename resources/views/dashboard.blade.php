<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                    <div class="qrcode mb-4">
                        <img src="{{ $qrImageUrl }}" alt="QR Code" width="300" height="300">
                    </div>
                    <a href="{{ $qrImageUrl }}" download="qrcode.png" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Download QR Code</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
