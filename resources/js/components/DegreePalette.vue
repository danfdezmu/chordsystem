<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    lyrics: string;
    key: string;
    mode: 'major' | 'minor';
}>();
const emit = defineEmits<{ (e: 'place', col: number, degree: string): void }>();

const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

function noteIndex(n: string): number {
    const map: Record<string, string> = { Db: 'C#', Eb: 'D#', Gb: 'F#', Ab: 'G#', Bb: 'A#' };
    return NOTES.indexOf(map[n] ?? n);
}

const DEGREES_MAJOR = [
    { roman: 'I', offset: 0, quality: '' },
    { roman: 'ii', offset: 2, quality: 'm' },
    { roman: 'iii', offset: 4, quality: 'm' },
    { roman: 'IV', offset: 5, quality: '' },
    { roman: 'V', offset: 7, quality: '' },
    { roman: 'vi', offset: 9, quality: 'm' },
    { roman: 'vii°', offset: 11, quality: 'dim' },
];

const DEGREES_MINOR = [
    { roman: 'i', offset: 0, quality: 'm' },
    { roman: 'ii°', offset: 2, quality: 'dim' },
    { roman: 'III', offset: 3, quality: '' },
    { roman: 'iv', offset: 5, quality: 'm' },
    { roman: 'v', offset: 7, quality: 'm' },
    { roman: 'VI', offset: 8, quality: '' },
    { roman: 'VII', offset: 10, quality: '' },
];

const degrees = computed(() => {
    const list = props.mode === 'minor' ? DEGREES_MINOR : DEGREES_MAJOR;
    const rootIdx = noteIndex(props.key);
    if (rootIdx < 0) return [];
    return list.map((d) => {
        const idx = (rootIdx + d.offset) % 12;
        const note = NOTES[idx];
        const chord = note + (d.quality === 'm' ? 'm' : d.quality === 'dim' ? 'dim' : '');
        return { roman: d.roman, chord };
    });
});

const selectedDegree = ref<string | null>(null);

function selectDegree(roman: string) {
    selectedDegree.value = selectedDegree.value === roman ? null : roman;
}

function placeAt(col: number) {
    if (!selectedDegree.value) return;
    const deg = degrees.value.find((d) => d.roman === selectedDegree.value);
    if (!deg) return;
    emit('place', col, deg.chord);
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="d in degrees"
                :key="d.roman"
                type="button"
                :class="[
                    'flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-xs font-bold transition',
                    selectedDegree === d.roman
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-primary/10 text-primary hover:bg-primary/20',
                ]"
                @click="selectDegree(d.roman)"
            >
                {{ d.roman }}
            </button>
        </div>
        <p v-if="selectedDegree" class="text-xs text-muted-foreground">
            Seleccionado: {{ selectedDegree }}. Haz clic en la letra para colocarlo.
        </p>
        <div class="whitespace-pre rounded-md bg-muted/40 p-2 font-mono text-sm">
            <span
                v-for="(ch, ci) in (lyrics || ' ')"
                :key="ci"
                class="cursor-pointer hover:bg-accent/40"
                @click="placeAt(ci)"
            >{{ ch === ' ' ? '\u00A0' : ch }}</span>
        </div>
    </div>
</template>