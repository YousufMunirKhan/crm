import { UK_TIMEZONE } from '@/utils/datetime';

/**
 * What each status is called and how it is coloured, for the screen and for the
 * picture of it. One map, so the board somebody reads in the app and the one
 * forwarded to the group cannot describe the same person two ways.
 */
export const LEADERBOARD_STATUS = {
    achieved: { label: 'Target hit', tone: 'success', text: '#065f46', fill: '#d1fae5' },
    on_track: { label: 'On track', tone: 'success', text: '#065f46', fill: '#ecfdf5' },
    behind: { label: 'Behind', tone: 'warning', text: '#92400e', fill: '#fef3c7' },
    at_risk: { label: 'At risk', tone: 'danger', text: '#991b1b', fill: '#fee2e2' },
    in_progress: { label: 'In progress', tone: 'primary', text: '#1e40af', fill: '#dbeafe' },
    not_started: { label: 'Not started', tone: 'neutral', text: '#475569', fill: '#f1f5f9' },
};

export const PERIOD_NOUN = { today: 'today', week: 'this week', month: 'this month' };

// The theme's own palette (resources/css/app.css), since a canvas cannot read it.
const C = {
    primary50: '#eff6ff',
    primary100: '#dbeafe',
    primary400: '#60a5fa',
    primary600: '#2563eb',
    primary700: '#1d4ed8',
    primary800: '#1e40af',
    slate50: '#f8fafc',
    slate100: '#f1f5f9',
    slate200: '#e2e8f0',
    slate400: '#94a3b8',
    slate500: '#64748b',
    slate900: '#0f172a',
    white: '#ffffff',
};

const MEDALS = { 1: '#f59e0b', 2: '#94a3b8', 3: '#b45309' };
const FONT = "'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";

const WIDTH = 1080;
const PAD = 48;
const HEADER = 236;
const TILE = 150;
const ROW = 112;

/**
 * Draw the board as a picture to post in the group chat.
 *
 * Drawn by hand rather than photographed off the page: the screen is laid out
 * for whoever is looking at it - their own row marked, a table that scrolls on
 * a phone - and the picture is for people who are not. This way it is always
 * the same width and always whole, whatever device it was made on.
 *
 * @returns {Promise<Blob>} a PNG
 */
export function renderLeaderboardImage(board, { companyName = '' } = {}) {
    const rows = board.rows ?? [];
    const tiles = summaryTiles(board);

    const tilesHeight = tiles.length ? TILE + 28 : 0;
    const boardHeight = rows.length ? 64 + rows.length * ROW + 16 : 200;
    const height = HEADER + 28 + tilesHeight + boardHeight + 96;

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'alphabetic';

    ctx.fillStyle = C.slate100;
    ctx.fillRect(0, 0, WIDTH, height);

    drawHeader(ctx, board, companyName);

    let y = HEADER + 28;

    if (tiles.length) {
        drawTiles(ctx, tiles, y);
        y += tilesHeight;
    }

    drawBoard(ctx, board, rows, y, boardHeight);

    ctx.textAlign = 'center';
    text(ctx, `Only people with a target set for ${board.month_label} are listed`, WIDTH / 2, height - 44, `500 22px ${FONT}`, C.slate500);

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not draw the image.'))), 'image/png');
    });
}

function summaryTiles(board) {
    const s = board.summary ?? {};
    const tiles = [];

    if (s.leads_today?.target > 0) {
        tiles.push({ label: 'Leads today', ...s.leads_today });
    }
    if (board.period !== 'today' && s.leads_period?.target > 0) {
        tiles.push({ label: `Leads ${PERIOD_NOUN[board.period]}`, ...s.leads_period });
    }
    if (s.appointments_month?.target > 0) {
        tiles.push({ label: 'Appointments this month', ...s.appointments_month, alt: true });
    }

    return tiles;
}

function drawHeader(ctx, board, companyName) {
    const gradient = ctx.createLinearGradient(0, 0, WIDTH, HEADER);
    gradient.addColorStop(0, C.primary600);
    gradient.addColorStop(1, C.primary800);
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, WIDTH, HEADER);

    ctx.textAlign = 'left';
    text(ctx, (companyName || 'Sales team').toUpperCase(), PAD, 70, `700 24px ${FONT}`, C.primary100, 760);
    text(ctx, 'Sales Leaderboard', PAD, 138, `700 62px ${FONT}`, C.white);

    const asOf = new Date().toLocaleTimeString('en-GB', { timeZone: UK_TIMEZONE, hour: '2-digit', minute: '2-digit', hour12: false });
    const when = board.period === 'today'
        ? `${board.today_label}  ·  as of ${asOf}`
        : `${board.period === 'month' ? 'Month' : 'Week'} of ${board.range.label}  ·  as of ${asOf}`;
    text(ctx, when, PAD, 190, `500 28px ${FONT}`, C.primary100, 760);

    // How many are on the board, in the corner the eye lands on last.
    ctx.fillStyle = 'rgba(255, 255, 255, 0.14)';
    roundRect(ctx, WIDTH - PAD - 168, 56, 168, 124, 24);
    ctx.fill();
    ctx.textAlign = 'center';
    text(ctx, String(board.summary?.members ?? 0), WIDTH - PAD - 84, 126, `700 60px ${FONT}`, C.white);
    text(ctx, 'ON THE BOARD', WIDTH - PAD - 84, 160, `600 17px ${FONT}`, C.primary100);
}

