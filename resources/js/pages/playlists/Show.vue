<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ListMusic, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PlaylistController from '@/actions/App/Http/Controllers/PlaylistController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { sectionColorClass, sectionHeader } from '@/lib/chart';
import { parseStructure, sectionAbbr, serializeStructure } from '@/lib/structure';
import * as playlists from '@/routes/playlists';
import * as songs from '@/routes/songs';
import * as playlistEditors from '@/routes/playlists/editors';
import * as playlistSongs from '@/routes/playlists/songs';

defineOptions({
    layout: (props: any) => ({
        breadcrumbs: [
            { title: 'Playlists', href: playlists.index() },
            { title: props.playlist.name, href: playlists.show(props.playlist.id) },
        ],
    }),
});

const props = defineProps<{
    playlist: any;
    availableSongs: any[];
    canEdit: boolean;
    isOwner: boolean;
    editors: Array<{ id: number; name: string; email: string }>;
}>();

const copied = ref(false);
const shareLink = ref<HTMLInputElement | null>(null);
const editorQuery = ref('');
const editorMatches = ref<Array<{ id: number; name: string; email: string }>>([]);
const editorSearchEmpty = ref(false);
let editorSearchTimer: ReturnType<typeof setTimeout> | undefined;

const ROOTS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const MAJOR_KEYS = ROOTS;
const MINOR_KEYS = ROOTS.map((root) => `${root}m`);

function isMinorKey(key: string): boolean {
    const rest = key.trim().replace(/^[A-G][#b]?/, '').toLowerCase();
    if (rest.startsWith('maj')) {
        return false;
    }
    return rest.startsWith('m');
}

const query = ref('');
const editingId = ref<number | null>(null);
const draftKey = ref('');
const draftChips = ref<string[]>([]);
const dragIndex = ref<number | null>(null);

const shareUrl = computed(() => {
    if (!props.playlist.share_token) return '';
    return `${window.location.origin}${playlists.shared(props.playlist.share_token as string).url}`;
});

const results = computed(() => {
    const term = query.value.trim().toLowerCase();
    if (term === '') {
        return [];
    }
    const taken = new Set((props.playlist.songs ?? []).map((song: { id: number }) => song.id));
    return props.availableSongs
        .filter((song) => {
            if (taken.has(song.id)) {
                return false;
            }
            const haystack = `${song.title ?? ''} ${song.artist ?? ''}`.toLowerCase();
            return haystack.includes(term);
        })
        .slice(0, 8);
});

function songKey(song: { original_key: string; pivot?: { key_override?: string | null } }): string {
    return song.pivot?.key_override?.trim() || song.original_key;
}

function keyIsCustom(song: { pivot?: { key_override?: string | null } }): boolean {
    return Boolean(song.pivot?.key_override?.trim());
}

function songOrder(song: { structure?: string | null; pivot?: { structure?: string | null } }): string {
    return song.pivot?.structure?.trim() || song.structure?.trim() || '';
}

function orderIsCustom(song: { pivot?: { structure?: string | null } }): boolean {
    return Boolean(song.pivot?.structure?.trim());
}

function sectionsFromContent(content: string): string[] {
    const labels: string[] = [];
    const seen = new Set<string>();
    for (const raw of (content || '').split('\n')) {
        const label = sectionHeader(raw);
        if (!label || seen.has(label)) {
            continue;
        }
        seen.add(label);
        labels.push(label);
    }
    return labels;
}

const editingSong = computed(() =>
    (props.playlist.songs ?? []).find((song: { id: number }) => song.id === editingId.value) ?? null,
);

const sectionPalette = computed(() => sectionsFromContent(editingSong.value?.content ?? ''));

const keyChoices = computed(() => {
    const source = editingSong.value?.original_key || draftKey.value;
    const choices = isMinorKey(source) ? MINOR_KEYS : MAJOR_KEYS;
    if (draftKey.value && !choices.includes(draftKey.value)) {
        return [draftKey.value, ...choices];
    }
    return choices;
});

const draftStructure = computed(() => serializeStructure(draftChips.value));

function toggleAdjust(song: { id: number; content?: string; original_key: string; structure?: string | null; pivot?: { key_override?: string | null; structure?: string | null } }): void {
    if (editingId.value === song.id) {
        editingId.value = null;
        return;
    }
    editingId.value = song.id;
    draftKey.value = songKey(song);
    draftChips.value = parseStructure(songOrder(song));
}

function onDragStart(index: number): void {
    dragIndex.value = index;
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();
}

function onDrop(index: number): void {
    if (dragIndex.value === null || dragIndex.value === index) {
        return;
    }
    const [item] = draftChips.value.splice(dragIndex.value, 1);
    draftChips.value.splice(index, 0, item);
    dragIndex.value = null;
}

function removeChip(index: number): void {
    draftChips.value.splice(index, 1);
}

function addChip(label: string): void {
    draftChips.value.push(label);
}

async function copyShareLink(): Promise<void> {
    const url = shareUrl.value;
    shareLink.value?.focus();
    shareLink.value?.select();

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            document.execCommand('copy');
        }
        copied.value = true;
    } catch {
        copied.value = false;
    }
}

