<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SongController from '@/actions/App/Http/Controllers/SongController';
import { findSection, isVariationPlay, parseSections, sectionColorClass, sectionHasVariation, sectionLabels, sectionName, structureChipClass } from '@/lib/chart';
import { parseStructure, sectionAbbr, serializeStructure } from '@/lib/structure';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as songs from '@/routes/songs';

const props = defineProps<{
    song: any;
}>();

const content = ref(props.song.content || '');
const chips = ref<string[]>(parseStructure(props.song.structure || ''));
const structure = ref(serializeStructure(chips.value));
const ignoreChipClick = ref(false);

const availableSections = computed(() => sectionLabels(content.value || ''));

// initialize chips when structure empty
watch(content, () => {
    if (!structure.value.trim() && chips.value.length === 0) {
        chips.value = [...availableSections.value];
    }
});

watch(chips, (v) => {
    structure.value = serializeStructure(v);
}, { deep: true });

// drag state
const dragIndex = ref<number | null>(null);
function onDragStart(i: number) {
    ignoreChipClick.value = true;
    dragIndex.value = i;
}
function onDragEnd() {
    window.setTimeout(() => {
        ignoreChipClick.value = false;
    }, 0);
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
function chipHasVariation(label: string): boolean {
    const section = findSection(parseSections(content.value || ''), label);
    return section ? sectionHasVariation(section.body) : false;
}
function toggleVariation(index: number) {
    if (ignoreChipClick.value || !chipHasVariation(chips.value[index])) {
        return;
    }
    const name = sectionName(chips.value[index]);
    chips.value[index] = isVariationPlay(chips.value[index]) ? name : `${name} *`;
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

    <div class="flex flex-col gap-4 p-4 max-w-5xl">
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
                                :class="['flex items-center gap-1 rounded-full px-3 py-1 text-sm font-bold', chipHasVariation(chip) ? 'cursor-pointer' : 'cursor-move', structureChipClass(chip)]"
                                :title="chipHasVariation(chip) ? 'Clic para alternar original y variación' : sectionName(chip)"
                                @click="toggleVariation(i)"
                                @dragstart="onDragStart(i)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver($event, i)"
                                @drop="onDrop(i)"
                            >
                                {{ sectionName(chip) }}
                                <button type="button" class="ml-1 text-xs opacity-60 hover:opacity-100" @click.stop="removeChip(i)">×</button>
                            </span>
                            <span v-if="chips.length === 0" class="text-xs text-muted-foreground">Sin estructura. Se mostrarán todas las secciones en orden.</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="sec in availableSections"
                                :key="sec"
                                type="button"
                                :class="['flex h-9 min-w-9 items-center justify-center rounded-full px-2 text-xs font-bold hover:opacity-80', sectionColorClass(sec)]"
                                :title="`Agregar ${sec} a la estructura`"
                                @click="addChip(sec)"
                            >
                                {{ sectionAbbr(sec) }}
                            </button>
                        </div>
                        <input type="hidden" name="structure" :value="structure" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="content">Letra y acordes</Label>
                        <p class="text-xs text-muted-foreground">
                            Escribe cada sección entre corchetes. Los acordes van arriba de la letra, respetando los espacios. El unísono va así: [Unisono] {C D E F G}. Para una variación, pon los acordes con asterisco en la línea de arriba, por ejemplo C#m* B*, y el unísono alterno como [Unisono*] {E F#}. En el orden, un clic sobre esa sección alterna el original y la variación: la variación se ve en color sólido.
                        </p>
                        <textarea
                            id="content"
                            name="content"
                            v-model="content"
                            rows="22"
                            wrap="off"
                            spellcheck="false"
                            required
                            placeholder="[Intro]&#10;D/F# G Bm A&#10;&#10;[Verso 1]&#10;[Unisono] {C D E F G}&#10;D/F#    G       Bm    A&#10;        Somos tus hijos"
                            class="border-input placeholder:text-muted-foreground dark:bg-input/30 min-h-80 w-full overflow-auto rounded-md border bg-transparent px-3 py-2 font-mono text-sm leading-6 whitespace-pre shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
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