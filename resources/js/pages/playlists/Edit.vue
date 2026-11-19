<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlaylistController from '@/actions/App/Http/Controllers/PlaylistController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as playlists from '@/routes/playlists';

defineProps<{ playlist: any }>();

defineOptions({
    layout: (props: any) => ({
        breadcrumbs: [
            { title: 'Playlists', href: playlists.index() },
            { title: props.playlist.name, href: playlists.show(props.playlist.id) },
            { title: 'Editar', href: playlists.edit(props.playlist.id) },
        ],
    }),
});
</script>

<template>
    <Head title="Editar playlist" />

    <div class="flex flex-col gap-4 p-4 max-w-2xl">
        <h1 class="text-2xl font-bold">Editar playlist</h1>

        <Card>
            <CardContent class="pt-6">
                <Form v-bind="PlaylistController.update.form(playlist.id)" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" name="name" :default-value="playlist.name" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        >{{ playlist.description }}</textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_public" name="is_public" value="1" :checked="playlist.is_public" />
                        <Label for="is_public">Pública</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit">Guardar</Button>
                        <Link :href="playlists.show(playlist.id)">
                            <Button variant="outline" type="button">Cancelar</Button>
                        </Link>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>