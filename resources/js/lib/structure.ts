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

export function sectionAbbr(label: string): string {
    const number = label.match(/(\d+)/);
    const letter = label.replace(/[^a-zA-ZáéíóúñÁÉÍÓÚÑ]/g, '').charAt(0).toUpperCase();

    return (letter || label.charAt(0).toUpperCase()) + (number ? number[1] : '');
}
