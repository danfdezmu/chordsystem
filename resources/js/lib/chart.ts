import { isChordLine, splitChordToken, transposeText } from '@/lib/chords';

function escapeHtml(s: string): string {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

const CHORD_CLASS = 'text-blue-600 dark:text-blue-400';
const ADORNMENT_CLASS = 'text-emerald-600 dark:text-emerald-400';
const UNISON_CLASS = 'text-red-600 dark:text-red-400';

export type ParsedSection = { label: string; body: string };

export function sectionHeader(line: string): string | null {
    const match = line.trim().match(/^\[(.+)\]$/);
    if (!match) {
        return null;
    }

    const label = match[1].trim();
    if (/^(unisono|unísono)$/i.test(label)) {
        return null;
    }

    return label;
}

export function parseSections(text: string): ParsedSection[] {
    const lines = text.split('\n');
    const result: ParsedSection[] = [];
    let current: ParsedSection | null = null;
    for (const line of lines) {
        const label = sectionHeader(line);
        if (label) {
            if (current) result.push(current);
            current = { label, body: '' };
            continue;
        }
        if (!current) {
            current = { label: 'Sección', body: '' };
        }
        current.body += (current.body ? '\n' : '') + line;
    }
    if (current) result.push(current);
    return result.filter((s) => s.body.trim() !== '' || s.label !== 'Sección');
}

function normalizeLabel(s: string): string {
    return s.toLowerCase().replace(/[^a-z0-9áéíóúñ]/gi, '');
}

export function findSection(sections: ParsedSection[], token: string): ParsedSection | undefined {
    const t = normalizeLabel(token.replace(/[\[\]]/g, ''));
    // alias support
    const aliases: Record<string, string> = {
        v1: 'verso1', v2: 'verso2', v3: 'verso3',
        c: 'coro', p: 'puente', i: 'intro', o: 'outro',
    };
    const key = aliases[t] ?? t;
    return sections.find((s) => normalizeLabel(s.label) === key);
}

export function renderSection(body: string, semitones: number, lyricsOnly = false): string {
    return renderBody(body, semitones, lyricsOnly);
}

const REPEAT_CLASS =
    'ml-1 inline-block rounded px-1.5 py-0.5 text-xs font-bold text-amber-700 bg-amber-400/30 dark:text-amber-300';

export function groupConsecutiveLabels(labels: string[]): { label: string; count: number }[] {
    const groups: { label: string; count: number }[] = [];

    for (const label of labels) {
        const last = groups[groups.length - 1];
        if (last && normalizeLabel(last.label) === normalizeLabel(label)) {
            last.count += 1;
            continue;
        }
        groups.push({ label, count: 1 });
    }

    return groups;
}

function repeatBadge(count: number): string {
    if (count < 2) {
        return '';
    }

    return `<span class="${REPEAT_CLASS}">x${count}</span>`;
}

function sectionBadge(label: string, count: number, index: number): string {
    return `<div id="section-${index}" class="mt-4 mb-1 scroll-mt-20"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold ${sectionColorClass(label)}">${escapeHtml(label)}</span>${repeatBadge(count)}</div>`;
}

export function sectionColorClass(label: string): string {
    const l = (label || '').toLowerCase();
    if (l.includes('intro')) return 'bg-fuchsia-500/20 text-fuchsia-800 dark:text-fuchsia-300';
    if (l.includes('verso') || l.includes('verse')) return 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-300';
    if (l.includes('coro') || l.includes('chorus')) return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    if (l.includes('puente') || l.includes('bridge')) return 'bg-purple-500/15 text-purple-700 dark:text-purple-300';
    if (l.includes('outro') || l.includes('final') || l.includes('ending')) return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
    if (l.includes('solo')) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    if (l.includes('interludio')) return 'bg-teal-500/15 text-teal-700 dark:text-teal-300';
    if (l.includes('espont')) return 'bg-rose-500/15 text-rose-700 dark:text-rose-300';
    return 'bg-primary/10 text-primary';
}

function renderMarkedLine(line: string, noteClass: string): string {
    const tokens = line.split(/(\s+)/);
    const colored = tokens
        .map((token) => {
            if (token.trim() === '') {
                return token;
            }
            const parts = splitChordToken(token.trim());
            if (!parts) {
                return escapeHtml(token);
            }
            return `<span class="${noteClass}">${escapeHtml(parts.chord)}</span>${escapeHtml(parts.rest)}`;
        })
        .join('');

    return `<div class="chart-line whitespace-pre">${colored || '&nbsp;'}</div>`;
}

function renderLyricLine(line: string): string {
    const text = line
        .replace(/\{[^}]*\}/g, '')
        .replace(/\[[^\]]*\]/g, '')
        .replace(/[ \t]+/g, ' ')
        .trim();
    if (!text) {
        return '';
    }

    return `<div class="chart-line">${escapeHtml(text)}</div>`;
}

