<?php

use App\Models\User;
use App\Support\SharedAuth;

beforeEach(function () {
    config([
        'shared-auth.secret' => 'testing-secret',
        'shared-auth.role' => 'client',
        'shared-auth.peer' => 'http://ictamx.test',
    ]);
});

test('a church handoff signs the same person into chordsystem', function () {
    $existing = User::factory()->create([
        'name' => 'Nombre viejo',
        'email' => 'ana@iglesia.test',
    ]);

    $token = SharedAuth::token([
        'email' => 'Ana@iglesia.test',
        'name' => 'Ana López',
        'verified' => true,
    ]);

    $this->get(route('sso.accept', ['token' => $token]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($existing);
    expect($existing->fresh())
        ->name->toBe('Ana López')
        ->and(User::query()->count())->toBe(1);
});

test('a church handoff creates a local user when the email is new', function () {
    $token = SharedAuth::token([
        'email' => 'nuevo@iglesia.test',
        'name' => 'Nuevo',
        'verified' => true,
    ]);

    $this->get(route('sso.accept', ['token' => $token]))
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'nuevo@iglesia.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('a church session opens chord settings without asking for the local password', function () {
    $token = SharedAuth::token([
        'email' => 'ana@iglesia.test',
        'name' => 'Ana',
        'verified' => true,
    ]);

    $this->get(route('sso.accept', ['token' => $token]))->assertRedirect();

    $this->get(route('security.edit'))->assertOk();
});

test('a handoff token only works once', function () {
    $token = SharedAuth::token([
        'email' => 'ana@iglesia.test',
        'name' => 'Ana',
        'verified' => true,
    ]);

    $this->get(route('sso.accept', ['token' => $token]))->assertRedirect();
    $this->get(route('sso.accept', ['token' => $token]))->assertForbidden();
});

test('a token from another secret is rejected', function () {
    config(['shared-auth.secret' => 'other-secret']);
    $token = SharedAuth::token([
        'email' => 'ana@iglesia.test',
        'name' => 'Ana',
        'verified' => true,
    ]);
    config(['shared-auth.secret' => 'testing-secret']);

    $this->get(route('sso.accept', ['token' => $token]))->assertForbidden();

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('login and registration on chordsystem go to the church site', function () {
    $this->get('http://chordsystem.test/login')
        ->assertRedirect('http://ictamx.test/login?return=http%3A%2F%2Fchordsystem.test%2Fsso%2Faccept');

    $this->get('http://chordsystem.test/register')
        ->assertRedirect('http://ictamx.test/register?return=http%3A%2F%2Fchordsystem.test%2Fsso%2Faccept');
});

test('logging out of chordsystem also ends the church session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('http://chordsystem.test/logout');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('http://ictamx.test/sso/logout?')
        ->toContain('return=http%3A%2F%2Fchordsystem.test%2F');
    $this->assertGuest();
});

test('the church site can end the chordsystem session', function () {
    $user = User::factory()->create();
    $return = 'http://ictamx.test/';

    $this->actingAs($user)
        ->get('/sso/logout?'.SharedAuth::signedLogoutQuery($return))
        ->assertRedirect($return);

    $this->assertGuest();
});
