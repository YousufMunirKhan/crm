/**
 * US-style numeric dates for reports (MM/DD/YYYY). No lowercase format letters in output.
 */
export function formatDateUsDisplay(iso) {
    if (iso == null || iso === '') return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
    });
}

/**
 * Describes a due date the way someone triaging a list needs to read it:
 * how urgent, in as few words as possible.
 *
 * Returns null when there is no date, so callers can render their own
 * "nothing scheduled" treatment rather than a misleading em dash.
 *
 * @returns {{label: string, tone: 'overdue'|'today'|'soon'|'later'}|null}
 */
export function describeDueDate(iso) {
    if (iso == null || iso === '') return null;
    const due = new Date(iso);
    if (Number.isNaN(due.getTime())) return null;

    // Compare whole days in local time; a 9am and a 5pm task are both "today".
    const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const days = Math.round((startOfDay(due) - startOfDay(new Date())) / 86400000);

    if (days < 0) {
        const n = Math.abs(days);
        return { label: n === 1 ? 'Yesterday' : `${n} days overdue`, tone: 'overdue' };
    }
    if (days === 0) return { label: 'Today', tone: 'today' };
    if (days === 1) return { label: 'Tomorrow', tone: 'soon' };
    if (days <= 7) return { label: `In ${days} days`, tone: 'soon' };

    return {
        label: due.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        tone: 'later',
    };
}

/**
 * US-style date + time (MM/DD/YYYY, h:mm:ss AM/PM). Meridiem is uppercase.
 */
export function formatDateTimeUsDisplay(iso) {
    if (iso == null || iso === '') return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';
    const s = d.toLocaleString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
    return s.replace(/\b(am|pm)\b/gi, (m) => m.toUpperCase());
}
