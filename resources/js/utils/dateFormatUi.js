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
