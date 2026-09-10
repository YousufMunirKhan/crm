/**
 * Times shown in London, wherever the person reading them is.
 *
 * Timestamps are stored in UTC, which is the only sane way to keep them, and
 * the API hands them over with an offset. Left alone, the browser renders them
 * in whoever's local zone - so the same shift reads as 09:00 to somebody in
 * Birmingham and 13:00 to somebody in Karachi, and neither of them knows which
 * one the other is looking at.
 *
 * This is a UK business. The working day, the shift, the report all mean UK
 * time, so that is what gets shown, and it says so on the dashboard clock.
 */

export const UK_TIMEZONE = 'Europe/London';

const parse = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
};

/** 9:05 AM */
export function ukTime(value) {
    const date = parse(value);
    if (!date) return '';

    return date.toLocaleTimeString('en-GB', {
        timeZone: UK_TIMEZONE,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

/** 10 Sep 2026 */
export function ukDate(value) {
    const date = parse(value);
    if (!date) return '';

    return date.toLocaleDateString('en-GB', {
        timeZone: UK_TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** 10 Sep 2026, 9:05 AM */
export function ukDateTime(value) {
    const date = parse(value);
    if (!date) return '';

    return `${ukDate(date)}, ${ukTime(date)}`;
}

/**
 * The label the clock carries - BST for half the year, GMT for the other half.
 * Worth showing: it is the difference between the reader trusting the number
 * and wondering whether somebody forgot the clocks went back.
 */
export function ukZoneLabel(value = new Date()) {
    const date = parse(value) ?? new Date();

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: UK_TIMEZONE,
        timeZoneName: 'short',
    }).formatToParts(date);

    return parts.find((part) => part.type === 'timeZoneName')?.value ?? '';
}
