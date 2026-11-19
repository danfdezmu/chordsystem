<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Music, Plus, Upload } from '@lucide/vue';
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
        ],
    },
});

const props = defineProps<{
    songs: any[];
}>();

const showImport = ref(false);
</script>

<template>
    <Head title="Canciones" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Canciones</h1>
            <div class="flex gap-2">
                <Button variant="outline" @click="showImport = !showImport">
                    <Upload class="size-4" />
                    Importar PDF
                </Button>
                <Link :href="songs.create()">
                    <Button>
                        <Plus class="size-4" />
                        Nueva canción
                    </Button>
                </Link>
            </div>
        </div>

        <Card v-if="showImport">
            <CardHeader>
                <CardTitle>Importar desde PDF</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="SongController.importMethod.form()"
                    enctype="multipart/form-data"
                    class="flex items-end gap-4"
                >
                    <div class="grid gap-2 flex-1">
                        <Label for="pdf">Archivo PDF</Label>
                        <Input id="pdf" type="file" name="pdf" accept="application/pdf" required />
                    </div>
                    <Button type="submit">Importar</Button>
                </Form>
            </CardContent>
        </Card>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="song in props.songs" :key="song.id">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Music class="size-4" />
                        {{ song.title }}
                    </CardTitle>
                    <p v-if="song.artist" class="text-sm text-muted-foreground">
                        {{ song.artist }}
                    </p>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Tonalidad: {{ song.original_key }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Link :href="songs.show(song.id)">
                            <Button size="sm" variant="outline">Ver</Button>
                        </Link>
                        <Link :href="songs.edit(song.id)">
                            <Button size="sm" variant="ghost">Editar</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="props.songs.length === 0">
                <CardContent class="py-8 text-center text-muted-foreground">
                    No hay canciones todavía.
                </CardContent>
            </Card>
        </div>
    </div>
</template>