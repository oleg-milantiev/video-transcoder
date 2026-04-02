
export function replaceTemplateValue(template, placeholder, value) {
    return template.replace(placeholder, String(value));
}

export function normalizeErrorMessage(error, fallback) {
    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallback;
}

export function extractApiErrorMessage(payload, fallback) {
    if (payload && payload.error && typeof payload.error === 'object' && typeof payload.error.message === 'string') {
        return payload.error.message;
    }

    if (payload && typeof payload.error === 'string') {
        return payload.error;
    }

    return fallback;
}

export async function parseJsonResponse(response) {
    try {
        return await response.json();
    } catch (e) {
        return null;
    }
}

export function secondsToHuman(sec) {
    if (typeof sec !== 'number' || !Number.isFinite(sec)) return '-';
    if (sec < 60) return `${sec} s`;
    const minutes = Math.floor(sec / 60);
    if (minutes < 60) return `${minutes} m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} h`;
    const days = Math.floor(hours / 24);
    return `${days} d`;
}

export function bytesToHuman(bytes) {
    if (typeof bytes !== 'number' || !Number.isFinite(bytes)) return '-';
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value = value / 1024;
        i += 1;
    }
    return `${Math.round(value * 10) / 10} ${units[i]}`;
}

export function megabytesToHuman(mb) {
    if (typeof mb !== 'number' || !Number.isFinite(mb)) return '-';
    if (mb < 1024) return `${mb} MB`;
    const gb = Math.round((mb / 1024) * 10) / 10;
    return `${gb} GB`;
}

const humanReadableDateTimeFormatter = new Intl.DateTimeFormat('en', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

export function humanReadableDateTime(value, fallback = '-') {
    if (typeof value !== 'string' || value.trim() === '') {
        return fallback;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    const parts = humanReadableDateTimeFormatter.formatToParts(date);
    const lookup = Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));

    if (!lookup.day || !lookup.month || !lookup.year || !lookup.hour || !lookup.minute) {
        return fallback;
    }

    return `${lookup.day} ${lookup.month} ${lookup.year} ${lookup.hour}:${lookup.minute}`;
}

