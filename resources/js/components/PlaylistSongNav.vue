<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    songs: Array<{ id: number; title: string; artist?: string | null; key?: string | null }>;
    activeId: number | null;
    hrefFor?: (songId: number) => string;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();
</script>

<template>
    <aside class="order-first flex max-h-48 w-full shrink-0 flex-col gap-1 overflow-auto rounded-lg border bg-background p-2 lg:sticky lg:top-4 lg:order-none lg:max-h-[calc(100vh-6rem)] lg:w-64">
        <p class="px-2 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Canciones</p>
        <component
            :is="hrefFor ? Link : 'button'"
            v-for="(song, index) in songs"
            :key="song.id"
            :href="hrefFor ? hrefFor(song.id) : undefined"
            :type="hrefFor ? undefined : 'button'"
            class="flex items-start gap-2 rounded-md px-2 py-1.5 text-left text-sm"
            :class="song.id === activeId ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            @click="emit('select', song.id)"
        >
            <span class="w-5 shrink-0 tabular-nums opacity-70">{{ index + 1 }}</span>
            <span class="min-w-0">
                <span class="block truncate font-medium">{{ song.title }}</span>
                <span v-if="song.artist" class="block truncate text-xs opacity-80">{{ song.artist }}</span>
            </span>
        </component>
    </aside>
</template>
