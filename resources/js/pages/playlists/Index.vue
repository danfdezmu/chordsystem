<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ListMusic, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as playlists from '@/routes/playlists';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Playlists', href: playlists.index() }],
    },
});

const props = defineProps<{ playlists: any[] }>();
</script>

<template>
    <Head title="Playlists" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Playlists / Servicios</h1>
            <Link :href="playlists.create()">
                <Button>
                    <Plus class="size-4" />
                    Nueva playlist
                </Button>
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="pl in props.playlists" :key="pl.id">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ListMusic class="size-4" />
                        {{ pl.name }}
                    </CardTitle>
                    <p v-if="pl.description" class="text-sm text-muted-foreground">{{ pl.description }}</p>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ pl.is_public ? 'Pública' : 'Privada' }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Link :href="playlists.show(pl.id)">
                            <Button size="sm" variant="outline">Ver</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="props.playlists.length === 0">
                <CardContent class="py-8 text-center text-muted-foreground">
                    No hay playlists todavía.
                </CardContent>
            </Card>
        </div>
    </div>
</template>