<template>
    <div class="email-visual-editor gjs-email-editor-root rounded-lg border border-slate-300 bg-white overflow-hidden min-w-0 h-full flex flex-col">
        <p v-if="initError" class="text-xs text-red-700 px-3 py-2 bg-red-50 border-b border-red-200 shrink-0">
            {{ initError }}
        </p>
        <p v-if="complexTemplateNote" class="text-xs text-amber-950 px-3 py-2 bg-amber-50 border-b border-amber-200 shrink-0 leading-relaxed">
            {{ complexTemplateNote }}
        </p>
        <p v-if="!compact" class="text-xs text-slate-600 px-3 py-2 border-b border-slate-200 bg-slate-50 shrink-0">
            Click the canvas to select content. Use the <strong>right</strong> panels for blocks and styles. Merge tags like
            <code v-pre class="text-[11px]">{{first_name}}</code> work in text.
        </p>
        <div ref="hostRef" class="gjs-email-host flex-1 min-h-[320px] min-w-0" />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import newsletterModule from 'grapesjs-preset-newsletter';

/** Editor-only SVG; replaced on export unless the user picked a real image (src changes). */
const PLACEHOLDER_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="360" height="140" viewBox="0 0 360 140"><rect width="360" height="140" rx="8" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/><text x="180" y="52" text-anchor="middle" font-family="system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" font-size="13" fill="#475569">Image or merge tag</text><text x="180" y="76" text-anchor="middle" font-family="system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" font-size="11" fill="#94a3b8">Open Assets (right panel) to upload, or edit src</text><text x="180" y="96" text-anchor="middle" font-family="system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" font-size="10" fill="#cbd5e1">{{placeholders}}</text></svg>`.replace(
    '{{placeholders}}',
    'Dynamic: {{header_logo_url}} etc. are kept when you save'
);
const PLACEHOLDER_DATA_URI = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(PLACEHOLDER_SVG)}`;

function imageSrcNeedsEditorPlaceholder(src) {
    const s = String(src || '').trim();
    if (!s || s === '#') {
        return true;
    }
    if (s.toLowerCase().startsWith('file:')) {
        return true;
    }
    if (s.includes('{{')) {
        return true;
    }
    if (/^\.\.?\//.test(s)) {
        return true;
    }
    if (/^https?:\/\//i.test(s)) {
        return false;
    }
    if (s.startsWith('data:')) {
        return false;
    }
    if (s.startsWith('/')) {
        return false;
    }
    return true;
}

/**
 * Apply merge-tag / broken img placeholders on a parsed document (body only).
 */
function applyImagePlaceholdersToDocumentBody(doc) {
    doc.body.querySelectorAll('img').forEach((img) => {
        const raw = img.getAttribute('src') || '';
        if (!imageSrcNeedsEditorPlaceholder(raw)) {
            return;
        }
        if (!img.hasAttribute('data-crm-src')) {
            img.setAttribute('data-crm-src', raw);
        }
        img.setAttribute('src', PLACEHOLDER_DATA_URI);
    });
}

/**
 * Prepare HTML for GrapesJS: keep &lt;style&gt; blocks (they live in &lt;head&gt; on full documents — returning only
 * body.innerHTML used to drop all CSS and “destroy” imported emails). Strip collected &lt;style&gt; nodes from the
 * fragment so they are not duplicated. Then apply image placeholders.
 */
function prepareHtmlForGrapesCanvas(html) {
    if (!html || typeof html !== 'string') {
        return '';
    }
    const trimmed = html.trim();
    if (!trimmed) {
        return trimmed;
    }
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(trimmed, 'text/html');

        const styleTexts = [];
        doc.querySelectorAll('style').forEach((el) => {
            const t = el.textContent ?? '';
            if (t.trim()) {
                styleTexts.push(t);
            }
            el.remove();
        });

        applyImagePlaceholdersToDocumentBody(doc);

        const styleOpen = '<style type="text/css">';
        const styleClose = '</style>';
        const stylePrefix =
            styleTexts.length > 0 ? `${styleOpen}${styleTexts.join('\n\n')}${styleClose}` : '';

        return stylePrefix + doc.body.innerHTML;
    } catch {
        return trimmed;
    }
}

