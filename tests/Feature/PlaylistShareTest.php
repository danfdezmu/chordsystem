<?php

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner can share a view link', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('playlists.share', $playlist))
        ->assertRedirect(route('playlists.show', $playlist));

    $playlist->refresh();

    expect($playlist->share_token)->not->toBeEmpty();

    $this->get(route('playlists.shared', $playlist->share_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('playlists/Shared'));
});

test('stopping the share removes the view link', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create();
    $playlist->generateShareToken();
    $token = $playlist->share_token;

    $this->actingAs($owner)
        ->delete(route('playlists.unshare', $playlist))
        ->assertRedirect(route('playlists.show', $playlist));

    $this->get(route('playlists.shared', $token))->assertNotFound();
});

test('a viewer cannot change the playlist key or order', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $song = Song::factory()->for($owner)->create([
        'is_public' => true,
        'original_key' => 'C',
        'structure' => 'Coro',
    ]);
    $playlist = Playlist::factory()->for($owner)->create(['is_public' => true]);
    $playlist->songs()->attach($song->id, ['position' => 1]);

    $this->actingAs($viewer)
        ->get(route('playlists.show', $playlist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('isOwner', false)
        );

    $this->actingAs($viewer)
        ->patch(route('playlists.songs.update', [$playlist, $song]), [
            'key_override' => 'D',
            'structure' => 'Verso',
        ])
        ->assertForbidden();

    expect($playlist->songs()->first()->pivot->key_override)->toBeNull()
        ->and($song->fresh()->original_key)->toBe('C');
});

test('an editor granted by the owner can change the key and order', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $song = Song::factory()->for($owner)->create([
        'original_key' => 'C',
        'structure' => 'Coro',
    ]);
    $playlist = Playlist::factory()->for($owner)->create();
    $playlist->songs()->attach($song->id, ['position' => 1]);

    $this->actingAs($owner)
        ->post(route('playlists.editors.store', $playlist), [
            'user_id' => $editor->id,
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    $this->actingAs($editor)
        ->patch(route('playlists.songs.update', [$playlist, $song]), [
            'key_override' => 'G',
            'structure' => 'Verso Coro',
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    $attached = $playlist->songs()->first();

    expect($attached->pivot->key_override)->toBe('G')
        ->and($attached->pivot->structure)->toBe('Verso Coro')
        ->and($song->fresh())
        ->original_key->toBe('C')
        ->structure->toBe('Coro');

    $this->actingAs($editor)
        ->get(route('playlists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('playlists.0.id', $playlist->id));
});

test('an editor cannot grant edit access to someone else', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $other = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create();
    $playlist->editors()->attach($editor->id);

    $this->actingAs($editor)
        ->post(route('playlists.editors.store', $playlist), [
            'user_id' => $other->id,
        ])
        ->assertForbidden();

    expect($playlist->editors()->whereKey($other->id)->exists())->toBeFalse();
});

test('granting an unknown user explains that the user was not found', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('playlists.editors.store', $playlist), [
            'user_id' => 999999,
        ])
        ->assertSessionHasErrors([
            'user_id' => 'No encontramos a ese usuario.',
        ]);
});

test('the owner can search editors by name or email after three letters', function () {
    $owner = User::factory()->create(['name' => 'Dueño', 'email' => 'owner@chordsystem.test']);
    $byName = User::factory()->create(['name' => 'Daniel Ruiz', 'email' => 'ruiz@chordsystem.test']);
    $byEmail = User::factory()->create(['name' => 'Ana', 'email' => 'correo.daniel@chordsystem.test']);
    User::factory()->create(['name' => 'Otro', 'email' => 'otro@chordsystem.test']);
    $playlist = Playlist::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->getJson(route('playlists.editors.search', ['playlist' => $playlist, 'q' => 'da']))
        ->assertOk()
        ->assertJsonPath('users', []);

    $this->actingAs($owner)
        ->getJson(route('playlists.editors.search', ['playlist' => $playlist, 'q' => 'dan']))
        ->assertOk()
        ->assertJsonFragment(['id' => $byName->id, 'email' => $byName->email])
        ->assertJsonFragment(['id' => $byEmail->id, 'email' => $byEmail->email])
        ->assertJsonMissing(['email' => 'otro@chordsystem.test'])
        ->assertJsonMissing(['email' => $owner->email]);
});
