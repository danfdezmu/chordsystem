<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PlaylistSongNav from '@/components/PlaylistSongNav.vue';
import { observePlaylistSongs, playlistKey, renderPlaylistSong } from '@/lib/playlistSong';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const props = defineProps<{ playlist: any }>();

const semitones = ref(0);
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
    <Head :title="playlist.name" />

    <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-start">
        <div class="flex min-w-0 flex-1 flex-col gap-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                <p v-if="playlist.description" class="text-muted-foreground">{{ playlist.description }}</p>
            </div>
            <div class="flex items-center gap-2">
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

        <Card
            v-for="song in playlist.songs"
            :id="`playlist-song-${song.id}`"
            :key="song.id"
            :data-song-id="song.id"
            class="scroll-mt-24"
        >
            <CardHeader>
                <CardTitle>{{ song.title }}</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Tonalidad: {{ playlistKey(song, semitones) }}
                </p>
            </CardHeader>
            <CardContent>
                <div class="font-mono text-sm leading-relaxed" v-html="renderPlaylistSong(song, semitones)"></div>
            </CardContent>
        </Card>
        </div>

        <PlaylistSongNav
            v-if="railSongs.length"
            :songs="railSongs"
            :active-id="activeId"
            @select="goToSong"
        />
    </div>
</template>