function shouldWarnComplexImportedEmail(html) {
    const s = (html || '').trim();
    if (s.length < 80) {
        return false;
    }
    const head = s.slice(0, 12000).toLowerCase();
    if (head.startsWith('<!doctype')) {
        return true;
    }
    if (head.includes('<html') && head.includes('</head>')) {
        return true;
    }
    if (head.includes('<head') && head.includes('<style')) {
        return true;
    }
    return false;
}

function restoreEditorImageSources(html) {
    if (!html || typeof html !== 'string' || !html.includes('data-crm-src')) {
        return html;
    }
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        doc.querySelectorAll('img[data-crm-src]').forEach((img) => {
            const cur = img.getAttribute('src') || '';
            const orig = img.getAttribute('data-crm-src') ?? '';
            if (cur === PLACEHOLDER_DATA_URI) {
                img.setAttribute('src', orig);
            }
            img.removeAttribute('data-crm-src');
        });
        return doc.body.innerHTML;
    } catch {
        return html;
    }
}

/** GrapesJS asset thumbnails: fix host mismatch vs APP_URL. */
function normalizeStorageUrlForBrowser(src) {
    if (!src || typeof src !== 'string') {
        return src;
    }
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    if (!origin) {
        return src;
    }
    if (src.startsWith(origin)) {
        return src;
    }
    try {
        if (/^https?:\/\//i.test(src)) {
            const u = new URL(src);
            if (u.pathname.startsWith('/storage/') || u.pathname.startsWith('/images/')) {
                return origin + u.pathname + u.search + u.hash;
            }
            return src;
        }
    } catch {
        /* ignore */
    }
    if (src.startsWith('/storage/') || src.startsWith('/images/')) {
        return origin + src;
    }
    return src;
}

function patchUploadResponseDataUrls(json) {
    if (!json || !Array.isArray(json.data)) {
        return;
    }
    for (let i = 0; i < json.data.length; i += 1) {
        const entry = json.data[i];
        if (typeof entry === 'string') {
            json.data[i] = normalizeStorageUrlForBrowser(entry);
        } else if (entry && typeof entry.src === 'string') {
            entry.src = normalizeStorageUrlForBrowser(entry.src);
        }
    }
}

function normalizeAllAssetManagerUrls() {
    if (!editor) {
        return;
    }
    const am = editor.Assets;
    if (!am || typeof am.getAll !== 'function') {
        return;
    }
    const assets = am.getAll();
    if (!Array.isArray(assets)) {
        return;
    }
    assets.forEach((asset) => {
        if (!asset || typeof asset.get !== 'function') {
            return;
        }
        const src = asset.get('src');
        const next = normalizeStorageUrlForBrowser(src);
        if (next && next !== src) {
            asset.set('src', next);
        }
    });
}

/** @type {((json: unknown) => void) | null} */
let onAssetUploadResponse = null;
/** @type {(() => void) | null} */
let onAssetOpen = null;

function bindAssetUrlFixers() {
    if (!editor) {
        return;
    }
    onAssetUploadResponse = (json) => {
        patchUploadResponseDataUrls(json);
    };
    onAssetOpen = () => {
        normalizeAllAssetManagerUrls();
    };
    editor.on('asset:upload:response', onAssetUploadResponse);
    editor.on('asset:open', onAssetOpen);
}

function unbindAssetUrlFixers() {
    if (!editor || !onAssetUploadResponse) {
        return;
    }
    editor.off('asset:upload:response', onAssetUploadResponse);
    editor.off('asset:open', onAssetOpen);
    onAssetUploadResponse = null;
    onAssetOpen = null;
}

