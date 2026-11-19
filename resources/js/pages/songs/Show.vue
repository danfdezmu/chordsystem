<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Minus, Music, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { transposeChord } from '@/lib/chords';
import { findSection, renderChart, renderSection, sectionColorClass } from '@/lib/chart';
import { parseStructure, sectionAbbr } from '@/lib/structure';
import PlaylistSongNav from '@/components/PlaylistSongNav.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as songs from '@/routes/songs';

defineOptions({
    layout: (props: any) => ({
        breadcrumbs: [
            { title: 'Canciones', href: songs.index() },
            { title: props.song.title, href: songs.show(props.song.id) },
        ],
    }),
});

const props = defineProps<{
    song: any;
    playlistNav?: { id: number; name: string; songs: Array<{ id: number; title: string; artist?: string | null; key?: string | null }> } | null;
}>();

function playlistSongHref(songId: number): string {
    return songs.show.url(songId, { query: { playlist: props.playlistNav?.id } });
}

const semitones = ref(0);
const lyricsOnly = ref(false);
const currentKey = computed(() => transposeChord(props.song.original_key, semitones.value));
const rendered = computed(() => {
    const structure = props.song.structure?.trim();
    if (!structure) return renderChart(props.song.content, semitones.value, lyricsOnly.value);
    const sections: { label: string; body: string }[] = [];
    let cur: { label: string; body: string } | null = null;
    for (const raw of (props.song.content || '').split('\n')) {
        const m = raw.trim().match(/^\[(.+)\]$/);
        if (m) {
            if (cur) sections.push(cur);
            cur = { label: m[1], body: '' };
        } else {
            if (!cur) cur = { label: 'Sección', body: '' };
            cur.body += (cur.body ? '\n' : '') + raw;
        }
    }
    if (cur) sections.push(cur);
    const tokens = parseStructure(structure);
    const blocks: string[] = [];
    tokens.forEach((token, index) => {
        const sec = findSection(sections, token);
        if (sec) {
            const label = token.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            blocks.push(`<div id="section-${index}" class="mt-4 mb-1 scroll-mt-20"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-slate-500/15 text-slate-700 dark:text-slate-300">${label}</span></div>` + renderSection(sec.body, semitones.value, lyricsOnly.value));
        }
    });
    return blocks.join('') || renderChart(props.song.content, semitones.value, lyricsOnly.value);
});

const chips = computed(() => {
    const parsedLabels: string[] = [];
    let cur: string | null = null;
    for (const raw of (props.song.content || '').split('\n')) {
        const m = raw.trim().match(/^\[(.+)\]$/);
        if (m) {
            if (cur) parsedLabels.push(cur);
            cur = m[1];
        }
    }
    if (cur) parsedLabels.push(cur);
    const source = props.song.structure?.trim()
        ? parseStructure(props.song.structure)
        : parsedLabels;
    return source.map((token: string, index: number) => {
        return { label: token, abbr: sectionAbbr(token), index };
    });
});

function scrollToSection(index: number) {
    const el = document.getElementById(`section-${index}`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="song.title" />

    <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-start">
        <div class="flex min-w-0 max-w-5xl flex-1 flex-col gap-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ song.title }}</h1>
                <p v-if="song.artist" class="text-muted-foreground">{{ song.artist }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="songs.edit(song.id)">
                    <Button variant="outline" size="sm">Editar</Button>
                </Link>
            </div>
        </div>

        <div v-if="chips.length" class="flex flex-wrap gap-2">
            <button
                v-for="chip in chips"
                :key="chip.index"
                type="button"
                :class="['flex size-9 items-center justify-center rounded-full text-sm font-bold hover:opacity-80', sectionColorClass(chip.label)]"
                :title="chip.label"
                @click="scrollToSection(chip.index)"
            >
                {{ chip.abbr }}
            </button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2 text-sm font-normal">
                        <span class="font-semibold">Tonalidad: {{ currentKey }}</span>
                        <Badge variant="secondary" v-if="song.bpm">BPM {{ song.bpm }}</Badge>
                        <Badge variant="secondary" v-if="song.time_signature">{{ song.time_signature }}</Badge>
                        <span class="text-muted-foreground">Original: {{ song.original_key }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex rounded-md border p-0.5">
                            <button
                                type="button"
                                class="rounded-sm px-3 py-1 text-xs font-medium"
                                :class="lyricsOnly ? 'text-muted-foreground hover:bg-muted' : 'bg-primary text-primary-foreground'"
                                @click="lyricsOnly = false"
                            >
                                Con acordes
                            </button>
                            <button
                                type="button"
                                class="rounded-sm px-3 py-1 text-xs font-medium"
                                :class="lyricsOnly ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                @click="lyricsOnly = true"
                            >
                                Solo letra
                            </button>
                        </div>
                        <Button size="icon-sm" variant="outline" @click="semitones--">
                            <Minus class="size-4" />
                        </Button>
                        <span class="min-w-12 text-center font-mono">
                            {{ semitones > 0 ? '+' : '' }}{{ semitones }}
                        </span>
                        <Button size="icon-sm" variant="outline" @click="semitones++">
                            <Plus class="size-4" />
                        </Button>
                        <Button size="sm" variant="ghost" @click="semitones = 0">Reset</Button>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    class="text-sm leading-relaxed"
                    :class="lyricsOnly ? 'font-sans text-base leading-7' : 'font-mono'"
                    v-html="rendered"
                ></div>
            </CardContent>
        </Card>
        </div>

        <PlaylistSongNav
            v-if="playlistNav?.songs?.length"
            :songs="playlistNav.songs"
            :active-id="song.id"
            :href-for="playlistSongHref"
        />
    </div>
</template>