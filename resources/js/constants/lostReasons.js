/**
 * Mirror of App\Modules\CRM\Support\LostReasons.
 *
 * Kept as a constant rather than fetched, because the picker opens inside a
 * dialog the user is already waiting on and a spinner there is the sort of
 * friction that made the old textarea go unfilled in the first place.
 *
 * LostReasonsMirrorTest asserts this file and the PHP list stay identical, so
 * the two cannot drift without the suite saying so.
 */
export const LOST_REASONS = [
    { code: 'price', label: 'Price', hint: 'Cheaper quote elsewhere, or over their budget.', detail_required: false },
    { code: 'competitor', label: 'Went with a competitor', hint: 'Chose another provider. Say who, if you know.', detail_required: true },
    { code: 'in_contract', label: 'Tied into a contract', hint: 'Locked in with their current provider for now.', detail_required: false },
    { code: 'no_response', label: 'Could not reach them', hint: 'Chased and never got a reply.', detail_required: false },
    { code: 'not_interested', label: 'Not interested', hint: 'Told us no. No further detail needed.', detail_required: false },
    { code: 'not_suitable', label: 'Not the right fit', hint: 'We could not do what they needed.', detail_required: false },
    { code: 'timing', label: 'Wrong time', hint: 'Interested, but not now. Worth a callback later.', detail_required: false },
    { code: 'closed_down', label: 'Business closed or sold', hint: 'There is nobody left to sell to.', detail_required: false },
    { code: 'duplicate', label: 'Duplicate or bad data', hint: 'Never a real opportunity.', detail_required: false },
    { code: 'other', label: 'Something else', hint: 'Please say what happened.', detail_required: true },
];

export function lostReasonLabel(code) {
    return LOST_REASONS.find((r) => r.code === code)?.label ?? null;
}

/**
 * Whether the picker has enough to submit. Kept beside the list so every dialog
 * that uses the picker enforces the same rule instead of inventing its own.
 */
export function isLostReasonComplete(code, detail) {
    const reason = LOST_REASONS.find((r) => r.code === code);

    if (! reason) return false;

    return reason.detail_required ? String(detail ?? '').trim() !== '' : true;
}

/** What gets stored in the free-text column; mirrors LostReasons::compose(). */
export function composeLostReason(code, detail) {
    const label = lostReasonLabel(code) ?? 'Not recorded';
    const text = String(detail ?? '').trim();

    return text === '' ? label : `${label} - ${text}`;
}
