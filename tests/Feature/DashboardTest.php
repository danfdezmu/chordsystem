<?php

use App\Models\Song;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the dashboard shows the visible song count and artists', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Song::factory()->for($user)->create(['artist' => 'Hillsong']);
    Song::factory()->for($user)->create(['artist' => 'Hillsong']);
    Song::factory()->for($user)->create(['artist' => 'Bethel']);
    Song::factory()->for($user)->create(['artist' => null]);
    Song::factory()->for($other)->create(['artist' => 'Privado', 'is_public' => false]);
    Song::factory()->for($other)->create(['artist' => 'Elevation', 'is_public' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('songCount', 5)
            ->where('artists', ['Bethel', 'Elevation', 'Hillsong'])
        );
});