/** GrapesJS expects this to resolve to response body text (not a Response object). */
function assetManagerFetch(url, options) {
    const token = typeof localStorage !== 'undefined' ? localStorage.getItem('auth_token') : null;
    const headers = new Headers(options?.headers || {});
    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrf) {
        headers.set('X-CSRF-TOKEN', csrf);
    }
    return fetch(url, {
        ...options,
        headers,
        credentials: 'include',
    }).then((res) =>
        res.text().then((text) => {
            if (!res.ok) {
                return Promise.reject(text);
            }
            return text;
        })
    );
}

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    /** GrapesJS canvas height, e.g. '580px' or '72vh' */
    canvasHeight: {
        type: String,
        default: '580px',
    },
    /** Shorter chrome when embedded in the full-screen visual modal */
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const hostRef = ref(null);
const initError = ref('');
/** Shown for full-page HTML emails: visual canvas is approximate vs HTML + preview. */
const complexTemplateNote = ref('');

/** @type {import('grapesjs').Editor | null} */
let editor = null;
let debounceTimer = null;
let ignoreUpdatesTimer = null;
let ignoreUpdates = false;

/** Vite/Node may nest `default` (CJS interop); preset must be a function(editor, opts). */
function resolveNewsletterPlugin(mod) {
    if (!mod) {
        return null;
    }
    if (typeof mod === 'function') {
        return mod;
    }
    const a = mod.default;
    if (typeof a === 'function') {
        return a;
    }
    if (a && typeof a.default === 'function') {
        return a.default;
    }
    return null;
}

const newsletterPlugin = resolveNewsletterPlugin(newsletterModule);

function buildOutputHtml() {
    if (!editor) {
        return '';
    }
    try {
        const inlined = editor.runCommand('gjs-get-inlined-html');
        if (typeof inlined === 'string' && inlined.trim()) {
            return restoreEditorImageSources(inlined.trim());
        }
    } catch {
        /* command may not return a string in all versions */
    }
    let html = editor.getHtml({ cleanId: false }) || '';
    const css = editor.getCss({ avoidProtected: true });
    if (css && String(css).trim()) {
        html = `<style type="text/css">${css}</style>${html}`;
    }
    return restoreEditorImageSources(html.trim());
}

function emitDebounced() {
    if (ignoreUpdates || !editor) {
        return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', buildOutputHtml());
    }, 450);
}

function bindEditorEvents() {
    if (!editor) {
        return;
    }
    editor.on('update', emitDebounced);
    editor.on('component:add', emitDebounced);
    editor.on('component:remove', emitDebounced);
    editor.on('component:update', emitDebounced);
}

function unbindEditorEvents() {
    if (!editor) {
        return;
    }
    editor.off('update', emitDebounced);
    editor.off('component:add', emitDebounced);
    editor.off('component:remove', emitDebounced);
    editor.off('component:update', emitDebounced);
}

function destroyEditor() {
    clearTimeout(debounceTimer);
    clearTimeout(ignoreUpdatesTimer);
    debounceTimer = null;
    ignoreUpdatesTimer = null;
    if (editor) {
        unbindEditorEvents();
        unbindAssetUrlFixers();
        editor.destroy();
        editor = null;
    }
}

