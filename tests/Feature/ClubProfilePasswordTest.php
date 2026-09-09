<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Profile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Club panel profile page — a club user can rename themselves and change
 * their password, but only by supplying the correct current password.
 */
class ClubProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function clubUser(): User
    {
        $user = User::factory()->create([
            'club_id' => null,
            'password' => Hash::make('old-password'),
        ]);
        $user->assignRole('master_club');

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('clubs'));
    }

    public function test_password_changes_with_the_correct_current_password(): void
    {
        $user = $this->clubUser();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->fillForm([
                'current_password' => 'old-password',
                'password' => 'brand-new-password',
                'passwordConfirmation' => 'brand-new-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_password_change_is_rejected_with_a_wrong_current_password(): void
    {
        $user = $this->clubUser();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->fillForm([
                'current_password' => 'not-my-password',
                'password' => 'brand-new-password',
                'passwordConfirmation' => 'brand-new-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_name_can_be_saved_without_touching_the_password(): void
    {
        $user = $this->clubUser();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->fillForm(['name' => 'New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue(Hash::check('old-password', $user->password));
    }
}
