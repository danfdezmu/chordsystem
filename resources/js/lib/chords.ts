const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

function normalizeRoot(root: string): string {
    const map: Record<string, string> = {
        'Ab': 'G#', 'Bb': 'A#', 'Cb': 'B', 'Db': 'C#',
        'Eb': 'D#', 'Fb': 'E', 'Gb': 'F#',
    };
    return map[root] ?? root;
}

const CHORD_TOKEN_RE = /^[A-G][#b]?(m|maj|min|dim|aug|sus|add|7|9|11|13|6|4|2|5|b|#|\+|-|°|ø)* (\/[A-G][#b]?)?$/;

export function normalizeChordSymbol(value: string): string {
    return value
        .trim()
        .replace(/\s*\/\s*/g, '/')
        .replace(/(^|\/)([A-Ga-g])([#b]?)/g, (_match, slash: string, letter: string, accidental: string) => {
            return slash + letter.toUpperCase() + accidental;
        });
}

export function isChordToken(tok: string): boolean {
    const t = normalizeChordSymbol(tok);
    if (!t) return false;
    if (!/^[A-G]/.test(t)) return false;
    return /^[A-G][#b]?(m(aj)?|min|dim|aug|sus|add)?[0-9]*(b[0-9]+|#?[0-9]+)?(\+[0-9]+)?(\/[A-G][#b]?)?$/.test(t);
}

export function splitChordToken(token: string): { chord: string; rest: string; variation: boolean } | null {
    let variation = false;
    let source = token;
    if (source.endsWith('*')) {
        variation = true;
        source = source.slice(0, -1);
    }

    const match = source.match(/^([A-G][^.,;:|]*)([.,;:|]*)$/);
    if (!match || !isChordToken(match[1])) {
        return null;
    }

    return { chord: match[1], rest: match[2], variation };
}

export function isChordLine(line: string): boolean {
    const tokens = line.trim().split(/\s+/).filter(Boolean);

    return tokens.length > 0 && tokens.every((token) => splitChordToken(token) !== null);
}

function rootOf(key: string): string {
    const match = key.trim().match(/^([A-G][#b]?)/);
    if (!match) {
        return '';
    }
    return normalizeRoot(match[1]);
}

export function semitonesBetween(fromKey: string, toKey: string): number {
    const from = NOTES.indexOf(rootOf(fromKey));
    const to = NOTES.indexOf(rootOf(toKey));
    if (from === -1 || to === -1) {
        return 0;
    }
    return (to - from + 12) % 12;
}

export function transposeChord(chord: string, semitones: number): string {
    const slashIndex = chord.indexOf('/');
    let main = chord;
    let bass = '';
    if (slashIndex !== -1) {
        main = chord.slice(0, slashIndex);
        bass = chord.slice(slashIndex);
    }

    const match = main.match(/^([A-G][#b]?)(.*)$/);
    if (!match) return chord;
    const root = normalizeRoot(match[1]);
    const suffix = match[2];
    const idx = NOTES.indexOf(root);
    if (idx === -1) return chord;
    const newIdx = ((idx + semitones) % 12 + 12) % 12;
    const newRoot = NOTES[newIdx];

    let newBass = bass;
    if (bass) {
        const bassMatch = bass.match(/^\/([A-G][#b]?)(.*)$/);
        if (bassMatch) {
            const bassRoot = normalizeRoot(bassMatch[1]);
            const bi = NOTES.indexOf(bassRoot);
            if (bi !== -1) {
                const newBi = ((bi + semitones) % 12 + 12) % 12;
                newBass = '/' + NOTES[newBi] + bassMatch[2];
            }
        }
    }

    return newRoot + suffix + newBass;
}

function transposeAligned(line: string, semitones: number): { line: string; insertions: { index: number; extra: number }[] } {
    const raw: { token: string; col: number }[] = [];
    const regex = /\S+/g;
    let match: RegExpExecArray | null;
    while ((match = regex.exec(line)) !== null) {
        const parts = splitChordToken(match[0]);
        const token = parts
            ? transposeChord(parts.chord, semitones) + parts.rest + (parts.variation ? '*' : '')
            : match[0];
        raw.push({ token, col: match.index });
    }
    if (raw.length === 0) {
        return { line, insertions: [] };
    }

    const insertions: { index: number; extra: number }[] = [];
    const placed: { token: string; col: number }[] = [];
    let shift = 0;
    let lastEnd = -1;
    for (const item of raw) {
        let col = item.col + shift;
        if (lastEnd >= 0 && col < lastEnd + 2) {
            const extra = lastEnd + 2 - col;
            insertions.push({ index: item.col, extra });
            col += extra;
            shift += extra;
        }
        placed.push({ token: item.token, col });
        lastEnd = col + item.token.length - 1;
    }

    let width = 0;
    for (const item of placed) {
        width = Math.max(width, item.col + item.token.length);
    }
    const chars: string[] = new Array(width).fill(' ');
    for (const item of placed) {
        for (let i = 0; i < item.token.length; i++) {
            chars[item.col + i] = item.token[i];
        }
    }

    return { line: chars.join('').trimEnd(), insertions };
}

function applyInsertions(line: string, insertions: { index: number; extra: number }[]): string {
    if (insertions.length === 0) {
        return line;
    }

    let result = line;
    let delta = 0;
    for (const insertion of insertions) {
        const at = insertion.index + delta;
        const pad = ' '.repeat(insertion.extra);
        result = at >= result.length
            ? result.padEnd(at, ' ') + pad
            : result.slice(0, at) + pad + result.slice(at);
        delta += insertion.extra;
    }

    return result;
}

export function transposeText(text: string, semitones: number): string {
    if (semitones === 0) return text;

    const lines = text.split('\n');
    const out: string[] = [];
    let pending: { index: number; extra: number }[] = [];

    for (const line of lines) {
        if (line.trim() === '' || /^\s*\[[^\]]+\]\s*$/.test(line) || /^\s*\{unisono\}\s*$/.test(line)) {
            pending = [];
            out.push(line);
            continue;
        }
        const inlineUnison = line.match(/^(\s*\{unisono\*?\})(.*)$/);
        if (inlineUnison && inlineUnison[2].trim() !== '') {
            const moved = transposeAligned(inlineUnison[2], semitones);
            out.push(inlineUnison[1] + moved.line);
            pending = moved.insertions.map((insertion) => ({
                index: insertion.index + inlineUnison[1].length,
                extra: insertion.extra,
            }));
            continue;
        }
        const writtenUnison = line.match(/^(\s*\[(?:unisono|unísono)\*?\]\s*\{)([^}]*)(\}\s*)$/i);
        if (writtenUnison) {
            const moved = transposeAligned(writtenUnison[2], semitones);
            out.push(writtenUnison[1] + moved.line + writtenUnison[3]);
            pending = [];
            continue;
        }
        if (isChordLine(line)) {
            const moved = transposeAligned(applyInsertions(line, pending), semitones);
            out.push(moved.line);
            pending = [...pending, ...moved.insertions];
            continue;
        }
        const lyric = applyInsertions(line, pending);
        out.push(lyric.replace(/\[([^\]]+)\]/g, (_m, inner) => {
            const chord = inner.trim();
            if (isChordToken(chord)) {
                return '[' + transposeChord(chord, semitones) + ']';
            }
            return _m;
        }));
    }

    return out.join('\n');
}