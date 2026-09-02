import { ref } from 'vue';
import axios from 'axios';

/**
 * Sends a location reading every fifteen minutes while somebody is on shift.
 *
 * What this can and cannot do, stated plainly because the difference matters:
 * it works while the CRM is open on the phone. A browser cannot take a reading
 * once the tab is in the background - iOS Safari suspends JavaScript within
 * seconds of the app being switched away, and on Android a service worker
 * cannot reach the geolocation API at all. No amount of code changes that.
 *
 * So the trail this produces is honest but partial: it covers the parts of the
 * day the app was open. Every point it sends is tagged `browser` so the map
 * never presents that as continuous coverage, and a gap is never read as a
 * person standing still.
 *
 * Nothing is sent unless the server says the person is clocked in, and the
 * server refuses anything outside a shift regardless. The switch is the one
 * they already use every morning.
 */

const INTERVAL_MS = 15 * 60 * 1000;
const QUEUE_KEY = 'shift-location-queue';

/** How stale a cached position may be before we insist on a fresh fix. */
const MAX_AGE_MS = 60 * 1000;

const tracking = ref(false);
const lastSentAt = ref(null);
const permission = ref('unknown');

let timer = null;
let started = false;

function readQueue() {
    try {
        const raw = JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]');

        return Array.isArray(raw) ? raw : [];
    } catch {
        return [];
    }
}

function writeQueue(points) {
    try {
        // A day of readings is ~40 points. The cap is for a phone that has been
        // offline for days, so the queue cannot grow without limit.
        localStorage.setItem(QUEUE_KEY, JSON.stringify(points.slice(-200)));
    } catch {
        // Private browsing, or storage full. Losing the queue is better than
        // throwing inside a timer nobody is watching.
    }
}

function currentPosition() {
    return new Promise((resolve) => {
        if (! navigator.geolocation) {
            resolve(null);

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => resolve(pos),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: MAX_AGE_MS },
        );
    });
}

async function batteryLevel() {
    try {
        const b = await navigator.getBattery?.();

        return b ? Math.round(b.level * 100) : null;
    } catch {
        return null;
    }
}

/**
 * Take a reading and send it, along with anything the phone could not send
 * earlier. Sending the backlog is what keeps a tunnel or a dead signal from
 * leaving a hole that reads as somebody switching it off.
 */
async function capture() {
    const position = await currentPosition();

    const queue = readQueue();

    if (position) {
        queue.push({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy ?? null,
            recorded_at: new Date(position.timestamp || Date.now()).toISOString(),
            battery_level: await batteryLevel(),
        });
    }

    if (queue.length === 0) return;

    try {
        await axios.post('/api/hr/attendance/location', { points: queue, source: 'browser' });
        writeQueue([]);
        lastSentAt.value = new Date();
    } catch (error) {
        // 409 means the shift closed - stop rather than keep a queue that can
        // never be accepted.
        if (error?.response?.status === 409) {
            writeQueue([]);
            stop();

            return;
        }

        // Anything else is the network. Hold the points for the next attempt.
        writeQueue(queue);
    }
}

function stop() {
    tracking.value = false;

    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

async function checkStatus() {
    try {
        const { data } = await axios.get('/api/hr/attendance/location/status');

        return !! data?.tracking;
    } catch {
        return false;
    }
}

/**
 * Called once from the app shell. Safe to call again - it will not start a
 * second timer.
 */
export function useShiftLocation() {
    async function start() {
        if (started) return;
        started = true;

        if (! navigator.geolocation) {
            permission.value = 'unsupported';

            return;
        }

        // Only ask about permission when the answer could matter. Prompting a
        // person who is not on shift teaches them to refuse.
        if (! await checkStatus()) {
            // Still flush anything left over from a shift that has since ended;
            // the server will keep what belongs to it and refuse the rest.
            if (readQueue().length) await capture();

            return;
        }

        try {
            const state = await navigator.permissions?.query({ name: 'geolocation' });
            permission.value = state?.state ?? 'prompt';
        } catch {
            permission.value = 'prompt';
        }

        tracking.value = true;

        await capture();
        timer = setInterval(async () => {
            // Re-checking is what stops the timer after a clock-out on another
            // device, without waiting to be refused.
            if (! await checkStatus()) {
                stop();

                return;
            }

            await capture();
        }, INTERVAL_MS);
    }

    /** Called by the attendance card the moment somebody clocks in or out. */
    async function refresh() {
        started = false;
        stop();
        await start();
    }

    return { start, stop, refresh, tracking, lastSentAt, permission };
}
