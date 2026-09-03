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
 * Set the document favicon and shortcut icon - the browser tab only.
 * Empty url restores the built-in SVG icon (same as welcome.blade default).
 */
export function applyFavicon(url) {
    if (typeof document === 'undefined') return;

    const href =
        url && String(url).trim() !== ''
            ? absolutePublicUrl(url)
            : `${window.location.origin}${DEFAULT_FAVICON_PATH}`;

    // Every rel="icon" link, not just the first.
    //
    // The page declares two - a PNG and an SVG. This used to use querySelector,
    // which returns one element, so the SVG was left pointing at the built-in
    // file while the PNG got the configured icon. Browsers that prefer SVG then
    // showed the old artwork, and since that filename never changes it stayed
    // in the cache for the week the CDN's max-age allows.
    let icons = Array.from(document.querySelectorAll('link[rel="icon"]'));
    if (icons.length === 0) {
        const icon = document.createElement('link');
        icon.rel = 'icon';
        document.head.appendChild(icon);
        icons = [icon];
    }

    const lower = href.toLowerCase();
    const type = lower.endsWith('.svg')
        ? 'image/svg+xml'
        : lower.endsWith('.png')
          ? 'image/png'
          : lower.endsWith('.ico')
            ? 'image/x-icon'
            : lower.endsWith('.gif')
              ? 'image/gif'
              : lower.endsWith('.webp')
                ? 'image/webp'
                : null;

    icons.forEach((icon) => {
        icon.href = href;
        // The sizes hint belongs to the markup's own file, not to this one.
        icon.removeAttribute('sizes');
        if (type) {
            icon.type = type;
        } else {
            icon.removeAttribute('type');
        }
    });

    const shortcut = document.querySelector('link[rel="shortcut icon"]');
    if (shortcut) {
        shortcut.href = href;
    }

    // apple-touch-icon is deliberately left alone.
    //
    // It is what iOS copies onto the home screen, and iOS is fussy about it in
    // two ways the uploaded favicon cannot satisfy: it needs at least 180x180,
    // and it does not read webp. The upload is validated only as "an image
    // under 1MB", so pointing the home-screen icon at it - as this used to -
    // handed iOS a 96px webp it could not use, and the installed app fell back
    // to a screenshot of the page.
    //
    // The static PNGs in pwa-head.blade.php are sized for this. The setting
    // governs the browser tab; the home screen keeps the real icon set.
}
