<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { isChordToken } from '@/lib/chords';

const props = defineProps<{ modelValue: string; keyName?: string; mode?: 'major' | 'minor' }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

type ChordItem = { chord: string; col: number };
type Track = 'chord' | 'unison';
type Row = { chords: ChordItem[]; lyrics: string; unisons: ChordItem[]; adornment: string };
type DragState = {
    chord: string;
    label: string;
    track: Track;
    from?: { row: number; index: number; track: Track };
    x: number;
    y: number;
    over: { row: number; col: number } | null;
};

const EXTRA_COLUMNS = 48;

function parseChords(chords: string): ChordItem[] {
    const result: ChordItem[] = [];
    const regex = /\S+/g;
    let m: RegExpExecArray | null;
    while ((m = regex.exec(chords)) !== null) {
        result.push({ chord: m[0], col: m.index });
    }
    return result;
}

function serializeChords(items: ChordItem[], lyricsLen: number): string {
    if (items.length === 0) return '';
    const maxCol = Math.max(...items.map((c) => c.col + c.chord.length), lyricsLen);
    const chars: string[] = new Array(maxCol).fill(' ');
    for (const c of items) {
        for (let i = 0; i < c.chord.length; i++) {
            chars[c.col + i] = c.chord[i];
        }
    }
    return chars.join('').trimEnd();
}

function isNoteLine(line: string): boolean {
    const tokens = line.trim().split(/\s+/).filter(Boolean);
    return tokens.length > 0 && tokens.every((token) => isChordToken(token));
}

function emptyRow(): Row {
    return { chords: [], lyrics: '', unisons: [], adornment: '' };
}

function parseBody(body: string): Row[] {
    const lines = body.split('\n');
    const result: Row[] = [];
    let i = 0;
    while (i < lines.length) {
        if (lines[i].trim() === '') { i++; continue; }
        const row = emptyRow();
        const trimmed = lines[i].trim();
        if (trimmed === '{unisono}') {
            i++;
            if (i < lines.length && isNoteLine(lines[i])) {
                row.unisons = parseChords(lines[i]);
                i++;
            }
        } else if (trimmed.startsWith('{unisono}')) {
            const rest = lines[i].replace(/^\s*\{unisono\}/, '');
            if (isNoteLine(rest)) {
                row.unisons = parseChords(rest);
            } else if (rest.trim()) {
                row.adornment = trimmed;
            }
            i++;
        } else if (trimmed.startsWith('{')) {
            row.adornment = trimmed;
            i++;
        }
        if (i < lines.length && isNoteLine(lines[i])) {
            row.chords = parseChords(lines[i]);
            i++;
            if (i < lines.length && lines[i].trim() !== '') {
                row.lyrics = lines[i].trimEnd();
                i++;
            }
        } else if (i < lines.length && lines[i].trim() !== '') {
            row.lyrics = lines[i].trimEnd();
            i++;
        }
        result.push(row);
    }
    if (result.length === 0) result.push(emptyRow());
    return result;
}

function serialize(rows: Row[]): string {
    const out: string[] = [];
    for (const r of rows) {
        if (r.adornment.trim()) out.push(r.adornment.trim());
        const unisonLine = serializeChords(r.unisons, 0);
        if (unisonLine.trim()) {
            out.push('{unisono}');
            out.push(unisonLine);
        }
        const chordsStr = serializeChords(r.chords, r.lyrics.length);
        if (chordsStr.trim()) out.push(chordsStr);
        if (r.lyrics.trim()) out.push(r.lyrics.trimEnd());
        out.push('');
    }
    return out.join('\n').trim();
}

const rows = ref<Row[]>(parseBody(props.modelValue || ''));
const extraChords = ref<string[]>([]);
const extraNotes = ref<string[]>([]);
const drag = ref<DragState | null>(null);

watch(rows, (v) => {
    emit('update:modelValue', serialize(v));
}, { deep: true });

watch(() => props.modelValue, (v) => {
    if (serialize(rows.value) !== (v || '')) {
        rows.value = parseBody(v || '');
    }
});

