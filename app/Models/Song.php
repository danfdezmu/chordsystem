<?php

namespace App\Models;

use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $artist
 * @property string $original_key
 * @property string $content
 * @property string|null $source_pdf_path
 * @property bool $is_public
 */
#[Fillable(['user_id', 'title', 'artist', 'original_key', 'bpm', 'time_signature', 'capo', 'content', 'structure', 'source_pdf_path', 'is_public'])]
class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'bpm' => 'integer',
            'capo' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_song')
            ->withPivot(['position', 'key_override', 'structure'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
