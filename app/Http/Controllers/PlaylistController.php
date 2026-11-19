<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaylistRequest;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $playlists = Playlist::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhere('is_public', true)
                    ->orWhereHas('editors', function ($editors) use ($request) {
                        $editors->where('users.id', $request->user()->id);
                    });
            })
            ->latest()
            ->get();

        return Inertia::render('playlists/Index', [
            'playlists' => $playlists,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('playlists/Create');
    }

    public function store(StorePlaylistRequest $request): RedirectResponse
    {
        $playlist = Playlist::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return to_route('playlists.show', $playlist);
    }

    public function show(Request $request, Playlist $playlist): Response
    {
        $this->authorizeView($playlist, $request);

        $playlist->load([
            'songs',
            'editors' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email')->orderBy('name');
            },
        ]);

        $isOwner = $request->user()?->id === $playlist->user_id;
        $canEdit = $isOwner || $playlist->editors->contains('id', $request->user()?->id);

        $availableSongs = $canEdit
            ? Song::query()
                ->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id)
                        ->orWhere('is_public', true);
                })
                ->orderBy('title')
                ->get()
            : [];

        return Inertia::render('playlists/Show', [
            'playlist' => $playlist,
            'availableSongs' => $availableSongs,
            'canEdit' => $canEdit,
            'isOwner' => $isOwner,
            'editors' => $isOwner
                ? $playlist->editors->map(fn (User $editor): array => [
                    'id' => $editor->id,
                    'name' => $editor->name,
                    'email' => $editor->email,
                ])->values()
                : [],
        ]);
    }

    public function edit(Playlist $playlist): Response
    {
        $this->authorizeOwner($playlist);

        return Inertia::render('playlists/Edit', [
            'playlist' => $playlist,
        ]);
    }

    public function update(StorePlaylistRequest $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $playlist->update($request->validated());

        return to_route('playlists.show', $playlist);
    }

    public function destroy(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $playlist->delete();

        return to_route('playlists.index');
    }

    public function addSong(Request $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeEdit($playlist);

        $request->validate([
            'song_id' => ['required', 'exists:songs,id'],
        ]);

        $song = Song::findOrFail($request->song_id);

        abort_unless($song->is_public || $song->user_id === $request->user()->id, 403);

        $nextPosition = (int) $playlist->songs()->max('playlist_song.position') + 1;

        $playlist->songs()->syncWithoutDetaching([
            $song->id => [
                'position' => $nextPosition,
                'key_override' => null,
                'structure' => null,
            ],
        ]);

        return to_route('playlists.show', $playlist);
    }

    public function updateSong(Request $request, Playlist $playlist, Song $song): RedirectResponse
    {
        $this->authorizeEdit($playlist);

        $data = $request->validate([
            'key_override' => ['nullable', 'string', 'max:10'],
            'structure' => ['nullable', 'string'],
        ]);

        abort_unless($playlist->songs()->whereKey($song->id)->exists(), 404);

        $key = trim((string) ($data['key_override'] ?? ''));
        $structure = trim((string) ($data['structure'] ?? ''));
        $songStructure = trim((string) $song->structure);

        $playlist->songs()->updateExistingPivot($song->id, [
            'key_override' => ($key === '' || strcasecmp($key, $song->original_key) === 0) ? null : $key,
            'structure' => ($structure === '' || $structure === $songStructure) ? null : $structure,
        ]);

        return to_route('playlists.show', $playlist);
    }

    public function removeSong(Playlist $playlist, Song $song): RedirectResponse
    {
        $this->authorizeEdit($playlist);

        $playlist->songs()->detach($song->id);

        return to_route('playlists.show', $playlist);
    }

    public function togglePublic(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $playlist->is_public = ! $playlist->is_public;
        if ($playlist->is_public && ! $playlist->share_token) {
            $playlist->generateShareToken();
        }
        $playlist->save();

        return to_route('playlists.show', $playlist);
    }

    public function present(Request $request, Playlist $playlist): Response
    {
        $this->authorizeView($playlist, $request);

        $playlist->load('songs');

        return Inertia::render('playlists/Present', [
            'playlist' => $playlist,
        ]);
    }

    public function share(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        if (! $playlist->share_token) {
            $playlist->generateShareToken();
        }

        return to_route('playlists.show', $playlist);
    }

    public function unshare(Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $playlist->share_token = null;
        $playlist->save();

        return to_route('playlists.show', $playlist);
    }

    public function grantEditor(Request $request, Playlist $playlist): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $editor = User::query()->find($data['user_id']);

        if ($editor === null) {
            throw ValidationException::withMessages([
                'user_id' => 'No encontramos a ese usuario.',
            ]);
        }

        if ($editor->id === $playlist->user_id) {
            throw ValidationException::withMessages([
                'user_id' => 'El creador ya puede editar la playlist.',
            ]);
        }

        $playlist->editors()->syncWithoutDetaching([$editor->id]);

        return to_route('playlists.show', $playlist);
    }

    public function searchEditors(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizeOwner($playlist);

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 3) {
            return response()->json(['users' => []]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $editorIds = $playlist->editors()->pluck('users.id');

        $users = User::query()
            ->where('id', '!=', $playlist->user_id)
            ->when($editorIds->isNotEmpty(), function ($query) use ($editorIds) {
                $query->whereNotIn('id', $editorIds);
            })
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }

    public function revokeEditor(Playlist $playlist, User $user): RedirectResponse
    {
        $this->authorizeOwner($playlist);

        $playlist->editors()->detach($user->id);

        return to_route('playlists.show', $playlist);
    }

    public function shared(string $token): Response
    {
        $playlist = Playlist::where('share_token', $token)->firstOrFail();
        $playlist->load('songs');

        return Inertia::render('playlists/Shared', [
            'playlist' => $playlist,
        ]);
    }

    protected function authorizeOwner(Playlist $playlist): void
    {
        abort_unless($playlist->user_id === auth()->id(), 403);
    }

    protected function authorizeEdit(Playlist $playlist): void
    {
        $userId = auth()->id();

        abort_unless(
            $playlist->user_id === $userId || $playlist->editors()->whereKey($userId)->exists(),
            403,
        );
    }

    protected function authorizeView(Playlist $playlist, Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $playlist->is_public
                || $user?->id === $playlist->user_id
                || ($user && $playlist->editors()->whereKey($user->id)->exists()),
            403,
        );
    }
}