function drawTiles(ctx, tiles, y) {
    const gap = 20;
    const width = (WIDTH - PAD * 2 - gap * (tiles.length - 1)) / tiles.length;

    tiles.forEach((tile, i) => {
        const x = PAD + i * (width + gap);

        card(ctx, x, y, width, TILE);

        ctx.textAlign = 'left';
        text(ctx, tile.label.toUpperCase(), x + 24, y + 40, `600 19px ${FONT}`, C.slate500, width - 48);

        const done = String(tile.done);
        text(ctx, done, x + 24, y + 92, `700 46px ${FONT}`, C.slate900);
        ctx.font = `700 46px ${FONT}`;
        const doneWidth = ctx.measureText(done).width;
        text(ctx, `/ ${tile.target}`, x + 24 + doneWidth + 10, y + 92, `500 26px ${FONT}`, C.slate500);

        ctx.textAlign = 'right';
        text(ctx, `${tile.percent}%`, x + width - 24, y + 92, `700 28px ${FONT}`, C.primary700);

        bar(ctx, x + 24, y + 114, width - 48, 12, tile.percent, tile.alt ? C.primary400 : C.primary600);
    });
}

function drawBoard(ctx, board, rows, y, height) {
    const x = PAD;
    const width = WIDTH - PAD * 2;

    card(ctx, x, y, width, height);

    if (! rows.length) {
        ctx.textAlign = 'center';
        text(ctx, `Nobody has a target set for ${board.month_label} yet`, WIDTH / 2, y + height / 2 + 10, `500 28px ${FONT}`, C.slate500);

        return;
    }

    const leadsX = x + 470;
    const appointmentsX = x + 730;
    const columnWidth = 220;

    ctx.textAlign = 'left';
    text(ctx, '#', x + 44, y + 42, `600 18px ${FONT}`, C.slate500);
    text(ctx, 'NAME', x + 104, y + 42, `600 18px ${FONT}`, C.slate500);
    text(ctx, `LEADS · ${board.period.toUpperCase()}`, leadsX, y + 42, `600 18px ${FONT}`, C.slate500);
    text(ctx, 'APPOINTMENTS · MONTH', appointmentsX, y + 42, `600 18px ${FONT}`, C.slate500);

    rows.forEach((row, i) => {
        const top = y + 64 + i * ROW;
        const middle = top + ROW / 2;

        if (row.rank <= 3) {
            ctx.fillStyle = C.primary50;
            ctx.fillRect(x + 1, top, width - 2, ROW);
        }

        ctx.strokeStyle = C.slate200;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x + 24, top);
        ctx.lineTo(x + width - 24, top);
        ctx.stroke();

        // Rank
        ctx.fillStyle = MEDALS[row.rank] ?? C.slate200;
        ctx.beginPath();
        ctx.arc(x + 52, middle, 26, 0, Math.PI * 2);
        ctx.fill();
        ctx.textAlign = 'center';
        text(ctx, String(row.rank), x + 52, middle + 9, `700 26px ${FONT}`, MEDALS[row.rank] ? C.white : C.slate900);

        // Name, and where they stand underneath it
        ctx.textAlign = 'left';
        text(ctx, row.name, x + 104, middle - 6, `700 29px ${FONT}`, C.slate900, 340);

        const status = LEADERBOARD_STATUS[row.status] ?? LEADERBOARD_STATUS.in_progress;
        ctx.font = `600 18px ${FONT}`;
        const chipWidth = ctx.measureText(status.label).width + 28;
        ctx.fillStyle = status.fill;
        roundRect(ctx, x + 104, middle + 10, chipWidth, 30, 15);
        ctx.fill();
        text(ctx, status.label, x + 118, middle + 31, `600 18px ${FONT}`, status.text);

        figure(ctx, row.leads, leadsX, middle, columnWidth, C.primary600);
        figure(ctx, row.appointments, appointmentsX, middle, columnWidth, C.primary400);
    });
}

/** "3 / 5", the percentage, and a bar - or a dash for a target nobody set. */
function figure(ctx, value, x, middle, width, colour) {
    ctx.textAlign = 'left';

    if (! value) {
        text(ctx, 'No target', x, middle + 8, `500 22px ${FONT}`, C.slate400);

        return;
    }

    const done = String(value.done);
    text(ctx, done, x, middle - 4, `700 30px ${FONT}`, C.slate900);
    ctx.font = `700 30px ${FONT}`;
    const doneWidth = ctx.measureText(done).width;
    text(ctx, `/ ${value.target}`, x + doneWidth + 8, middle - 4, `500 22px ${FONT}`, C.slate500);

    ctx.textAlign = 'right';
    text(ctx, `${value.percent}%`, x + width, middle - 4, `600 22px ${FONT}`, C.slate500);

    bar(ctx, x, middle + 14, width, 12, value.percent, colour);
}

function card(ctx, x, y, width, height) {
    ctx.fillStyle = C.white;
    roundRect(ctx, x, y, width, height, 24);
    ctx.fill();
    ctx.strokeStyle = C.slate200;
    ctx.lineWidth = 2;
    ctx.stroke();
}

function bar(ctx, x, y, width, height, percent, colour) {
    ctx.fillStyle = C.slate200;
    roundRect(ctx, x, y, width, height, height / 2);
    ctx.fill();

    const filled = (width * Math.max(0, Math.min(100, percent))) / 100;

    if (filled > 0) {
        ctx.fillStyle = colour;
        roundRect(ctx, x, y, Math.max(filled, height), height, height / 2);
        ctx.fill();
    }
}

/** Fill text, shortened with an ellipsis when it would run past maxWidth. */
function text(ctx, value, x, y, font, colour, maxWidth = null) {
    ctx.font = font;
    ctx.fillStyle = colour;

    let out = String(value);

    if (maxWidth && ctx.measureText(out).width > maxWidth) {
        while (out.length > 1 && ctx.measureText(`${out}…`).width > maxWidth) {
            out = out.slice(0, -1);
        }
        out = `${out.trimEnd()}…`;
    }

    ctx.fillText(out, x, y);
}

function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);

    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}
