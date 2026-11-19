<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import PlaylistSongNav from '@/components/PlaylistSongNav.vue';
import { playlistKey, renderPlaylistSong } from '@/lib/playlistSong';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const props = defineProps<{ playlist: any }>();

const semitones = ref(0);
const lyricsOnly = ref(false);
const lyricScale = ref(1);

const chartFontSize = computed(() => {
    const base = lyricsOnly.value ? 1 : 0.875;

    return `${base * lyricScale.value}rem`;
});

function enlargeLyrics(): void {
    lyricScale.value = Math.min(1.75, Number((lyricScale.value + 0.25).toFixed(2)));
}

function shrinkLyrics(): void {
    lyricScale.value = Math.max(1, Number((lyricScale.value - 0.25).toFixed(2)));
}
const songs = computed(() => props.playlist.songs ?? []);
const activeId = ref<number | null>(songs.value[0]?.id ?? null);

const railSongs = computed(() =>
    songs.value.map((song) => ({
        id: song.id,
        title: song.title,
        artist: song.artist,
        key: playlistKey(song, semitones.value),
    })),
);

const activeSong = computed(() => {
    return songs.value.find((song) => song.id === activeId.value) ?? songs.value[0] ?? null;
});

function goToSong(id: number): void {
    activeId.value = id;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <Head :title="playlist.name" />

    <div class="flex min-h-svh flex-col gap-4 bg-background p-4 lg:flex-row lg:items-start">
        <div class="flex min-w-0 flex-1 flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                <p v-if="playlist.description" class="text-muted-foreground">{{ playlist.description }}</p>
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
                <Button size="sm" variant="outline" title="Achicar letra" :disabled="lyricScale <= 1" @click="shrinkLyrics">
                    A−
                </Button>
                <Button size="sm" variant="outline" title="Agrandar letra" :disabled="lyricScale >= 1.75" @click="enlargeLyrics">
                    A+
                </Button>
                <Button size="icon-sm" variant="outline" @click="semitones--">
                    <Minus class="size-4" />
                </Button>
                <span class="font-mono">{{ semitones > 0 ? '+' : '' }}{{ semitones }}</span>
                <Button size="icon-sm" variant="outline" @click="semitones++">
                    <Plus class="size-4" />
                </Button>
                <Button size="sm" variant="ghost" @click="semitones = 0">Reset</Button>
            </div>
        </div>

        <Card v-if="activeSong" :key="activeSong.id">
            <CardHeader>
                <CardTitle>{{ activeSong.title }}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Tonalidad: {{ playlistKey(activeSong, semitones) }}
                </p>
            </CardHeader>
            <CardContent>
                <div
                    class="overflow-x-auto pt-2 [font-variant-ligatures:none] [line-height:1.7]"
                    :class="lyricsOnly ? 'font-sans' : 'font-mono'"
                    :style="{ fontSize: chartFontSize }"
                    v-html="renderPlaylistSong(activeSong, semitones, lyricsOnly)"
                ></div>
            </CardContent>
        </Card>

        <p v-else class="text-muted-foreground">Sin canciones.</p>
        </div>

        <PlaylistSongNav
            v-if="railSongs.length"
            :songs="railSongs"
            :active-id="activeId"
            @select="goToSong"
        />
    </div>
</template>