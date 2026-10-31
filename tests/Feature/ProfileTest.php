<?php

namespace Tests\Feature;

use App\Models\BagDeposit;
use App\Models\BagSlot;
use App\Models\User;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_profile_page_is_displayed(): void
    {
        $password = fake()->password(8, 12);
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();

        $response->assertSee('Profile Information');
        $response->assertSee('Update Password');

        $response->assertSee('first_name');
        $response->assertSee('last_name');
        $response->assertSee('email');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $password = fake()->password(8, 12);
        $user = User::factory()->create([
            'password' => bcrypt($password),
            'first_name' => 'Original',
            'last_name' => 'Name',
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
    }

    public function test_user_can_delete_their_account(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', $password)
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_user_with_open_bag_deposit_cannot_delete_their_account(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);
        $slot = BagSlot::create(['number' => 1, 'is_active' => true]);
        BagDeposit::create([
            'user_id' => $user->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 1,
            'claim_code' => '123456',
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->actingAs($user);

        Volt::test('profile.delete-user-form')
            ->set('password', $password)
            ->call('deleteUser')
            ->assertNoRedirect();

        // Self-deletion refused — the open deposit must be claimed first.
        $this->assertNotNull($user->fresh());
        $this->assertSame(BagDeposit::STATUS_OPEN, BagDeposit::sole()->status);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }
}
