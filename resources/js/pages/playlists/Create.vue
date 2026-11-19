<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlaylistController from '@/actions/App/Http/Controllers/PlaylistController';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as playlists from '@/routes/playlists';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Playlists', href: playlists.index() },
            { title: 'Nueva', href: playlists.create() },
        ],
    },
});
</script>

<template>
    <Head title="Nueva playlist" />

    <div class="flex flex-col gap-4 p-4 max-w-2xl">
        <h1 class="text-2xl font-bold">Nueva playlist</h1>

        <Card>
            <CardContent class="pt-6">
                <Form v-bind="PlaylistController.store.form()" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" name="name" required />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                        ></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_public" name="is_public" value="1" />
                        <Label for="is_public">Pública</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit">Crear</Button>
                        <Link :href="playlists.index()">
                            <Button variant="outline" type="button">Cancelar</Button>
                        </Link>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>