watch(editorQuery, (value) => {
    window.clearTimeout(editorSearchTimer);
    const term = value.trim();

    if (term.length < 3) {
        editorMatches.value = [];
        editorSearchEmpty.value = false;

        return;
    }

    editorSearchTimer = window.setTimeout(async () => {
        const response = await fetch(
            PlaylistController.searchEditors.url(props.playlist.id, { query: { q: term } }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (editorQuery.value.trim() !== term) {
            return;
        }

        if (!response.ok) {
            editorMatches.value = [];
            editorSearchEmpty.value = true;

            return;
        }

        const data = (await response.json()) as { users: Array<{ id: number; name: string; email: string }> };
        editorMatches.value = data.users;
        editorSearchEmpty.value = data.users.length === 0;
    }, 200);
});
</script>

<template>
    <Head :title="playlist.name" />

    <div class="flex flex-col gap-4 p-4 max-w-4xl">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                <p v-if="playlist.description" class="text-muted-foreground">{{ playlist.description }}</p>
                <p class="text-sm text-muted-foreground mt-1">
                    {{ playlist.is_public ? 'Pública' : 'Privada' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link v-if="isOwner" :href="playlists.edit(playlist.id)">
                    <Button variant="outline" size="sm">Editar</Button>
                </Link>
                <Link :href="playlists.present(playlist.id)">
                    <Button variant="secondary" size="sm">Presentación</Button>
                </Link>
                <Form v-if="isOwner" v-bind="PlaylistController.togglePublic.form(playlist.id)">
                    <Button type="submit" variant="secondary" size="sm">
                        {{ playlist.is_public ? 'Hacer privada' : 'Hacer pública' }}
                    </Button>
                </Form>
            </div>
        </div>

        <Card v-if="isOwner">
            <CardHeader>
                <CardTitle>Compartir</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Quien tenga el enlace puede ver la playlist. No puede cambiar el orden ni la tonalidad.
                </p>
                <Form v-if="!playlist.share_token" v-bind="PlaylistController.share.form(playlist.id)">
                    <Button type="submit">Compartir</Button>
                </Form>
                <div v-else class="space-y-2">
                    <a :href="shareUrl" class="block break-all text-sm font-medium underline" target="_blank" rel="noopener">
                        {{ shareUrl }}
                    </a>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            ref="shareLink"
                            :value="shareUrl"
                            readonly
                            class="border-input bg-background h-9 min-w-64 flex-1 rounded-md border px-3 text-sm"
                        />
                        <Button type="button" variant="outline" @click="copyShareLink">
                            {{ copied ? 'Copiado' : 'Copiar enlace' }}
                        </Button>
                        <Form v-bind="PlaylistController.unshare.form(playlist.id)">
                            <Button type="submit" variant="ghost">Dejar de compartir</Button>
                        </Form>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="isOwner">
            <CardHeader>
                <CardTitle>Quién puede editar</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Estas personas pueden cambiar el orden y la tonalidad. El permiso lo das tú.
                </p>
                <div class="grid gap-2">
                    <Label for="editor-search">Usuario o correo</Label>
                    <Input
                        id="editor-search"
                        v-model="editorQuery"
                        type="search"
                        autocomplete="off"
                        placeholder="Escribe al menos 3 letras"
                    />
                    <p v-if="editorQuery.trim().length > 0 && editorQuery.trim().length < 3" class="text-sm text-muted-foreground">
                        Escribe al menos 3 letras.
                    </p>
                    <ul v-if="editorMatches.length" class="divide-y rounded-md border">
                        <li v-for="user in editorMatches" :key="user.id">
                            <Form v-bind="PlaylistController.grantEditor.form(playlist.id)">
                                <input type="hidden" name="user_id" :value="user.id" />
                                <button type="submit" class="hover:bg-muted flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm">
                                    <span class="font-medium">{{ user.name }}</span>
                                    <span class="text-muted-foreground">{{ user.email }}</span>
                                </button>
                            </Form>
                        </li>
                    </ul>
                    <p v-else-if="editorSearchEmpty" class="text-sm text-muted-foreground">
                        Sin coincidencias.
                    </p>
                </div>
                <p v-if="$page.props.errors.user_id" class="text-sm text-destructive">
                    {{ $page.props.errors.user_id }}
                </p>
                <ul v-if="editors.length" class="divide-y rounded-md border">
                    <li v-for="editor in editors" :key="editor.id" class="flex items-center justify-between gap-3 px-3 py-2">
                        <span>
                            <span class="font-medium">{{ editor.name }}</span>
                            <span class="text-muted-foreground"> · {{ editor.email }}</span>
                        </span>
                        <Link
                            :href="playlistEditors.destroy({ playlist: playlist.id, user: editor.id })"
                            method="delete"
                            as="button"
                            class="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Quitar
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card v-if="canEdit">
            <CardHeader>
                <CardTitle>Agregar canción</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-2">
                    <Label for="song-search">Buscar canción o artista</Label>
                    <Input id="song-search" v-model="query" placeholder="Escribe un título o un artista" />
                </div>
                <p v-if="query.trim() !== '' && results.length === 0" class="text-sm text-muted-foreground">
                    Sin resultados.
                </p>
                <ul v-if="results.length > 0" class="divide-y rounded-md border">
                    <li v-for="song in results" :key="song.id">
                        <Form v-bind="PlaylistController.addSong.form(playlist.id)">
                            <input type="hidden" name="song_id" :value="song.id" />
                            <button type="submit" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-muted">
                                <span>
                                    <span class="font-medium">{{ song.title }}</span>
                                    <span v-if="song.artist" class="text-muted-foreground"> — {{ song.artist }}</span>
                                </span>
                                <span class="text-sm text-muted-foreground">{{ song.original_key }}</span>
                            </button>
                        </Form>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ListMusic class="size-4" />
                    Canciones ({{ playlist.songs?.length ?? 0 }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="!playlist.songs || playlist.songs.length === 0" class="text-muted-foreground">
                    Sin canciones.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="(song, idx) in playlist.songs"
                        :key="song.id"
                        class="py-3 space-y-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <Link :href="songs.show(song.id, { query: { playlist: playlist.id } })" class="font-medium hover:underline">
                                    {{ Number(idx) + 1 }}. {{ song.title }}
                                </Link>
                                <p v-if="song.artist" class="text-sm text-muted-foreground">{{ song.artist }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Tonalidad: {{ songKey(song) }}
                                    · {{ keyIsCustom(song) ? 'solo en esta playlist' : 'de la canción' }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Orden: {{ songOrder(song) || 'el de la letra' }}
                                    · {{ orderIsCustom(song) ? 'solo en esta playlist' : 'de la canción' }}
                                </p>
                            </div>
                            <div v-if="canEdit" class="flex gap-1">
                                <Button size="sm" variant="outline" type="button" @click="toggleAdjust(song)">
                                    {{ editingId === song.id ? 'Cerrar' : 'Ajustar' }}
                                </Button>
                                <Button size="icon-sm" variant="ghost" as-child>
                                    <Link
                                        :href="playlistSongs.remove({ playlist: playlist.id, song: song.id })"
                                        method="delete"
                                    >
                                        <Trash2 class="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <div v-if="canEdit && editingId === song.id" class="space-y-3 rounded-md border p-3">
                            <p class="text-sm text-muted-foreground">
                                Estos cambios quedan en la playlist. La canción original sigue igual.
                            </p>
                            <Form
                                v-bind="playlistSongs.update.form({ playlist: playlist.id, song: song.id })"
                                class="space-y-3"
                            >
                                <div class="grid max-w-xs gap-2">
                                    <Label>Tonalidad</Label>
                                    <Select v-model="draftKey">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Tonalidad" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="key in keyChoices" :key="key" :value="key">
                                                {{ key }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <input type="hidden" name="key_override" :value="draftKey" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Orden</Label>
                                    <div class="flex min-h-12 flex-wrap gap-2 rounded-md border p-3">
                                        <span
                                            v-for="(chip, index) in draftChips"
                                            :key="`${chip}-${index}`"
                                            draggable="true"
                                            :class="['flex cursor-move items-center gap-1 rounded-full px-3 py-1 text-sm font-bold', sectionColorClass(chip)]"
                                            @dragstart="onDragStart(index)"
                                            @dragover="onDragOver($event)"
                                            @drop="onDrop(index)"
                                        >
                                            {{ chip }}
                                            <button type="button" class="ml-1 text-xs opacity-60 hover:opacity-100" @click="removeChip(index)">×</button>
                                        </span>
                                        <span v-if="draftChips.length === 0" class="text-xs text-muted-foreground">
                                            Sin orden propio. Se usa el de la canción.
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="section in sectionPalette"
                                            :key="section"
                                            type="button"
                                            :class="['flex size-9 items-center justify-center rounded-full text-xs font-bold hover:opacity-80', sectionColorClass(section)]"
                                            :title="`Agregar ${section}`"
                                            @click="addChip(section)"
                                        >
                                            {{ sectionAbbr(section) }}
                                        </button>
                                    </div>
                                    <input type="hidden" name="structure" :value="draftStructure" />
                                </div>
                                <Button type="submit" size="sm">Guardar</Button>
                            </Form>
                            <Form
                                v-if="keyIsCustom(song) || orderIsCustom(song)"
                                v-bind="playlistSongs.update.form({ playlist: playlist.id, song: song.id })"
                            >
                                <input type="hidden" name="key_override" value="" />
                                <input type="hidden" name="structure" value="" />
                                <Button type="submit" size="sm" variant="ghost">Volver a la canción</Button>
                            </Form>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>