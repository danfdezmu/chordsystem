<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $songs = Song::query()
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('is_public', true);
            });

        $artists = (clone $songs)
            ->whereNotNull('artist')
            ->where('artist', '!=', '')
            ->distinct()
            ->orderBy('artist')
            ->pluck('artist')
            ->values();

        return Inertia::render('Dashboard', [
            'songCount' => $songs->count(),
            'artists' => $artists,
        ]);
    }
}
