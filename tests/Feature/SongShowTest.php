<?php

use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a song opened from a playlist lists that playlist on the side', function () {
    $user = User::factory()->create();
    $first = Song::factory()->for($user)->create(['title' => 'Primera']);
    $second = Song::factory()->for($user)->create(['title' => 'Segunda']);
    $playlist = Playlist::factory()->for($user)->create();
    $playlist->songs()->attach($first->id, ['position' => 1]);
    $playlist->songs()->attach($second->id, ['position' => 2]);

    $this->actingAs($user)
        ->get(route('songs.show', ['song' => $first, 'playlist' => $playlist->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('songs/Show')
            ->where('playlistNav.id', $playlist->id)
            ->where('playlistNav.songs.0.title', 'Primera')
            ->where('playlistNav.songs.1.title', 'Segunda')
        );
});

test('another users private playlist is left off the song page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $song = Song::factory()->for($owner)->create(['is_public' => true]);
    $playlist = Playlist::factory()->for($owner)->create(['is_public' => false]);
    $playlist->songs()->attach($song->id, ['position' => 1]);

    $this->actingAs($other)
        ->get(route('songs.show', ['song' => $song, 'playlist' => $playlist->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('playlistNav', null));
});
