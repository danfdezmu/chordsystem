<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SongController from '@/actions/App/Http/Controllers/SongController';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as songs from '@/routes/songs';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Canciones', href: songs.index() },
            { title: 'Nueva', href: songs.create() },
        ],
    },
});

const content = ref('');

</script>

<template>
    <Head title="Nueva canción" />

    <div class="flex flex-col gap-4 p-4 max-w-5xl">
        <h1 class="text-2xl font-bold">Nueva canción</h1>

        <Card>
            <CardContent class="pt-6">
                <Form v-bind="SongController.store.form()" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="title">Título</Label>
                        <Input id="title" name="title" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="artist">Artista</Label>
                        <Input id="artist" name="artist" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="original_key">Tonalidad original</Label>
                        <Input id="original_key" name="original_key" default-value="C" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="bpm">BPM</Label>
                            <Input id="bpm" name="bpm" type="number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="time_signature">Compás</Label>
                            <Input id="time_signature" name="time_signature" placeholder="4/4" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="content">Letra y acordes</Label>
                        <p class="text-xs text-muted-foreground">
                            Escribe cada sección entre corchetes. Los acordes van arriba de la letra, respetando los espacios. El unísono va así: [Unisono] {C D E F G}. Al guardar, esas secciones se pueden ordenar y repetir.
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
                        <input type="checkbox" id="is_public" name="is_public" value="1" />
                        <Label for="is_public">Pública</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit">Guardar</Button>
                        <Button variant="outline" as-child>
                            <Link :href="songs.index()">Cancelar</Link>
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>