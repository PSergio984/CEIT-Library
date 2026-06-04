<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('admin.assign-librarians.create-batch')
        ->assertStatus(200);
});
