<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SongController from '@/actions/App/Http/Controllers/SongController';
import SectionEditor from '@/components/SectionEditor.vue';
import { sectionColorClass } from '@/lib/chart';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as songs from '@/routes/songs';

const props = defineProps<{
    song: any;
}>();

const content = ref(props.song.content || '');
const structure = ref(props.song.structure || '');
const chips = ref<string[]>(
    (props.song.structure || '')
        .trim()
        .split(/[\s,]+/)
        .filter(Boolean),
);

const availableSections = computed(() => {
    const labels: string[] = [];
    let cur: string | null = null;
    for (const raw of (content.value || '').split('\n')) {
        const m = raw.trim().match(/^\[(.+)\]$/);
        if (m) {
            if (cur) labels.push(cur);
            cur = m[1];
        }
    }
    if (cur) labels.push(cur);
    return labels.length ? labels : [];
});

// initialize chips when structure empty
watch(content, () => {
    if (!structure.value.trim() && chips.value.length === 0) {
        chips.value = [...availableSections.value];
    }
});

watch(chips, (v) => {
    structure.value = v.join(' ');
}, { deep: true });

// drag state
const dragIndex = ref<number | null>(null);
function onDragStart(i: number) {
    dragIndex.value = i;
}
function onDragOver(e: DragEvent, i: number) {
    e.preventDefault();
}
function onDrop(i: number) {
    if (dragIndex.value === null || dragIndex.value === i) return;
    const item = chips.value.splice(dragIndex.value, 1)[0];
    chips.value.splice(i, 0, item);
    dragIndex.value = null;
}
function removeChip(i: number) {
    chips.value.splice(i, 1);
}
function addChip(label: string) {
    chips.value.push(label);
}

defineOptions({
    layout: (props: any) => ({
        breadcrumbs: [
            { title: 'Canciones', href: songs.index() },
            { title: props.song.title, href: songs.show(props.song.id) },
            { title: 'Editar', href: songs.edit(props.song.id) },
        ],
    }),
});
</script>

<template>
    <Head title="Editar canción" />

    <div class="flex flex-col gap-4 p-4 max-w-3xl">
        <h1 class="text-2xl font-bold">Editar canción</h1>

        <Card>
            <CardContent class="pt-6">
                <Form
                    v-bind="SongController.update.form(song.id)"
                    class="space-y-4"
                >
                    <div class="grid gap-2">
                        <Label for="title">Título</Label>
                        <Input id="title" name="title" :default-value="song.title" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="artist">Artista</Label>
                        <Input id="artist" name="artist" :default-value="song.artist" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="original_key">Tonalidad original</Label>
                        <Input id="original_key" name="original_key" :default-value="song.original_key" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="bpm">BPM</Label>
                            <Input id="bpm" name="bpm" type="number" :default-value="song.bpm" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="time_signature">Compás</Label>
                            <Input id="time_signature" name="time_signature" :default-value="song.time_signature" placeholder="4/4" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Estructura de la canción (arrastra para ordenar / repetir)</Label>
                        <div class="flex flex-wrap gap-2 rounded-md border p-3 min-h-12">
                            <span
                                v-for="(chip, i) in chips"
                                :key="i"
                                draggable="true"
                                :class="['flex cursor-move items-center gap-1 rounded-full px-3 py-1 text-sm font-bold', sectionColorClass(chip)]"
                                @dragstart="onDragStart(i)"
                                @dragover="onDragOver($event, i)"
                                @drop="onDrop(i)"
                            >
                                {{ chip }}
                                <button type="button" class="ml-1 text-xs opacity-60 hover:opacity-100" @click="removeChip(i)">×</button>
                            </span>
                            <span v-if="chips.length === 0" class="text-xs text-muted-foreground">Sin estructura. Se mostrarán todas las secciones en orden.</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="sec in availableSections"
                                :key="sec"
                                type="button"
                                :class="['flex size-9 items-center justify-center rounded-full text-xs font-bold hover:opacity-80', sectionColorClass(sec)]"
                                :title="`Agregar ${sec} a la estructura`"
                                @click="addChip(sec)"
                            >
                                {{ sec.replace(/[^a-zA-Z]/g, '').charAt(0).toUpperCase() }}{{ sec.match(/\d/)?.[0] || '' }}
                            </button>
                        </div>
                        <input type="hidden" name="structure" :value="structure" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="content">Letra y acordes</Label>
                        <p class="text-xs text-muted-foreground">
                            Cada sección es un bloque. Las notas del unísono van en rojo, encima de los acordes. Separa los bloques con una línea en blanco.
                        </p>
                        <SectionEditor v-model="content" :key-name="props.song.original_key" :mode="'major'" />
                        <textarea name="content" hidden v-model="content" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_public" name="is_public" value="1" :checked="song.is_public" />
                        <Label for="is_public">Pública</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit">Guardar</Button>
                        <Button variant="outline" as-child>
                            <Link :href="songs.show(song.id)">Cancelar</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>