<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Academic Paper Directory') }}
        </h2>
    </x-slot>

    <mary-x-accordion wire:model="group">
        <x-mary-accordion name="group1">
            <x-slot:heading>Group 1</x-slot:heading>
            <x-slot:content>Hello 1</x-slot:content>
        </x-mary-accordion>
        <mary-x-collapse name="group2">
            <x-slot:heading>Group 2</x-slot:heading>
            <x-slot:content>Hello 2</x-slot:content>
        </mary-x-collapse>
        <mary-x-collapse name="group3">
            <x-slot:heading>Group 3</x-slot:heading>
            <x-slot:content>Hello 3</x-slot:content>
        </mary-x-collapse>
    </mary-x-accordion>
</div>
