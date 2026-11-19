<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { sectionColorClass } from '@/lib/chart';
import ChordDragEditor from '@/components/ChordDragEditor.vue';

const props = defineProps<{ modelValue: string; keyName?: string; mode?: 'major' | 'minor' }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

const sectionTypes = ['Intro', 'Verso', 'Coro', 'Puente', 'Outro', 'Solo', 'Interludio', 'Espontáneo'];

type Section = { type: string; body: string };

function parseContent(content: string): Section[] {
    const lines = content.split('\n');
    const result: Section[] = [];
    let current: Section | null = null;
    for (const raw of lines) {
        const line = raw.trimEnd();
        const m = line.trim().match(/^\[(.+)\]$/);
        if (m) {
            if (current) result.push(current);
            current = { type: m[1], body: '' };
            continue;
        }
        if (!current) current = { type: 'Verso', body: '' };
        current.body += (current.body ? '\n' : '') + raw;
    }
    if (current) result.push(current);
    if (result.length === 0) result.push({ type: 'Verso', body: '' });
    return result;
}

const sections = ref<Section[]>(parseContent(props.modelValue || ''));

function serialize(list: Section[]): string {
    return list
        .map((s) => `[${s.type}]\n${s.body.trimEnd()}`)
        .join('\n\n')
        .trim();
}

watch(sections, (v) => {
    emit('update:modelValue', serialize(v));
}, { deep: true });

watch(() => props.modelValue, (v) => {
    if (serialize(sections.value) !== (v || '')) {
        sections.value = parseContent(v || '');
    }
});

function baseType(t: string): string {
    return t.replace(/\s*\d+\s*$/, '').trim() || t;
}

function nextNumber(base: string): number {
    const count = sections.value.filter((s) => baseType(s.type) === base).length;
    return count + 1;
}

function addSectionOfType(t: string) {
    const base = baseType(t);
    const n = nextNumber(base);
    sections.value.push({ type: `${base} ${n}`, body: '' });
}

function changeType(sec: { type: string; body: string }, newBase: string) {
    const base = baseType(newBase);
    const others = sections.value.filter((s) => s !== sec && baseType(s.type) === base).length;
    sec.type = `${base} ${others + 1}`;
}
function removeSection(i: number) {
    sections.value.splice(i, 1);
    if (sections.value.length === 0) sections.value.push({ type: 'Verso', body: '' });
}
function moveSection(i: number, dir: -1 | 1) {
    const ni = i + dir;
    if (ni < 0 || ni >= sections.value.length) return;
    const tmp = sections.value[i];
    sections.value[i] = sections.value[ni];
    sections.value[ni] = tmp;
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="(sec, si) in sections" :key="si" class="rounded-lg border p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <Label>Tipo</Label>
                <span class="rounded-md bg-primary/10 px-3 py-2 text-sm font-bold text-primary">
                    {{ sec.type }}
                </span>
                <Select :model-value="baseType(sec.type)" @update:model-value="changeType(sec, $event as string)">
                    <SelectTrigger class="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in sectionTypes" :key="t" :value="t">{{ t }}</SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex gap-1 ml-auto">
                    <Button type="button" size="sm" variant="ghost" @click="moveSection(si, -1)">↑</Button>
                    <Button type="button" size="sm" variant="ghost" @click="moveSection(si, 1)">↓</Button>
                    <Button type="button" size="sm" variant="destructive" @click="removeSection(si)">Eliminar</Button>
                </div>
            </div>

            <ChordDragEditor v-model="sections[si].body" :key-name="props.keyName" :mode="props.mode" />
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="t in sectionTypes"
                :key="t"
                type="button"
                :class="['flex size-9 items-center justify-center rounded-full text-xs font-bold hover:opacity-80', sectionColorClass(t)]"
                :title="`Agregar sección ${t}`"
                @click="addSectionOfType(t)"
            >
                {{ t.charAt(0).toUpperCase() }}{{ t.match(/\d/) ? t.match(/\d/)?.[0] : '' }}
            </button>
        </div>
    </div>
</template>