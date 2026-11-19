<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SsoAcceptController;
use App\Http\Controllers\SsoLogoutController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('sso/accept', SsoAcceptController::class)->name('sso.accept');
Route::get('sso/logout', SsoLogoutController::class)->name('sso.logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('songs', SongController::class);
    Route::post('songs/import', [SongController::class, 'import'])->name('songs.import');

    Route::resource('playlists', PlaylistController::class);
    Route::post('playlists/{playlist}/songs', [PlaylistController::class, 'addSong'])->name('playlists.songs.add');
    Route::delete('playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');
    Route::patch('playlists/{playlist}/songs/{song}', [PlaylistController::class, 'updateSong'])->name('playlists.songs.update');
    Route::patch('playlists/{playlist}/toggle-public', [PlaylistController::class, 'togglePublic'])->name('playlists.toggle-public');
    Route::post('playlists/{playlist}/share', [PlaylistController::class, 'share'])->name('playlists.share');
    Route::delete('playlists/{playlist}/share', [PlaylistController::class, 'unshare'])->name('playlists.unshare');
    Route::get('playlists/{playlist}/editors/search', [PlaylistController::class, 'searchEditors'])->name('playlists.editors.search');
    Route::post('playlists/{playlist}/editors', [PlaylistController::class, 'grantEditor'])->name('playlists.editors.store');
    Route::delete('playlists/{playlist}/editors/{user}', [PlaylistController::class, 'revokeEditor'])->name('playlists.editors.destroy');
    Route::get('playlists/{playlist}/present', [PlaylistController::class, 'present'])->name('playlists.present');
});

Route::get('shared/playlists/{token}', [PlaylistController::class, 'shared'])->name('playlists.shared');

require __DIR__.'/settings.php';
