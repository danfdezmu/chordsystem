<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PlaylistSongNav from '@/components/PlaylistSongNav.vue';
import { observePlaylistSongs, playlistKey, renderPlaylistSong } from '@/lib/playlistSong';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import * as playlists from '@/routes/playlists';

const props = defineProps<{ playlist: any }>();

const semitones = ref(0);
const lyricsOnly = ref(false);
const activeId = ref<number | null>(props.playlist.songs?.[0]?.id ?? null);
let stopWatching = () => {};

const railSongs = computed(() =>
    (props.playlist.songs ?? []).map((song) => ({
        id: song.id,
        title: song.title,
        artist: song.artist,
        key: playlistKey(song, semitones.value),
    })),
);

onMounted(() => {
    stopWatching = observePlaylistSongs((id) => {
        activeId.value = id;
    });
});

onUnmounted(() => stopWatching());

function goToSong(id: number): void {
    activeId.value = id;
    document.getElementById(`playlist-song-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="`Presentación - ${playlist.name}`" />

    <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-start">
        <div class="flex min-w-0 flex-1 flex-col gap-4">
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 bg-background/95 py-2 backdrop-blur">
            <h1 class="text-xl font-bold">{{ playlist.name }}</h1>
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
                <Link :href="playlists.show(playlist.id)">
                    <Button variant="outline" size="sm">Salir</Button>
                </Link>
                <Button size="icon-sm" variant="outline" @click="semitones--">
                    <Minus class="size-4" />
                </Button>
                <span class="font-mono min-w-10 text-center">{{ semitones > 0 ? '+' : '' }}{{ semitones }}</span>
                <Button size="icon-sm" variant="outline" @click="semitones++">
                    <Plus class="size-4" />
                </Button>
                <Button size="sm" variant="ghost" @click="semitones = 0">Reset</Button>
            </div>
        </div>

        <Card
            v-for="(song, idx) in playlist.songs"
            :id="`playlist-song-${song.id}`"
            :key="song.id"
            :data-song-id="song.id"
            class="scroll-mt-24"
        >
            <CardHeader>
                <CardTitle class="flex items-center gap-3">
                    <span class="text-muted-foreground">{{ Number(idx) + 1 }}.</span>
                    {{ song.title }}
                    <Badge variant="secondary">{{ playlistKey(song, semitones) }}</Badge>
                    <span v-if="song.bpm" class="text-sm text-muted-foreground">BPM {{ song.bpm }}</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    class="overflow-x-auto pt-2 text-sm leading-6 [font-variant-ligatures:none]"
                    :class="lyricsOnly ? 'font-sans text-base leading-7' : 'font-mono'"
                    v-html="renderPlaylistSong(song, semitones, lyricsOnly)"
                ></div>
            </CardContent>
        </Card>

        <p v-if="!playlist.songs || playlist.songs.length === 0" class="text-muted-foreground">
            Sin canciones.
        </p>
        </div>

        <PlaylistSongNav
            v-if="railSongs.length"
            :songs="railSongs"
            :active-id="activeId"
            @select="goToSong"
        />
    </div>
</template>