<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    modelValue: { chords: string; lyrics: string; unison: string };
}>();
const emit = defineEmits<{ (e: 'update:modelValue', v: { chords: string; lyrics: string; unison: string }): void }>();

const lyricsRef = ref<HTMLInputElement | null>(null);
const chordInput = ref('');
const showChordInput = ref(false);
const cursorPos = ref(0);

const local = ref({ ...props.modelValue });

watch(() => props.modelValue, (v) => {
    local.value = { ...v };
}, { deep: true });

function update() {
    emit('update:modelValue', { ...local.value });
}

function placeChord() {
    const pos = lyricsRef.value?.selectionStart ?? local.value.lyrics.length;
    cursorPos.value = pos;
    showChordInput.value = true;
    chordInput.value = '';
}

function confirmChord() {
    const pos = cursorPos.value;
    const chord = chordInput.value.trim();
    if (!chord) {
        showChordInput.value = false;
        return;
    }
    let chords = local.value.chords;
    // pad to position
    if (chords.length < pos) chords = chords + ' '.repeat(pos - chords.length);
    // insert chord (overwrite spaces)
    chords = chords.slice(0, pos) + chord + chords.slice(pos + chord.length);
    local.value.chords = chords;
    update();
    showChordInput.value = false;
}
</script>

<template>
    <div class="space-y-2">
        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">Arreglo / unísono (opcional)</Label>
            <Input v-model="local.unison" @change="update" placeholder="notas al unísono" />
        </div>

        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">Letra (haz clic donde va el acorde)</Label>
            <div class="flex gap-2">
                <Input
                    ref="lyricsRef"
                    v-model="local.lyrics"
                    @input="update"
                    placeholder="línea de letra"
                    class="font-mono"
                />
                <Button type="button" size="sm" variant="outline" @click="placeChord">+ Acorde</Button>
            </div>
        </div>

        <div v-if="showChordInput" class="flex gap-2 items-center">
            <Input v-model="chordInput" placeholder="Acorde (ej. C, Gm7)" class="w-40" @keyup.enter="confirmChord" />
            <Button type="button" size="sm" @click="confirmChord">OK</Button>
            <span class="text-xs text-muted-foreground">Posición: {{ cursorPos }}</span>
        </div>

        <div class="grid gap-1">
            <Label class="text-xs text-muted-foreground">Acordes (alineados)</Label>
            <Input v-model="local.chords" @input="update" placeholder="C  G  Am  F" class="font-mono" />
        </div>

        <!-- Preview -->
        <div class="rounded-md bg-muted/40 p-2 font-mono text-sm whitespace-pre">
            <div class="text-blue-600 dark:text-blue-400">{{ local.chords || ' ' }}</div>
            <div>{{ local.lyrics || ' ' }}</div>
        </div>
    </div>
</template>