export function renderStructure(content: string, tokens: string[], semitones: number, lyricsOnly = false): string {
    const sections = parseSections(content);
    const groups = groupConsecutiveLabels(tokens);
    const blocks = groups.map((group, index) => {
        const section = findSection(sections, group.label);
        if (!section) {
            return `<div id="section-${index}" class="mt-4 mb-1 scroll-mt-20"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-300">${escapeHtml(group.label)} (no encontrado)</span></div>`;
        }

        return sectionBadge(group.label, group.count, index) + renderSection(section.body, semitones, lyricsOnly);
    });

    return blocks.join('') || renderChart(content, semitones, lyricsOnly);
}

export function renderChart(text: string, semitones: number, lyricsOnly = false): string {
    const sections = parseSections(text);
    const hasHeader = sections.some((section) => section.label !== 'Sección');
    if (!hasHeader) {
        return renderBody(text, semitones, lyricsOnly);
    }

    const groups: { label: string; body: string; count: number }[] = [];
    for (const section of sections) {
        const last = groups[groups.length - 1];
        const sameLabel = last && normalizeLabel(last.label) === normalizeLabel(section.label);
        const sameBody = last && last.body.trim() === section.body.trim();
        if (last && sameLabel && sameBody) {
            last.count += 1;
            continue;
        }
        groups.push({ label: section.label, body: section.body, count: 1 });
    }

    return groups.map((group, index) => {
        const body = renderBody(group.body, semitones, lyricsOnly);
        if (group.label === 'Sección') {
            return body;
        }

        return sectionBadge(group.label, group.count, index) + body;
    }).join('');
}

function renderBody(text: string, semitones: number, lyricsOnly = false): string {
    const transposed = transposeText(text, semitones);
    const lines = transposed.split('\n');
    const out: string[] = [];
    let sectionIndex = 0;
    let pendingUnison = false;

    for (const rawLine of lines) {
        const line = rawLine.trimEnd();
        if (line.trim() === '{unisono}') {
            pendingUnison = true;
            continue;
        }

        const writtenUnison = line.match(/^\s*\[(?:unisono|unísono)\]\s*\{([^}]*)\}\s*$/i);
        if (writtenUnison) {
            pendingUnison = false;
            if (!lyricsOnly && writtenUnison[1].trim() !== '') {
                out.push(renderMarkedLine(writtenUnison[1], UNISON_CLASS));
            }
            continue;
        }

        if (/^\s*\[(?:unisono|unísono)\]\s*$/i.test(line)) {
            pendingUnison = false;
            continue;
        }

        const header = sectionHeader(line);
        if (header) {
            pendingUnison = false;
            const label = escapeHtml(header);
            out.push(
                `<div id="section-${sectionIndex}" class="mt-4 mb-1 scroll-mt-20"><span class="inline-block rounded px-2 py-0.5 text-xs font-semibold ${sectionColorClass(header)}">${label}</span></div>`,
            );
            sectionIndex++;
            continue;
        }

        if (pendingUnison) {
            pendingUnison = false;
            if (!lyricsOnly) {
                out.push(renderMarkedLine(line, UNISON_CLASS));
            }
            continue;
        }

        const inlineUnison = line.match(/^(\s*)\{unisono\}(.*)$/);
        if (inlineUnison && inlineUnison[2].trim() !== '') {
            if (!lyricsOnly) {
                out.push(renderMarkedLine(inlineUnison[2], UNISON_CLASS));
            }
            continue;
        }

        if (isChordLine(line)) {
            if (!lyricsOnly) {
                out.push(renderMarkedLine(line, CHORD_CLASS));
            }
            continue;
        }

        if (lyricsOnly) {
            if (line.trim() === '') {
                out.push('<div class="h-3"></div>');
            } else {
                const lyric = renderLyricLine(line);
                if (lyric) {
                    out.push(lyric);
                }
            }
            continue;
        }

        let escaped = escapeHtml(line);
        // Adornments {unisono}, {solo}, {riff}, etc.
        escaped = escaped.replace(/\{([^}]+)\}/g, (_m, tag) => {
            return `<span class="${ADORNMENT_CLASS}">{${escapeHtml(tag)}}</span>`;
        });
        // Chords [C], [Gm7], etc.
        escaped = escaped.replace(/\[([^\]]+)\]/g, (_m, chord) => {
            return `<span class="${CHORD_CLASS}">[${escapeHtml(chord)}]</span>`;
        });

        out.push(`<div class="chart-line whitespace-pre">${escaped || '&nbsp;'}</div>`);
    }

    return out.join('');
}