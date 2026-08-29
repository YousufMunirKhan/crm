/**
 * How a customer is named on screen.
 *
 * A customer is a person at a company, and the app used to pick one or the
 * other and drop the rest: lists printed the contact name with no sign of the
 * business, commission tables printed `business_name ?: name` so the contact
 * vanished, and the command palette did the same. Someone who knows an account
 * by its trading name could not find their own pipeline. One definition here,
 * two renderings - stacked for tables, inline for dense digests.
 *
 * Records reach these functions in two shapes: a real customer
 * (`name` / `business_name`) or a flattened report row
 * (`customer_name` / `customer_business_name`). Both are accepted.
 */

function pick(record, ...keys) {
    for (const key of keys) {
        const value = record?.[key];
        if (typeof value === 'string' && value.trim()) return value.trim();
    }
    return '';
}

/** The contact, falling back to the company so a row is never just a dash. */
export function contactName(record, fallback = '—') {
    return pick(record, 'name', 'customer_name')
        || pick(record, 'business_name', 'customer_business_name')
        || fallback;
}

/** The company, empty when it would only repeat the line above it. */
export function companyName(record) {
    const company = pick(record, 'business_name', 'customer_business_name');

    return company && company !== contactName(record, '') ? company : '';
}

/** "Jane Smith · Bright Star Ltd" - for one-line contexts. */
export function customerLabel(record, fallback = '—') {
    const contact = contactName(record, fallback);
    const company = companyName(record);

    return company ? `${contact} · ${company}` : contact;
}