function laneLength(row: Row): number {
    const farthest = Math.max(
        row.lyrics.length,
        ...row.chords.map((item) => item.col + 1),
        ...row.unisons.map((item) => item.col + 1),
        1,
    );
    return farthest + EXTRA_COLUMNS;
}

function laneCells(row: Row): { col: number; ch: string; extra: boolean }[] {
    const cells: { col: number; ch: string; extra: boolean }[] = [];
    const length = laneLength(row);
    for (let col = 0; col < length; col++) {
        if (col < row.lyrics.length) {
            cells.push({ col, ch: row.lyrics[col] === ' ' ? '\u00A0' : row.lyrics[col], extra: false });
        } else {
            cells.push({ col, ch: '\u00A0', extra: true });
        }
    }
    return cells;
}

function itemEnd(item: ChordItem): number {
    return item.col + item.chord.length + 1.5;
}

function stackIndex(items: ChordItem[], index: number): number {
    const ends: number[] = [];
    let placedRow = 0;
    for (let i = 0; i <= index; i++) {
        const start = items[i].col;
        let row = 0;
        while (row < ends.length && start < ends[row]) {
            row++;
        }
        ends[row] = itemEnd(items[i]);
        if (i === index) {
            placedRow = row;
        }
    }
    return placedRow;
}

function laneBoxHeight(items: ChordItem[]): string {
    if (items.length === 0) {
        return '1.75rem';
    }
    const rows = items.map((_, index) => stackIndex(items, index));
    return `${(Math.max(...rows) + 1) * 1.75}rem`;
}

function dropColumn(row: Row, col: number): number {
    const length = laneLength(row);
    if (length === 0) {
        return 0;
    }

    return Math.max(0, Math.min(col, length - 1));
}

function isColumnHighlighted(rowIdx: number, col: number): boolean {
    const over = drag.value?.over;
    if (!over || over.row !== rowIdx) {
        return false;
    }
    const row = rows.value[rowIdx];
    if (!row) {
        return false;
    }
    return col === dropColumn(row, over.col);
}

function ghostCol(rowIdx: number): number | null {
    const over = drag.value?.over;
    if (!over || over.row !== rowIdx) {
        return null;
    }
    const row = rows.value[rowIdx];
    if (!row) {
        return null;
    }
    return dropColumn(row, over.col);
}

function isSourceItem(rowIdx: number, itemIdx: number, track: Track): boolean {
    return drag.value?.from?.row === rowIdx && drag.value.from.index === itemIdx && drag.value.from.track === track;
}

function trackItems(row: Row, track: Track): ChordItem[] {
    return track === 'unison' ? row.unisons : row.chords;
}

function updateOver(event: PointerEvent) {
    if (!drag.value) {
        return;
    }
    const under = document.elementFromPoint(event.clientX, event.clientY);
    const target = under?.closest('[data-lyric-col]') as HTMLElement | null;
    const row = target ? Number(target.dataset.lyricRow) : NaN;
    const col = target ? Number(target.dataset.lyricCol) : NaN;
    drag.value = {
        ...drag.value,
        x: event.clientX,
        y: event.clientY,
        over: Number.isInteger(row) && Number.isInteger(col) ? { row, col } : null,
    };
}

function placeItem(payload: DragState) {
    const over = payload.over;
    if (!over) {
        return;
    }
    const row = rows.value[over.row];
    if (!row) {
        return;
    }
    const col = dropColumn(row, over.col);

    if (payload.from) {
        const source = rows.value[payload.from.row];
        const list = source ? trackItems(source, payload.from.track) : undefined;
        if (!list || !list[payload.from.index]) {
            return;
        }
        list.splice(payload.from.index, 1);
    }

    const target = trackItems(row, payload.track);
    target.push({ chord: payload.chord, col });
    target.sort((a, b) => a.col - b.col);
}

function releaseDrag() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
}

function onPointerMove(event: PointerEvent) {
    updateOver(event);
}

function onPointerUp(event: PointerEvent) {
    if (!drag.value) {
        return;
    }
    updateOver(event);
    const payload = drag.value;
    drag.value = null;
    releaseDrag();
    placeItem(payload);
}

