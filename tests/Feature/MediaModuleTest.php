<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared Media library: page rendering, ownership stamping, and the
 * club-scoped visibility rule (a club user only ever sees / picks media
 * stamped with their own club; a non-club media admin sees everything).
 */
class MediaModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('master_admin');

        return $user;
    }

    private function clubUser(Club $club): User
    {
        $user = User::factory()->create(['club_id' => $club->id]);
        $user->assignRole('master_club');

        return $user;
    }

    private function makeClub(string $name): Club
    {
        return Club::create([
            'name'   => $name,
            'code'   => strtoupper($name),
            'email'  => strtolower($name) . '@example.test',
            'status' => 'active',
        ]);
    }

    public function test_media_pages_render_for_an_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/media')->assertOk();
        $this->get('/admin/media/create')->assertOk();
    }

    public function test_club_user_only_sees_their_own_club_media(): void
    {
        $alpha = $this->makeClub('Alpha');
        $bravo = $this->makeClub('Bravo');

        $own    = Media::create(['name' => 'Alpha crest', 'file' => 'media/alpha.png', 'club_id' => $alpha->id]);
        $other  = Media::create(['name' => 'Bravo crest', 'file' => 'media/bravo.png', 'club_id' => $bravo->id]);
        $global = Media::create(['name' => 'Macron logo', 'file' => 'media/macron.png']);

        $clubUser = $this->clubUser($alpha);

        $visible = Media::query()->visibleTo($clubUser)->pluck('id');

        $this->assertTrue($visible->contains($own->id));
        $this->assertFalse($visible->contains($other->id));
        $this->assertFalse($visible->contains($global->id));
    }

    public function test_media_resource_is_available_in_both_panels(): void
    {
        $adminResources = \Filament\Facades\Filament::getPanel('admin')->getResources();
        $clubResources = \Filament\Facades\Filament::getPanel('clubs')->getResources();

        $this->assertContains(\App\Filament\Resources\MediaResource::class, $adminResources);
        $this->assertContains(\App\Filament\Resources\MediaResource::class, $clubResources);
    }

    public function test_media_admin_sees_every_club_and_global_entry(): void
    {
        $alpha = $this->makeClub('Alpha');
        Media::create(['name' => 'scoped', 'file' => 'media/x.png', 'club_id' => $alpha->id]);
        Media::create(['name' => 'global', 'file' => 'media/y.png']);

        $this->assertSame(2, Media::query()->visibleTo($this->admin())->count());
    }

    public function test_club_upload_is_stamped_with_the_uploaders_club(): void
    {
        $alpha = $this->makeClub('Alpha');
        $clubUser = $this->clubUser($alpha);
        $this->actingAs($clubUser);

        $media = Media::create(['name' => 'Team photo', 'file' => 'media/team.png']);

        $this->assertSame($alpha->id, $media->fresh()->club_id);
        $this->assertSame($clubUser->id, $media->fresh()->created_by);
    }

    public function test_admin_upload_stays_global(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $media = Media::create(['name' => 'House style', 'file' => 'media/house.png']);

        $this->assertNull($media->fresh()->club_id);
        $this->assertSame($admin->id, $media->fresh()->created_by);
    }

    public function test_name_falls_back_to_the_file_base_name(): void
    {
        $media = Media::create(['name' => '', 'file' => 'media/some-badge.png']);

        $this->assertSame('some-badge', $media->fresh()->name);
    }

    public function test_club_role_has_media_crud_permissions(): void
    {
        $clubUser = $this->clubUser($this->makeClub('Alpha'));

        $this->assertTrue($clubUser->can('view_media'));
        $this->assertTrue($clubUser->can('create_media'));
        $this->assertTrue($clubUser->can('edit_media'));
        $this->assertTrue($clubUser->can('delete_media'));
    }

    public function test_user_without_view_media_cannot_reach_the_module(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        $this->actingAs($user);

        $this->assertFalse(\App\Filament\Resources\MediaResource::canAccess());
        $this->assertCount(0, Media::query()->visibleTo($user)->get());
    }

    public function test_sponsor_logo_form_with_the_media_picker_still_renders(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/sponsor-logos/create')->assertOk();
        $this->get('/admin/clubs/create')->assertOk();
        $this->get('/admin/logo-stocks/create')->assertOk();
    }
}
