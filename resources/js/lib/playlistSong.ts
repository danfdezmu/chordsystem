import { findSection, parseSections, renderChart, renderSection, sectionColorClass } from '@/lib/chart';
import { parseStructure } from '@/lib/structure';
import { semitonesBetween, transposeChord } from '@/lib/chords';

type PlaylistSong = {
    content?: string | null;
    original_key: string;
    structure?: string | null;
    pivot?: {
        key_override?: string | null;
        structure?: string | null;
    } | null;
};

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

export function playlistKey(song: PlaylistSong, extraSemitones = 0): string {
    const base = song.pivot?.key_override?.trim() || song.original_key;
    return transposeChord(base, extraSemitones);
}

export function playlistSemitones(song: PlaylistSong, extraSemitones = 0): number {
    const target = song.pivot?.key_override?.trim() || song.original_key;
    return semitonesBetween(song.original_key, target) + extraSemitones;
}

export function playlistStructure(song: PlaylistSong): string {
    const override = song.pivot?.structure?.trim();
    if (override) {
        return override;
    }
    return (song.structure ?? '').trim();
}

export function observePlaylistSongs(onChange: (id: number) => void): () => void {
    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (!visible) {
            return;
        }
        const id = Number((visible.target as HTMLElement).dataset.songId);
        if (id) {
            onChange(id);
        }
    }, { rootMargin: '-15% 0px -55% 0px', threshold: 0.15 });

    document.querySelectorAll<HTMLElement>('[data-song-id]').forEach((element) => {
        observer.observe(element);
    });

    return () => observer.disconnect();
}

export function renderPlaylistSong(song: PlaylistSong, extraSemitones = 0): string {
    const steps = playlistSemitones(song, extraSemitones);
    const content = song.content ?? '';
    const structure = playlistStructure(song);
    if (!structure) {
        return renderChart(content, steps);
    }

    const sections = parseSections(content);
    const tokens = parseStructure(structure);
    const blocks = tokens.map((token) => {
        const section = findSection(sections, token);
        const label = escapeHtml(token);
        if (!section) {
            return `<div class="mt-3 mb-1"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-300">${label} (no encontrado)</span></div>`;
        }
        return `<div class="mt-3 mb-1"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold ${sectionColorClass(token)}">${label}</span></div>${renderSection(section.body, steps)}`;
    });

    return blocks.join('') || renderChart(content, steps);
}
