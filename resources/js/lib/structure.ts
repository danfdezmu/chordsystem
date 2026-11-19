export function parseStructure(value: string): string[] {
    const raw = value.trim();

    if (raw === '') {
        return [];
    }

    if (raw.includes(' | ')) {
        return raw.split(' | ').map((part) => part.trim()).filter(Boolean);
    }

    const labels: string[] = [];

    for (const piece of raw.split(/[\s,]+/)) {
        const token = piece.trim();

        if (token === '') {
            continue;
        }

        const previous = labels[labels.length - 1];

        if (/^\d+$/.test(token) && previous && !/\d$/.test(previous)) {
            labels[labels.length - 1] = `${previous} ${token}`;
            continue;
        }

        labels.push(token);
    }

    return labels;
}

export function serializeStructure(labels: string[]): string {
    return labels.join(' | ');
}

const SECTION_ABBR: Record<string, string> = {
    intro: 'I',
    introduction: 'I',
    verso: 'V',
    verse: 'V',
    coro: 'C',
    chorus: 'C',
    'pre coro': 'Pre',
    precoro: 'Pre',
    'pre chorus': 'Pre',
    prechorus: 'Pre',
    puente: 'P',
    bridge: 'P',
    solo: 'S',
    instrumental: 'Inst',
    inst: 'Inst',
    outro: 'Ou',
    final: 'Ending',
    ending: 'Ending',
    interludio: 'Il',
    interlude: 'Il',
};

function sectionKey(label: string): string {
    return label
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z]+/g, ' ')
        .trim()
        .replace(/\s+/g, ' ');
}

export function sectionAbbr(label: string): string {
    const number = label.match(/(\d+)/)?.[1] ?? '';
    const key = sectionKey(label);
    const known = SECTION_ABBR[key];

    if (known) {
        return known + number;
    }

    const letter = key.replace(/[^a-z]/g, '').charAt(0).toUpperCase();

    return (letter || label.trim().charAt(0).toUpperCase()) + number;
}
