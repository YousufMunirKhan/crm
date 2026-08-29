/**
 * One way to write money.
 *
 * Reports were printing "GBP 2,652.8" - a currency code instead of a symbol,
 * and a trailing pence digit dropped - while the rest of the app printed
 * "£2,652.80". Two renderings of the same figure on the same screen is how a
 * report stops being trusted.
 */
const gbp = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatMoney(value) {
    return gbp.format(Number(value) || 0);
}

/**
 * Whole pounds, for axis labels and dense tables where the pence are noise.
 * "£1.2k" past a thousand, so a column of figures stays scannable.
 */
export function formatMoneyShort(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);

    if (abs >= 1_000_000) return `£${(n / 1_000_000).toFixed(1)}m`;
    if (abs >= 1_000) return `£${(n / 1_000).toFixed(1)}k`;

    return `£${Math.round(n)}`;
}