function createEditor() {
    initError.value = '';
    if (!hostRef.value) {
        initError.value = 'Editor container not ready. Try closing and reopening the template.';
        return;
    }

    destroyEditor();

    const plugins = newsletterPlugin ? [newsletterPlugin] : [];
    const pluginsOpts = newsletterPlugin
        ? {
              [newsletterPlugin]: {
                  inlineCss: true,
                  showBlocksOnLoad: true,
                  showStylesOnChange: true,
              },
          }
        : {};

    try {
        editor = grapesjs.init({
            container: hostRef.value,
            height: props.canvasHeight,
            width: '100%',
            fromElement: false,
            storageManager: false,
            plugins,
            pluginsOpts,
            parser: {
                optionsHtml: {
                    allowUnsafeAttr: true,
                    allowUnsafeAttrValue: true,
                },
            },
            assetManager: {
                upload: '/api/email-templates/upload-image',
                uploadName: 'image',
                multiUpload: false,
                autoAdd: true,
                embedAsBase64: false,
                customFetch: assetManagerFetch,
            },
        });
    } catch (err) {
        initError.value =
            err instanceof Error ? err.message : 'Could not start the visual editor. Use HTML code tab instead.';
        return;
    }

    if (!newsletterPlugin) {
        initError.value =
            'Newsletter preset did not load; using basic GrapesJS (add blocks from the toolbar if visible).';
    }

    const initialRaw =
        props.modelValue?.trim() ||
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td style="padding:24px;font-family:Arial,sans-serif;color:#64748b;text-align:center">Use the <strong>Blocks</strong> panel to add rows, text, images, and buttons — or switch to <strong>HTML code</strong> and paste an imported template.</td></tr></table>';
    complexTemplateNote.value = shouldWarnComplexImportedEmail(initialRaw)
        ? 'This is a full HTML document: use the HTML editor + Live preview for reliable layout. The visual canvas uses GrapesJS and may still reflow flex/table/CSS compared to a real email client.'
        : '';
    const initial = prepareHtmlForGrapesCanvas(initialRaw);

    ignoreUpdates = true;
    try {
        editor.setComponents(initial);
    } catch (err) {
        initError.value =
            (err instanceof Error ? err.message : 'Invalid HTML') +
            ' — fix it in the HTML code tab, then reopen Visual builder.';
        ignoreUpdates = false;
        return;
    }
    clearTimeout(ignoreUpdatesTimer);
    ignoreUpdatesTimer = setTimeout(() => {
        ignoreUpdates = false;
        ignoreUpdatesTimer = null;
    }, 120);

    bindEditorEvents();
    bindAssetUrlFixers();
}

/** Replace canvas HTML (import / external merge tags). */
function loadHtml(html) {
    if (!editor) {
        return;
    }
    const raw = (html || '').trim() || '<div></div>';
    complexTemplateNote.value = shouldWarnComplexImportedEmail(raw)
        ? 'This is a full HTML document: use the HTML editor + Live preview for reliable layout. The visual canvas uses GrapesJS and may still reflow flex/table/CSS compared to a real email client.'
        : '';
    const body = prepareHtmlForGrapesCanvas(raw);
    ignoreUpdates = true;
    try {
        editor.setComponents(body);
    } catch (err) {
        initError.value =
            (err instanceof Error ? err.message : 'Invalid HTML') + ' — try fixing markup and import again.';
        ignoreUpdates = false;
        return;
    }
    clearTimeout(ignoreUpdatesTimer);
    ignoreUpdatesTimer = setTimeout(() => {
        ignoreUpdates = false;
        ignoreUpdatesTimer = null;
        emit('update:modelValue', buildOutputHtml());
    }, 150);
}

onMounted(() => {
    nextTick(() => {
        createEditor();
    });
});

onBeforeUnmount(() => {
    destroyEditor();
});

defineExpose({
    exportHtml: buildOutputHtml,
    loadHtml,
});
</script>

<style scoped>
.gjs-email-host {
    min-height: 320px;
}

.email-visual-editor :deep(.gjs-mdl-container) {
    z-index: 10050 !important;
}
</style>

<!-- Unscoped: GrapesJS mounts many nodes without Vue data attributes; avoid Tailwind breaking layout -->
<style>
.gjs-email-editor-root .gjs-editor {
    box-sizing: border-box;
    min-height: 320px;
}

.gjs-email-editor-root .gjs-editor * {
    box-sizing: border-box;
}

.gjs-email-editor-root .gjs-cv-canvas,
.gjs-email-editor-root .gjs-frame {
    box-sizing: border-box;
}
</style>
