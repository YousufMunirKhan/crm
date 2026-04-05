/**
 * User-facing labels: consistent capitalization for stages, statuses, and enums.
 */

export function capitalizeWords(str) {
    if (str == null || str === '') return '';
    const s = String(str).trim();
    if (!s) return '';
    return s
        .split(/[\s_-]+/)
        .filter(Boolean)
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
        .join(' ');
}

const LEAD_STAGE_LABELS = {
    follow_up: 'Follow-up',
    lead: 'Lead',
    hot_lead: 'Hot lead',
    quotation: 'Quotation',
    won: 'Won',
    lost: 'Lost',
};

/**
 * @param {string|null|undefined} stage
 * @param {string} whenEmpty — e.g. '—', '-', or ''
 */
export function formatLeadStage(stage, whenEmpty = '—') {
    if (stage == null || stage === '') return whenEmpty;
    return LEAD_STAGE_LABELS[stage] || capitalizeWords(String(stage).replace(/_/g, ' '));
}

const LINE_ITEM_LABELS = {
    pending: 'Pending',
    won: 'Won',
    lost: 'Lost',
};

export function formatLineItemStatus(status, whenEmpty = 'Pending') {
    if (status == null || status === '') return whenEmpty;
    return LINE_ITEM_LABELS[status] || capitalizeWords(String(status).replace(/_/g, ' '));
}

const COMM_LOG_LABELS = {
    sent: 'Sent',
    failed: 'Failed',
    pending: 'Pending',
    delivered: 'Delivered',
    queued: 'Queued',
    processing: 'Processing',
    submitted: 'Submitted',
    bounced: 'Bounced',
    opened: 'Opened',
    clicked: 'Clicked',
    draft: 'Draft',
    received: 'Received',
};

export function formatCommLogStatus(status, whenEmpty = '—') {
    if (status == null || status === '') return whenEmpty;
    const key = String(status).toLowerCase();
    return COMM_LOG_LABELS[key] || capitalizeWords(String(status).replace(/_/g, ' '));
}

const TICKET_STATUS_LABELS = {
    open: 'Open',
    in_progress: 'Working',
    on_hold: 'On Hold',
    resolved: 'Resolved',
    closed: 'Closed',
};

export function formatTicketStatus(status, whenEmpty = '—') {
    if (status == null || status === '') return whenEmpty;
    return TICKET_STATUS_LABELS[status] || capitalizeWords(String(status).replace(/_/g, ' '));
}

const INVOICE_STATUS_LABELS = {
    draft: 'Draft',
    sent: 'Sent',
    partially_paid: 'Partially Paid',
    paid: 'Paid',
    overdue: 'Overdue',
};

export function formatInvoiceStatus(status, whenEmpty = '—') {
    if (status == null || status === '') return whenEmpty;
    return INVOICE_STATUS_LABELS[status] || capitalizeWords(String(status).replace(/_/g, ' '));
}

/** WhatsApp / API enums often arrive as UPPER_SNAKE or lowercase. */
export function formatApiEnumLabel(value, whenEmpty = '—') {
    if (value == null || value === '') return whenEmpty;
    return capitalizeWords(String(value).replace(/_/g, ' '));
}

const CAMPAIGN_STATUS_LABELS = {
    draft: 'Draft',
    scheduled: 'Scheduled',
    sending: 'Sending',
    completed: 'Completed',
    failed: 'Failed',
    cancelled: 'Cancelled',
    canceled: 'Cancelled',
    paused: 'Paused',
    pending: 'Pending',
};

export function formatCampaignStatus(status, whenEmpty = '—') {
    if (status == null || status === '') return whenEmpty;
    const key = String(status).toLowerCase();
    return CAMPAIGN_STATUS_LABELS[key] || capitalizeWords(String(status).replace(/_/g, ' '));
}
