<?php

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;

test('adding a song keeps the song key and order', function () {
    $user = User::factory()->create();
    $song = Song::factory()->for($user)->create([
        'original_key' => 'C',
        'structure' => 'Coro Verso',
    ]);
    $playlist = Playlist::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('playlists.songs.add', $playlist), [
            'song_id' => $song->id,
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    $attached = $playlist->songs()->first();

    expect($attached->pivot->key_override)->toBeNull()
        ->and($attached->pivot->structure)->toBeNull()
        ->and($song->fresh())
        ->original_key->toBe('C')
        ->structure->toBe('Coro Verso');
});

test('a playlist stores its own key and order without changing the song', function () {
    $user = User::factory()->create();
    $song = Song::factory()->for($user)->create([
        'original_key' => 'C',
        'structure' => 'Coro Verso',
    ]);
    $playlist = Playlist::factory()->for($user)->create();
    $playlist->songs()->attach($song->id, ['position' => 1]);

    $this->actingAs($user)
        ->patch(route('playlists.songs.update', [$playlist, $song]), [
            'key_override' => 'G',
            'structure' => 'Verso Coro Coro',
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    $attached = $playlist->songs()->first();

    expect($attached->pivot->key_override)->toBe('G')
        ->and($attached->pivot->structure)->toBe('Verso Coro Coro')
        ->and($song->fresh())
        ->original_key->toBe('C')
        ->structure->toBe('Coro Verso');
});

test('saving the song key and order clears the playlist override', function () {
    $user = User::factory()->create();
    $song = Song::factory()->for($user)->create([
        'original_key' => 'C',
        'structure' => 'Coro Verso',
    ]);
    $playlist = Playlist::factory()->for($user)->create();
    $playlist->songs()->attach($song->id, [
        'position' => 1,
        'key_override' => 'G',
        'structure' => 'Verso Coro',
    ]);

    $this->actingAs($user)
        ->patch(route('playlists.songs.update', [$playlist, $song]), [
            'key_override' => 'C',
            'structure' => 'Coro Verso',
        ])
        ->assertRedirect(route('playlists.show', $playlist));

    $attached = $playlist->songs()->first();

    expect($attached->pivot->key_override)->toBeNull()
        ->and($attached->pivot->structure)->toBeNull();
});

test('guests are redirected when adding a song to a playlist', function () {
    $playlist = Playlist::factory()->create();
    $song = Song::factory()->create();

    $this->post(route('playlists.songs.add', $playlist), [
        'song_id' => $song->id,
    ])->assertRedirect(route('login'));
});

test('another user cannot change playlist song settings', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $song = Song::factory()->for($owner)->create();
    $playlist = Playlist::factory()->for($owner)->create();
    $playlist->songs()->attach($song->id, ['position' => 1]);

    $this->actingAs($other)
        ->patch(route('playlists.songs.update', [$playlist, $song]), [
            'key_override' => 'D',
            'structure' => 'Coro',
        ])
        ->assertForbidden();
});