function beginDrag(event: PointerEvent, chord: string, label = chord, track: Track = 'chord', from?: { row: number; index: number; track: Track }) {
    if (event.button !== 0 || !chord.trim()) {
        return;
    }
    event.preventDefault();
    const name = chord.trim();
    drag.value = {
        chord: name,
        label: label.trim() || name,
        track,
        from,
        x: event.clientX,
        y: event.clientY,
        over: null,
    };
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'grabbing';
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
    window.addEventListener('pointercancel', onPointerUp);
}

function removeItem(rowIdx: number, itemIdx: number, track: Track) {
    trackItems(rows.value[rowIdx], track).splice(itemIdx, 1);
}

const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const noteIndex = (n: string) => {
    const map: Record<string, string> = { Db: 'C#', Eb: 'D#', Gb: 'F#', Ab: 'G#', Bb: 'A#' };
    return NOTES.indexOf(map[n] ?? n);
};
const DEG_MAJOR = [
    { roman: 'I', off: 0, q: '' }, { roman: 'ii', off: 2, q: 'm' }, { roman: 'iii', off: 4, q: 'm' },
    { roman: 'IV', off: 5, q: '' }, { roman: 'V', off: 7, q: '' }, { roman: 'vi', off: 9, q: 'm' },
    { roman: 'vii°', off: 11, q: 'dim' },
];
const DEG_MINOR = [
    { roman: 'i', off: 0, q: 'm' }, { roman: 'ii°', off: 2, q: 'dim' }, { roman: 'III', off: 3, q: '' },
    { roman: 'iv', off: 5, q: 'm' }, { roman: 'v', off: 7, q: 'm' }, { roman: 'VI', off: 8, q: '' },
    { roman: 'VII', off: 10, q: '' },
];
const degrees = computed(() => {
    const list = (props.mode || 'major') === 'minor' ? DEG_MINOR : DEG_MAJOR;
    const rootIdx = noteIndex(props.keyName || 'C');
    if (rootIdx < 0) return [];
    return list.map((d) => {
        const idx = (rootIdx + d.off) % 12;
        const note = NOTES[idx];
        const chord = note + (d.q === 'm' ? 'm' : d.q === 'dim' ? 'dim' : '');
        return { roman: d.roman, chord };
    });
});

function chordLabel(chord: string): string {
    return degrees.value.find((degree) => degree.chord === chord)?.roman ?? chord;
}

function chordTitle(chord: string): string {
    const label = chordLabel(chord);
    const name = label === chord ? chord : `${label} (${chord})`;
    return `${name}. Arrastra para mover`;
}

const SCALE_MAJOR = [
    { degree: '1', off: 0 }, { degree: '2', off: 2 }, { degree: '3', off: 4 },
    { degree: '4', off: 5 }, { degree: '5', off: 7 }, { degree: '6', off: 9 },
    { degree: '7', off: 11 },
];
const SCALE_MINOR = [
    { degree: '1', off: 0 }, { degree: '2', off: 2 }, { degree: 'b3', off: 3 },
    { degree: '4', off: 5 }, { degree: '5', off: 7 }, { degree: 'b6', off: 8 },
    { degree: 'b7', off: 10 },
];
const scaleNotes = computed(() => {
    const list = (props.mode || 'major') === 'minor' ? SCALE_MINOR : SCALE_MAJOR;
    const rootIdx = noteIndex(props.keyName || 'C');
    if (rootIdx < 0) return [];
    return list.map((step) => ({
        degree: step.degree,
        note: NOTES[(rootIdx + step.off) % 12],
    }));
});

function extraChord(rowIdx: number): string {
    return extraChords.value[rowIdx] ?? '';
}

function setExtraChord(rowIdx: number, value: string | number) {
    extraChords.value[rowIdx] = String(value);
}

function extraNote(rowIdx: number): string {
    return extraNotes.value[rowIdx] ?? '';
}

function setExtraNote(rowIdx: number, value: string | number) {
    extraNotes.value[rowIdx] = String(value);
}

