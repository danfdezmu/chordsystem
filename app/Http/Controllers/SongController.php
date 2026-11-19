<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Requests\UpdateSongRequest;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\PdfTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    public function index(Request $request): Response
    {
        $songs = Song::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhere('is_public', true);
            })
            ->latest()
            ->get();

        return Inertia::render('songs/Index', [
            'songs' => $songs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('songs/Create');
    }

    public function store(StoreSongRequest $request): RedirectResponse
    {
        $song = Song::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return to_route('songs.show', $song);
    }

    public function show(Request $request, Song $song): Response
    {
        $this->authorizeView($song, $request);

        return Inertia::render('songs/Show', [
            'song' => $song,
            'playlistNav' => $this->playlistNav($request, $song),
        ]);
    }

    public function edit(Song $song): Response
    {
        $this->authorizeOwner($song);

        return Inertia::render('songs/Edit', [
            'song' => $song,
        ]);
    }

    public function update(UpdateSongRequest $request, Song $song): RedirectResponse
    {
        $this->authorizeOwner($song);

        $song->update($request->validated());

        return to_route('songs.show', $song);
    }

    public function destroy(Song $song): RedirectResponse
    {
        $this->authorizeOwner($song);

        $song->delete();

        return to_route('songs.index');
    }

    public function import(Request $request, PdfTextExtractor $extractor): RedirectResponse
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('pdf');
        $path = $file->store('pdfs', 'local');

        $fullPath = Storage::disk('local')->path($path);
        $text = $extractor->extract($fullPath);

        $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $song = Song::create([
            'user_id' => $request->user()->id,
            'title' => $title,
            'original_key' => 'C',
            'content' => $text ?: 'No se pudo extraer texto del PDF.',
            'source_pdf_path' => $path,
        ]);

        return to_route('songs.edit', $song);
    }

    protected function authorizeOwner(Song $song): void
    {
        abort_unless($song->user_id === auth()->id(), 403);
    }

    protected function authorizeView(Song $song, Request $request): void
    {
        abort_unless($song->is_public || $song->user_id === $request->user()?->id, 403);
    }

    /**
     * @return array{id: int, name: string, songs: array<int, array{id: int, title: string, artist: string|null, key: string}>}|null
     */
    protected function playlistNav(Request $request, Song $song): ?array
    {
        $playlistId = $request->integer('playlist');
        if ($playlistId === 0) {
            return null;
        }

        $playlist = Playlist::query()->find($playlistId);
        if ($playlist === null) {
            return null;
        }

        $canView = $playlist->is_public || $playlist->user_id === $request->user()?->id;
        if (! $canView) {
            return null;
        }

        $playlist->load(['songs' => function ($query) {
            $query->select('songs.id', 'songs.title', 'songs.artist', 'songs.original_key');
        }]);

        if (! $playlist->songs->contains(fn (Song $item): bool => $item->id === $song->id)) {
            return null;
        }

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'songs' => $playlist->songs->map(fn (Song $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'artist' => $item->artist,
                'key' => $item->pivot->key_override ?: $item->original_key,
            ])->values()->all(),
        ];
    }
}
