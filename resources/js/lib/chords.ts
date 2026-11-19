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

function transposeAligned(line: string, semitones: number): string {
    const items: { token: string; col: number }[] = [];
    const regex = /\S+/g;
    let match: RegExpExecArray | null;
    while ((match = regex.exec(line)) !== null) {
        const token = isChordToken(match[0]) ? transposeChord(match[0], semitones) : match[0];
        items.push({ token, col: match.index });
    }
    if (items.length === 0) {
        return line;
    }

    let width = 0;
    for (const item of items) {
        width = Math.max(width, item.col + item.token.length);
    }
    const chars: string[] = new Array(width).fill(' ');
    for (const item of items) {
        for (let i = 0; i < item.token.length; i++) {
            chars[item.col + i] = item.token[i];
        }
    }

    return chars.join('').trimEnd();
}

export function transposeText(text: string, semitones: number): string {
    if (semitones === 0) return text;

    const lines = text.split('\n');
    const out: string[] = [];

    for (const line of lines) {
        if (/^\s*\{unisono\}\s*$/.test(line)) {
            out.push(line);
            continue;
        }
        const inlineUnison = line.match(/^(\s*\{unisono\})(.*)$/);
        if (inlineUnison && inlineUnison[2].trim() !== '') {
            out.push(inlineUnison[1] + transposeAligned(inlineUnison[2], semitones));
            continue;
        }
        const tokens = line.split(/(\s+)/);
        const nonSpace = tokens.filter((t) => t.trim() !== '');
        const allChords = nonSpace.length > 0 && nonSpace.every(isChordToken);
        if (allChords) {
            out.push(transposeAligned(line, semitones));
            continue;
        }
        // transpose bracketed chords
        out.push(line.replace(/\[([^\]]+)\]/g, (_m, inner) => {
            const chord = inner.trim();
            if (isChordToken(chord)) {
                return '[' + transposeChord(chord, semitones) + ']';
            }
            return _m;
        }));
    }

    return out.join('\n');
}