function addRow() {
    rows.value.push(emptyRow());
}

function removeRow(i: number) {
    rows.value.splice(i, 1);
    extraChords.value.splice(i, 1);
    extraNotes.value.splice(i, 1);
    if (rows.value.length === 0) rows.value.push(emptyRow());
}

onBeforeUnmount(releaseDrag);
</script>

<template>
    <div class="space-y-3">
        <p class="text-xs text-muted-foreground">
            Arrastra un grado o una nota del unísono sobre la letra exacta. La zona punteada sigue después de la letra para colocar arreglos más adelante. Al subir o bajar el tono, acordes y unísono se mueven juntos.
        </p>
        <div v-for="(row, ri) in rows" :key="ri" class="space-y-2 rounded-md border p-3">
            <div class="flex flex-wrap items-center gap-1">
                <button
                    v-for="d in degrees"
                    :key="d.roman"
                    type="button"
                    class="flex h-7 cursor-grab touch-none items-center justify-center rounded-full bg-primary/10 px-2 text-xs font-bold text-primary select-none hover:bg-primary/20 active:cursor-grabbing"
                    :title="`Arrastra ${d.roman} (${d.chord}) sobre la letra`"
                    @pointerdown="beginDrag($event, d.chord, d.roman, 'chord')"
                >
                    {{ d.roman }}
                </button>
                <Input
                    :model-value="extraChord(ri)"
                    class="h-7 w-28 text-xs"
                    placeholder="Otro acorde"
                    @update:model-value="setExtraChord(ri, $event)"
                />
                <span
                    v-if="extraChord(ri).trim()"
                    class="inline-flex h-7 cursor-grab touch-none items-center rounded-full bg-muted px-2 text-xs font-bold select-none active:cursor-grabbing"
                    :title="`Arrastra ${extraChord(ri).trim()} sobre la letra`"
                    @pointerdown="beginDrag($event, extraChord(ri), extraChord(ri), 'chord')"
                >
                    {{ extraChord(ri).trim() }}
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-1">
                <span class="mr-1 text-xs text-muted-foreground">Unísono</span>
                <button
                    v-for="note in scaleNotes"
                    :key="note.degree"
                    type="button"
                    class="flex h-7 cursor-grab touch-none items-center justify-center rounded-full bg-red-500/10 px-2 text-xs font-bold text-red-700 select-none hover:bg-red-500/20 active:cursor-grabbing dark:text-red-300"
                    :title="`Arrastra ${note.degree} (${note.note})`"
                    @pointerdown="beginDrag($event, note.note, note.note, 'unison')"
                >
                    {{ note.degree }}
                </button>
                <Input
                    :model-value="extraNote(ri)"
                    class="h-7 w-28 text-xs"
                    placeholder="Otra nota"
                    @update:model-value="setExtraNote(ri, $event)"
                />
                <span
                    v-if="isChordToken(extraNote(ri).trim())"
                    class="inline-flex h-7 cursor-grab touch-none items-center rounded-full bg-red-500/10 px-2 text-xs font-bold text-red-700 select-none active:cursor-grabbing dark:text-red-300"
                    :title="`Arrastra ${extraNote(ri).trim()}`"
                    @pointerdown="beginDrag($event, extraNote(ri), extraNote(ri), 'unison')"
                >
                    {{ extraNote(ri).trim() }}
                </span>
            </div>
            <Input v-model="row.lyrics" placeholder="Letra" class="font-mono" />
            <div class="overflow-x-auto rounded-md bg-muted/40">
                <div
                    class="relative w-max min-w-full px-3 py-2 font-mono text-sm"
                    style="font-variant-ligatures: none"
                >
                    <div class="relative mb-1" :style="{ height: laneBoxHeight(row.unisons) }">
                        <span
                            v-for="(note, ni) in row.unisons"
                            :key="`u-${note.col}-${ni}-${note.chord}`"
                            class="absolute inline-flex items-center whitespace-nowrap leading-none"
                            :class="isSourceItem(ri, ni, 'unison') ? 'opacity-30' : ''"
                            :style="{ left: `${note.col}ch`, top: `${stackIndex(row.unisons, ni) * 1.75}rem` }"
                        >
                            <span
                                class="cursor-grab touch-none font-bold text-red-600 select-none dark:text-red-400"
                                title="Arrastra para mover la nota"
                                @pointerdown="beginDrag($event, note.chord, note.chord, 'unison', { row: ri, index: ni, track: 'unison' })"
                            >{{ note.chord }}</span>
                            <button
                                type="button"
                                class="ml-0.5 px-0.5 text-xs text-destructive"
                                title="Quitar nota"
                                @pointerdown.stop
                                @click.stop="removeItem(ri, ni, 'unison')"
                            >×</button>
                        </span>
                        <span
                            v-if="ghostCol(ri) !== null && drag?.track === 'unison'"
                            class="pointer-events-none absolute top-0 font-bold whitespace-nowrap text-red-600 dark:text-red-400"
                            :style="{ left: `${ghostCol(ri)}ch` }"
                        >{{ drag?.label }}</span>
                    </div>
                    <div class="relative mb-1" :style="{ height: laneBoxHeight(row.chords) }">
                        <span
                            v-for="(c, ci) in row.chords"
                            :key="`${c.col}-${ci}-${c.chord}`"
                            class="absolute inline-flex items-center whitespace-nowrap leading-none"
                            :class="isSourceItem(ri, ci, 'chord') ? 'opacity-30' : ''"
                            :style="{ left: `${c.col}ch`, top: `${stackIndex(row.chords, ci) * 1.75}rem` }"
                        >
                            <span
                                class="cursor-grab touch-none font-bold text-blue-600 select-none dark:text-blue-400"
                                :title="chordTitle(c.chord)"
                                @pointerdown="beginDrag($event, c.chord, chordLabel(c.chord), 'chord', { row: ri, index: ci, track: 'chord' })"
                            >{{ chordLabel(c.chord) }}</span>
                            <button
                                type="button"
                                class="ml-0.5 px-0.5 text-xs text-destructive"
                                title="Quitar acorde"
                                @pointerdown.stop
                                @click.stop="removeItem(ri, ci, 'chord')"
                            >×</button>
                        </span>
                        <span
                            v-if="ghostCol(ri) !== null && drag?.track !== 'unison'"
                            class="pointer-events-none absolute top-0 font-bold whitespace-nowrap text-primary"
                            :style="{ left: `${ghostCol(ri)}ch` }"
                        >{{ drag?.label }}</span>
                    </div>
                    <div class="relative whitespace-pre select-none leading-none">
                        <div
                            v-if="row.lyrics.length < laneLength(row)"
                            class="pointer-events-none absolute bottom-0 border-b border-dotted border-muted-foreground/40"
                            :style="{ left: `${row.lyrics.length}ch`, right: '0' }"
                        ></div>
                        <span
                            v-for="cell in laneCells(row)"
                            :key="cell.col"
                            :data-lyric-row="ri"
                            :data-lyric-col="cell.col"
                            class="inline-block w-[1ch] text-center"
                            :class="isColumnHighlighted(ri, cell.col) ? 'bg-primary/30' : ''"
                        >{{ cell.ch }}</span>
                    </div>
                    <p v-if="!row.lyrics" class="mt-1 text-xs text-muted-foreground">
                        Escribe la letra, o suelta notas en la zona punteada.
                    </p>
                </div>
            </div>
            <Button type="button" size="sm" variant="ghost" @click="removeRow(ri)">Quitar bloque</Button>
        </div>
        <Button type="button" size="sm" variant="secondary" @click="addRow()">+ Bloque</Button>
        <div
            v-if="drag"
            class="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-8 rounded-md px-2 py-0.5 text-xs font-bold text-white shadow"
            :class="drag.track === 'unison' ? 'bg-red-600' : 'bg-primary'"
            :style="{ left: `${drag.x}px`, top: `${drag.y}px` }"
        >
            {{ drag.label }}
        </div>
    </div>
</template>
