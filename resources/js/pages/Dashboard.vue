<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import * as playlists from '@/routes/playlists';
import * as songs from '@/routes/songs';

defineProps<{
    songCount: number;
    artists: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="playlists.index()">Playlists</Link>
                </Button>
                <Button as-child>
                    <Link :href="songs.create()">Agregar canción</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Canciones</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-4xl font-bold">{{ songCount }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Artistas</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="artists.length === 0" class="text-sm text-muted-foreground">
                        Todavía no hay artistas.
                    </p>
                    <ul v-else class="flex flex-wrap gap-2">
                        <li
                            v-for="artist in artists"
                            :key="artist"
                            class="rounded-full border px-3 py-1 text-sm"
                        >
                            {{ artist }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
