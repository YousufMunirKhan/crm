/**
 * Build an absolute URL for a path or full URL (for img src, favicon, etc.).
 */
export function absolutePublicUrl(path) {
    if (!path || typeof window === 'undefined') return path || '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const origin = window.location.origin;
    return origin + (path.startsWith('/') ? path : `/${path}`);
}

const DEFAULT_FAVICON_PATH = '/icons/icon-72x72.svg';

/**
 * Set document favicon and shortcut icon; update apple-touch-icon links to match.
 * Empty url restores the built-in SVG icon (same as welcome.blade default).
 */
export function applyFavicon(url) {
    if (typeof document === 'undefined') return;

    const href =
        url && String(url).trim() !== ''
            ? absolutePublicUrl(url)
            : `${window.location.origin}${DEFAULT_FAVICON_PATH}`;

    let icon = document.querySelector('link[rel="icon"]');
    if (!icon) {
        icon = document.createElement('link');
        icon.rel = 'icon';
        document.head.appendChild(icon);
    }
    icon.href = href;
    const lower = href.toLowerCase();
    if (lower.endsWith('.svg')) {
        icon.type = 'image/svg+xml';
    } else if (lower.endsWith('.png')) {
        icon.type = 'image/png';
    } else if (lower.endsWith('.ico')) {
        icon.type = 'image/x-icon';
    } else if (lower.endsWith('.gif')) {
        icon.type = 'image/gif';
    } else if (lower.endsWith('.webp')) {
        icon.type = 'image/webp';
    } else {
        icon.removeAttribute('type');
    }

    const shortcut = document.querySelector('link[rel="shortcut icon"]');
    if (shortcut) {
        shortcut.href = href;
    }

    document.querySelectorAll('link[rel="apple-touch-icon"]').forEach((el) => {
        el.href = href;
    });
}
