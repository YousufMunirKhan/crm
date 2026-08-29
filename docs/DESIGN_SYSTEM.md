# Switch & Save CRM — Design System Implementation Contract

**Status:** authoritative. **Scope:** `resources/css/app.css` + `resources/js/**` only.
**Audience:** the implementing developer agent. Everything below is literal — copy the CSS
and component code as written. Where a choice is left open it says so explicitly.

This document **extends** the design system that already ships in `resources/css/app.css`.
It does not replace it. The primary hue stays blue (`--color-primary-600: #2563eb`), the two
radii (`--radius-control`, `--radius-card`) stay, the z-index utilities
(`z-sticky`/`z-dropdown`/`z-modal`/`z-toast`) stay, and every existing `.listing-*`,
`.form-*` and `.modal-backdrop` class keeps working unchanged until its call sites are
migrated in Phase D.

---

## 0. Audit findings this contract responds to

Measured on the current tree:

| Finding | Count | Consequence |
| --- | --- | --- |
| Components hand-rolling a modal with `fixed inset-0` | 37 files | No focus trap, no Escape, no scroll lock, no `role="dialog"` |
| `BaseModal.vue` call sites | **0** | The primitive exists and is used nowhere |
| `@heroicons/vue` / `@headlessui/vue` imports | **0 / 0** | Both installed, both dead weight |
| Distinct inline `<svg><path d="…">` blocks | ~106 | Same icon redrawn at 5 different stroke widths |
| Views not using `ListingPageShell` | 24 of 52 | Page chrome drifts per screen |
| Ad-hoc card class strings (`bg-white rounded-xl shadow-sm …`) | 12+ variants, 22× the most common | No single card surface |
| Raw non-token colours (`text-red-600`, `bg-emerald-*`, `bg-amber-*`, `text-blue-600`) | 700+ occurrences | The token layer is bypassed, not extended |
| Ad-hoc focus rings (`focus:ring-blue-500`) instead of `focus-visible:ring-primary-500/40` | widespread in `CustomersView`, `DashboardView` | Mouse users get rings, keyboard contract is inconsistent |

### Contrast defects that MUST be fixed during migration

Computed against `#ffffff`:

| Token | Hex | Ratio on white | Verdict |
| --- | --- | --- | --- |
| `slate-400` | `#94a3b8` | **2.54:1** | ❌ Never for text. Decorative/placeholder only. |
| `success-600` | `#059669` | **3.76:1** | ❌ Never for body text. Fills and icons ≥24px only. |
| `warning-600` | `#d97706` | **3.21:1** | ❌ Never for body text. Fills and icons only. |
| `slate-500` | `#64748b` | 4.76:1 | ✅ Smallest allowed muted body text |
| `danger-600` | `#dc2626` | 4.83:1 | ✅ (no smaller than 14px) |
| `primary-600` | `#2563eb` | 5.12:1 | ✅ |
| `success-700` | `#047857` | 5.55:1 | ✅ Use this for success **text** |
| `warning-800` | `#92400e` | 7.09:1 | ✅ Use this for warning **text** |

Rules that follow, and are non-negotiable:

1. `text-slate-400` is banned on any element containing words a user must read.
   Replace with `text-slate-500`. (`DashboardView.vue` uses it for KPI captions — fix.)
2. Status **text** uses the `-700`/`-800` step. Status **fills** use the `-50`/`-100` step.
   `-600` is for solid button backgrounds and icon glyphs ≥ 20px only.
3. Every interactive element carries `focus-visible:ring-2` + a ring colour. `focus:ring-*`
   (without `-visible`) is banned — it fires on mouse click and is inconsistent with the
   `@layer base :focus-visible` rule already in `app.css`.

---

## 1. Token additions

Append these **inside the existing `@theme { … }` block** in `resources/css/app.css`,
after `--radius-card`. Nothing existing is edited or removed.

```css
    /* ---- Status ramp completion -------------------------------------------
       success/warning/danger were defined with holes. Ring and border steps
       (-200) and hover/active steps (-700/-900) were missing, which is why
       ~700 raw `emerald-*`, `amber-*` and `red-*` classes leaked into views. */
    --color-success-300: #6ee7b7;
    --color-success-400: #34d399;
    --color-success-500: #10b981;
    --color-success-900: #064e3b;

    --color-warning-200: #fde68a;
    --color-warning-300: #fcd34d;
    --color-warning-400: #fbbf24;
    --color-warning-500: #f59e0b;
    --color-warning-700: #b45309;
    --color-warning-900: #78350f;

    --color-danger-200: #fecaca;
    --color-danger-300: #fca5a5;
    --color-danger-400: #f87171;
    --color-danger-500: #ef4444;
    --color-danger-900: #7f1d1d;

    /* ---- Semantic surfaces ------------------------------------------------
       Named surfaces instead of memorising which slate step is "the card" vs
       "the header bar". These also become the single seam a future dark theme
       would flip (see §8). */
    --color-surface: #ffffff;          /* cards, modals, table body        */
    --color-surface-muted: #f8fafc;    /* slate-50  - section heads, thead */
    --color-surface-sunken: #f1f5f9;   /* slate-100 - page background      */
    --color-border-subtle: #f1f5f9;    /* slate-100 - row dividers         */
    --color-border-default: #e2e8f0;   /* slate-200 - card + input borders */
    --color-border-strong: #cbd5e1;    /* slate-300 - hover borders        */

    /* ---- Radii ------------------------------------------------------------
       A third radius, only because ListingPageShell and the dashboard KPI
       cards already both use rounded-2xl (1rem) for the outermost panel. */
    --radius-panel: 1rem;

    /* ---- Elevation --------------------------------------------------------
       Four levels, no more. Every `shadow-sm shadow-slate-900/[0.0x]` string
       currently in the views collapses onto one of these. */
    --shadow-card: 0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.04);
    --shadow-card-hover: 0 4px 6px -1px rgb(15 23 42 / 0.07), 0 2px 4px -2px rgb(15 23 42 / 0.05);
    --shadow-dropdown: 0 10px 15px -3px rgb(15 23 42 / 0.10), 0 4px 6px -4px rgb(15 23 42 / 0.08);
    --shadow-overlay: 0 20px 25px -5px rgb(15 23 42 / 0.12), 0 8px 10px -6px rgb(15 23 42 / 0.10);

    /* ---- Named spacing ----------------------------------------------------
       The 4px scale Tailwind already generates is kept. These are *layout*
       constants so page gutters stop being re-invented per view. */
    --spacing-gutter: 1rem;      /* p-gutter   - mobile page/card padding */
    --spacing-gutter-lg: 1.5rem; /* p-gutter-lg- >=sm page/card padding   */
    --spacing-section: 2rem;     /* gap-section- between page sections     */
    --spacing-field: 1rem;       /* gap-field  - between form fields       */

    /* ---- Named type styles ------------------------------------------------
       Additive only. --text-xs … --text-3xl keep Tailwind's defaults. */
    --text-eyebrow: 0.6875rem;              /* 11px - table heads, KPI labels */
    --text-eyebrow--line-height: 1rem;
    --text-eyebrow--letter-spacing: 0.06em;
    --text-eyebrow--font-weight: 600;

    --text-metric: 1.5rem;                  /* 24px - KPI value, mobile       */
    --text-metric--line-height: 1.75rem;
    --text-metric--font-weight: 600;

    --text-metric-lg: 1.875rem;             /* 30px - KPI value, >=sm         */
    --text-metric-lg--line-height: 2.25rem;
    --text-metric-lg--font-weight: 600;

    --text-page-title: 1.5rem;              /* 24px - h1 in ListingPageShell  */
    --text-page-title--line-height: 2rem;
    --text-page-title--letter-spacing: -0.015em;
    --text-page-title--font-weight: 700;
```

### 1.1 Deliberately NOT added: an `info` colour

The comment block at the top of `app.css` names `info` as a role but no `--color-info-*`
ever existed. **Do not add one.** Informational callouts use the primary ramp
(`primary-50` / `primary-200` / `primary-900`) — exactly what `.form-tip-panel` already
does. One blue, one meaning. `.callout-info` in §2 encodes this.

### 1.2 Base-layer additions

Append to the **existing** `@layer base { … }` block:

```css
    /* Honour the OS "reduce motion" setting. The app animates spinners,
       sidebar transforms and card shadows on every screen. */
    @media (prefers-reduced-motion: reduce) {
        *,
        ::before,
        ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }

    /* Tables carry numbers on nearly every screen; align the glyph widths. */
    td,
    th {
        font-variant-numeric: tabular-nums;
    }
```

---

## 2. Component class additions

Append to the **existing** `@layer components { … }` block in `app.css`, after
`.modal-backdrop`. Existing classes are untouched except where a grouped selector is
explicitly given.

> **Tailwind 4 constraint:** `@apply` only accepts *utilities*, not other component
> classes. Every block below therefore lists utilities in full. Where two class names must
> share a definition, they are written as a grouped selector rather than one `@apply`ing
> the other.

### 2.1 Buttons

```css
    /* ---- Button system ----------------------------------------------------
       Compose: .btn + one size + one variant.
         <button class="btn btn-md btn-primary">Save</button>
       Supersedes .listing-btn-* and .form-btn-* (kept as deprecated aliases
       until Phase D of the migration). */
    .btn {
        @apply inline-flex items-center justify-center gap-2 rounded-control font-semibold
            select-none whitespace-nowrap transition-colors
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-0
            disabled:opacity-50 disabled:pointer-events-none;
    }
    .btn-sm {
        @apply px-3 py-1.5 min-h-[34px] text-xs;
    }
    .btn-md {
        @apply px-4 py-2.5 min-h-[42px] text-sm touch-manipulation;
    }
    .btn-lg {
        @apply px-6 py-3 min-h-[48px] text-base touch-manipulation;
    }
    /** Icon-only. MUST carry an aria-label. */
    .btn-icon {
        @apply p-2 min-h-[38px] min-w-[38px] text-sm;
    }
    /** The single main action on a screen. Never more than one per view. */
    .btn-primary {
        @apply bg-primary-600 text-white shadow-card hover:bg-primary-700
            active:bg-primary-800 focus-visible:ring-primary-500/40;
    }
    /** Secondary actions: Filter, Apply, Export, Generate. */
    .btn-soft {
        @apply bg-primary-50 text-primary-700 border border-primary-200
            hover:bg-primary-100 active:bg-primary-200 focus-visible:ring-primary-500/40;
    }
    .btn-outline {
        @apply border border-slate-200 bg-white text-slate-700 shadow-card
            hover:bg-slate-50 hover:border-slate-300 focus-visible:ring-primary-500/40;
    }
    /** Toolbar / table-row actions where a border would add noise. */
    .btn-ghost {
        @apply text-slate-600 hover:bg-slate-100 hover:text-slate-900
            focus-visible:ring-primary-500/40;
    }
    .btn-danger {
        @apply bg-danger-600 text-white shadow-card hover:bg-danger-700
            active:bg-danger-800 focus-visible:ring-danger-600/40;
    }
    /** Confirms a positive/terminal state (Mark won, Mark paid). Rare. */
    .btn-success {
        @apply bg-success-700 text-white shadow-card hover:bg-success-800
            focus-visible:ring-success-600/40;
    }
    /** Full width on mobile, intrinsic from sm up. Add to any .btn. */
    .btn-block-mobile {
        @apply w-full sm:w-auto;
    }
```

### 2.2 Badges

```css
    /* ---- Badge system -----------------------------------------------------
       Compose: .badge + one tone. Replaces .listing-badge-active /
       -inactive / -pending and ~40 ad-hoc rounded-full pills. */
    .badge {
        @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5
            text-xs font-medium ring-1 ring-inset whitespace-nowrap;
    }
    .badge-neutral {
        @apply bg-slate-100 text-slate-700 ring-slate-200;
    }
    .badge-primary {
        @apply bg-primary-50 text-primary-800 ring-primary-200;
    }
    .badge-success {
        @apply bg-success-50 text-success-800 ring-success-200;
    }
    .badge-warning {
        @apply bg-warning-50 text-warning-800 ring-warning-200;
    }
    .badge-danger {
        @apply bg-danger-50 text-danger-800 ring-danger-200;
    }
    /** Leading status dot inside a .badge. Inherits the badge text colour. */
    .badge-dot {
        @apply w-1.5 h-1.5 rounded-full bg-current shrink-0;
    }
    /** Numeric counter (sidebar/tab counts). */
    .badge-count {
        @apply inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5
            rounded-full bg-slate-900 text-white text-[10px] font-semibold tabular-nums;
    }

    /* Deprecated aliases - identical output, kept so no view breaks mid-migration. */
    .listing-badge-active {
        @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium
            ring-1 ring-inset whitespace-nowrap bg-success-50 text-success-800 ring-success-200;
    }
    .listing-badge-inactive {
        @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium
            ring-1 ring-inset whitespace-nowrap bg-danger-50 text-danger-800 ring-danger-200;
    }
    .listing-badge-pending {
        @apply inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium
            ring-1 ring-inset whitespace-nowrap bg-warning-50 text-warning-800 ring-warning-200;
    }
```

> The three `.listing-badge-*` rules above **replace** the existing three in `app.css`
> (they gain `gap-1.5` and move from `ring-*-100` to `ring-*-200`). This is the one
> in-place edit to an existing class this contract authorises.

### 2.3 Cards and panels

```css
    /* ---- Card surfaces ----------------------------------------------------
       Replaces 12 hand-written variants of
       "bg-white rounded-xl shadow-sm p-6 border border-slate-200". */
    .card {
        @apply bg-white rounded-panel border border-slate-200 shadow-card;
    }
    /** Add to .card when the whole card is a link/button. */
    .card-interactive {
        @apply transition-shadow cursor-pointer hover:shadow-card-hover hover:border-slate-300
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40;
    }
    .card-head {
        @apply px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50/90
            flex flex-wrap items-start justify-between gap-3;
    }
    .card-title {
        @apply text-base font-semibold text-slate-900 tracking-tight;
    }
    .card-subtitle {
        @apply text-sm text-slate-500 mt-0.5 leading-relaxed;
    }
    .card-body {
        @apply p-4 sm:p-6;
    }
    .card-foot {
        @apply px-4 sm:px-6 py-3 border-t border-slate-200 bg-slate-50/70
            flex flex-wrap items-center justify-end gap-3;
    }

    /* ---- KPI / stat tile (dashboards) ------------------------------------ */
    .stat-card {
        @apply bg-white rounded-panel border border-slate-200 shadow-card
            p-4 sm:p-5 min-w-0 text-left w-full;
    }
    .stat-label {
        @apply text-eyebrow text-slate-500 uppercase;
    }
    .stat-value {
        @apply text-metric sm:text-metric-lg text-slate-900 tabular-nums mt-1;
    }
    .stat-caption {
        @apply text-xs text-slate-500 mt-2 line-clamp-2;
    }
    /** 40/44px tinted square that holds the stat icon. */
    .stat-icon {
        @apply w-10 h-10 sm:w-11 sm:h-11 rounded-xl grid place-items-center shrink-0;
    }
```

### 2.4 Tables

Grouped selectors so the new `.table-*` names and the existing `.listing-*` names are the
same rule. **Replace** the existing `.listing-thead` / `.listing-th` / `.listing-td` /
`.listing-td-strong` / `.listing-row` declarations with these:

```css
    /* ---- Table system ----------------------------------------------------- */
    .table-wrap {
        @apply w-full overflow-x-auto;
    }
    .table {
        @apply w-full border-collapse text-left;
    }
    .table-thead,
    .listing-thead {
        @apply bg-slate-50/90;
    }
    .table-th,
    .listing-th {
        @apply px-5 py-3.5 text-left text-eyebrow text-slate-500 uppercase;
    }
    .table-td,
    .listing-td {
        @apply px-5 py-4 text-sm text-slate-700 border-b border-slate-100;
    }
    .table-td-strong,
    .listing-td-strong {
        @apply px-5 py-4 text-sm font-semibold text-slate-800 border-b border-slate-100;
    }
    .table-row,
    .listing-row {
        @apply hover:bg-slate-50/60 transition-colors;
    }
    /* New members of the family. */
    .table-th-num {
        @apply px-5 py-3.5 text-right text-eyebrow text-slate-500 uppercase;
    }
    .table-td-num {
        @apply px-5 py-4 text-sm text-slate-700 border-b border-slate-100 text-right tabular-nums;
    }
    .table-td-actions {
        @apply px-5 py-4 text-sm border-b border-slate-100 text-right whitespace-nowrap;
    }
    /** Sticky header for long tables inside a scroll container. */
    .table-thead-sticky {
        @apply sticky top-0 z-sticky bg-slate-50/95 backdrop-blur-sm;
    }
    /** The mobile card that stands in for a table row below md. */
    .table-card {
        @apply rounded-card border border-slate-200 bg-slate-50/40 p-4 space-y-2;
    }
```

### 2.5 Form additions

```css
    /* ---- Select --------------------------------------------------------
       Matches .form-input exactly, plus a chevron. appearance-none removes
       the OS control so the height matches inputs on every platform. */
    .form-select {
        @apply w-full min-h-[42px] pl-3 pr-9 py-2 border border-slate-200 rounded-control
            text-sm bg-white text-slate-900 shadow-card appearance-none bg-no-repeat
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30
            focus-visible:border-primary-500 transition-colors
            disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-position: right 0.625rem center;
        background-size: 1rem 1rem;
    }
    /** Search field with a leading icon; put the icon in an absolutely
        positioned span and give the input this class. */
    .form-input-search {
        @apply w-full min-h-[42px] pl-10 pr-3 py-2 border border-slate-200 rounded-control
            text-sm bg-white text-slate-900 placeholder:text-slate-500 shadow-card
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30
            focus-visible:border-primary-500 transition-colors;
    }
    .form-hint {
        @apply mt-1 text-xs text-slate-500;
    }
    .form-error {
        @apply mt-1 text-xs text-danger-700;
    }
    /** Red asterisk after a required field's label text. */
    .form-required {
        @apply text-danger-600 ml-0.5;
    }
    .form-grid-2 {
        @apply grid grid-cols-1 sm:grid-cols-2 gap-field;
    }
    .form-grid-3 {
        @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-field;
    }
    /** Radio/checkbox choice rendered as a full tap target. */
    .form-choice {
        @apply inline-flex items-center gap-2 cursor-pointer text-sm font-medium
            text-slate-700 min-h-[38px];
    }
    /** Applied to <fieldset>; pair with .form-legend on the <legend>. */
    .form-fieldset {
        @apply border-0 p-0 m-0 min-w-0;
    }
    .form-legend {
        @apply text-sm font-semibold text-slate-800 mb-2 p-0;
    }
```

### 2.6 Callouts, chips, tabs, feedback

```css
    /* ---- Callouts (inline messages, not toasts) --------------------------- */
    .callout {
        @apply rounded-card border px-4 py-3 text-sm leading-relaxed;
    }
    .callout-info {
        @apply border-primary-200 bg-primary-50 text-primary-900;
    }
    .callout-success {
        @apply border-success-200 bg-success-50 text-success-800;
    }
    .callout-warning {
        @apply border-warning-200 bg-warning-50 text-warning-800;
    }
    .callout-danger {
        @apply border-danger-200 bg-danger-50 text-danger-800;
    }

    /* ---- Filter chips (active-filter summaries) --------------------------- */
    .chip {
        @apply inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1
            text-xs font-medium text-slate-700;
    }
    .chip-remove {
        @apply -mr-1 rounded-full p-0.5 text-slate-500 hover:bg-slate-200 hover:text-slate-800
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40;
    }

    /* ---- Segmented tabs (SettingsView, WhatsAppManagementView, HrView) ----- */
    .tab-list {
        @apply flex flex-nowrap gap-2 overflow-x-auto p-1 min-w-0;
    }
    .tab {
        @apply px-4 py-2 rounded-control text-sm font-medium whitespace-nowrap shrink-0
            text-slate-600 transition-colors hover:bg-slate-100
            focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40;
    }
    .tab-active {
        @apply bg-primary-600 text-white hover:bg-primary-700;
    }

    /* ---- Loading + empty -------------------------------------------------- */
    .skeleton {
        @apply animate-pulse rounded-control bg-slate-200/70;
    }
    .skeleton-text {
        @apply animate-pulse rounded bg-slate-200/70 h-4;
    }
    /** Spinner: <span class="spinner" role="status" aria-label="Loading" /> */
    .spinner {
        @apply inline-block w-4 h-4 rounded-full border-2 border-current border-r-transparent
            animate-spin align-[-0.125em];
    }

    /* ---- Misc ------------------------------------------------------------- */
    .link {
        @apply text-primary-600 font-medium hover:text-primary-800 underline-offset-2
            hover:underline focus-visible:outline-none focus-visible:ring-2
            focus-visible:ring-primary-500/40 rounded-sm;
    }
    .kbd {
        @apply inline-flex items-center rounded border border-slate-200 bg-slate-50
            px-1.5 py-0.5 text-[10px] font-medium text-slate-600;
    }
    .divider {
        @apply border-t border-slate-100;
    }
    /** Dropdown/popover panel. Pair with z-dropdown. */
    .popover-panel {
        @apply rounded-card border border-slate-200 bg-white shadow-dropdown py-1.5 z-dropdown;
    }
    .popover-item {
        @apply flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 text-left
            hover:bg-slate-50 focus-visible:outline-none focus-visible:bg-slate-50;
    }
    /** Standard heroicon size inside buttons, badges and table cells. */
    .icon {
        @apply w-5 h-5 shrink-0;
    }
    .icon-sm {
        @apply w-4 h-4 shrink-0;
    }
    /** Skip-to-content link; first focusable child of AppLayout. */
    .skip-link {
        @apply sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-toast
            focus:rounded-control focus:bg-primary-600 focus:px-4 focus:py-2
            focus:text-sm focus:font-semibold focus:text-white;
    }
```

### 2.7 Deprecation notice to add above the legacy button block

Insert this comment above `.listing-btn-primary` in `app.css`. Do **not** delete the
classes until Phase D:

```css
    /**
     * @deprecated Use .btn + .btn-{sm|md|lg} + .btn-{primary|soft|outline|ghost|danger}.
     *   .listing-btn-accent  -> .btn .btn-md .btn-primary .btn-block-mobile
     *   .listing-btn-primary -> .btn .btn-md .btn-soft
     *   .listing-btn-outline -> .btn .btn-md .btn-outline
     *   .listing-btn-danger  -> .btn .btn-md .btn-danger
     *   .form-btn-submit     -> .btn .btn-lg .btn-primary .btn-block-mobile
     *   .form-btn-cancel     -> .btn .btn-md .btn-outline .btn-block-mobile
     * Remove once `rg 'listing-btn-|form-btn-' resources/js` is empty.
     */
```

---

## 3. Primitive components to add

All under `resources/js/components/base/`. Six new files plus a barrel. Each replaces a
specific, counted duplication — nothing speculative.

### 3.1 `resources/js/components/base/BaseButton.vue`

| | |
| --- | --- |
| **Replaces** | ~400 hand-written `<button class="px-4 py-2 …">`; all `.listing-btn-*` / `.form-btn-*` call sites |
| **Renders** | `<button>`, or `<router-link>` when `to` is passed, or `<a>` when `href` is passed |

```
props:
  variant   String  'primary' | 'soft' | 'outline' | 'ghost' | 'danger' | 'success'   default 'outline'
  size      String  'sm' | 'md' | 'lg' | 'icon'                                        default 'md'
  type      String  'button' | 'submit' | 'reset'                                      default 'button'
  to        [String, Object]  router-link target                                       default null
  href      String                                                                     default null
  disabled  Boolean                                                                    default false
  loading   Boolean  shows .spinner, sets aria-busy, forces disabled                   default false
  blockMobile Boolean adds .btn-block-mobile                                           default false
  label     String   REQUIRED when size === 'icon'; becomes aria-label                 default ''
emits:
  click (MouseEvent)   -- not emitted while disabled or loading
slots:
  default   button text
  icon      leading icon (rendered before text, receives no props)
```

Implementation notes (binding, not optional):
- `type` must only be bound on the `<button>` branch.
- When `loading`, render `<span class="spinner" aria-hidden="true" />` in place of the
  `icon` slot and set `aria-busy="true"`; keep the label text visible so the button does
  not change width mid-request.
- Dev-time assert: `if (size === 'icon' && !label) console.warn(...)`.
- `defineOptions({ inheritAttrs: true })` so `aria-*`, `form`, `title` pass through.

### 3.2 `resources/js/components/base/BaseSelect.vue`

| | |
| --- | --- |
| **Replaces** | 60+ `<select>` elements, each with its own class string and hand-written `id`/`for` pair |
| **Mirrors** | `BaseInput.vue` exactly — same `useId()` label pairing, same `hint`/`error` slots |

```
props:
  modelValue  [String, Number, null]                            default ''
  label       String   REQUIRED
  options     Array    of { value, label, disabled? } OR primitives
  placeholder String   rendered as a disabled first <option value="">   default ''
  required    Boolean                                            default false
  disabled    Boolean                                            default false
  hint        String                                             default ''
  error       String                                             default ''
emits:
  update:modelValue (value)
```

Copy the `id = \`field-${useId()}\`` / `describedBy` computed / `aria-invalid` logic
verbatim from `BaseInput.vue`. Root element class `min-w-0`, control class `form-select`.

### 3.3 `resources/js/components/base/BaseBadge.vue`

| | |
| --- | --- |
| **Replaces** | `.listing-badge-*` call sites and ~40 ad-hoc pills; centralises the status→tone map |

```
props:
  tone   String  'neutral' | 'primary' | 'success' | 'warning' | 'danger'   default 'neutral'
  status String  raw domain status; when set, tone is derived and ignored   default ''
  dot    Boolean render a leading .badge-dot                                default false
slots:
  default  label text (falls back to a title-cased `status`)
```

Export the map from the component so views stop re-deriving it:

```js
export const STATUS_TONE = {
    active: 'success', won: 'success', paid: 'success', completed: 'success',
    approved: 'success', resolved: 'success', delivered: 'success', sent: 'success',
    pending: 'warning', draft: 'warning', scheduled: 'warning', partial: 'warning',
    in_progress: 'warning', overdue: 'danger', failed: 'danger', lost: 'danger',
    cancelled: 'danger', inactive: 'danger', rejected: 'danger', unpaid: 'danger',
    open: 'primary', new: 'primary', contacted: 'primary', qualified: 'primary',
};
```

Unknown statuses fall back to `neutral` — never throw.

### 3.4 `resources/js/components/base/BaseCard.vue`

| | |
| --- | --- |
| **Replaces** | the 12 ad-hoc `bg-white rounded-xl shadow-sm border …` variants |

```
props:
  title       String                             default ''
  subtitle    String                             default ''
  padded      Boolean  wrap default slot in .card-body   default true
  interactive Boolean  adds .card-interactive           default false
  as          String   'div' | 'section' | 'article'    default 'div'
emits:
  click (MouseEvent)   -- only meaningful when interactive
slots:
  default, header (replaces title/subtitle), actions (right side of .card-head), footer
```

When `interactive` is true the root gets `tabindex="0"`, `role="button"` and a
`@keydown.enter/@keydown.space` handler that emits `click` — a clickable card that only
responds to a mouse is a keyboard trap by omission (this is the current bug in
`DashboardView.vue`'s KPI tiles).

### 3.5 `resources/js/components/base/BaseTable.vue`

| | |
| --- | --- |
| **Replaces** | the `<div class="hidden md:block overflow-x-auto"><table>…` + `<div class="md:hidden space-y-3">` pair repeated in every listing view |

```
props:
  columns  Array    REQUIRED. { key, label, align?: 'left'|'right', width?, class?,
                                mobileLabel?, hideOnMobile?: Boolean }
  rows     Array    REQUIRED
  rowKey   [String, Function]                          default 'id'
  loading  Boolean  renders skeleton rows              default false
  minWidth String   min-width for the desktop table    default '640px'
  caption  String   visually-hidden <caption>          default ''
slots:
  cell-<key>   scoped { row, value, index } - per-column override
  actions      scoped { row } - rendered in a trailing .table-td-actions
  empty        replaces the default <EmptyState>
  mobile       scoped { row } - full override of the mobile card
```

Requirements:
- Desktop `<table>` inside `.table-wrap`; below `md`, render one `.table-card` per row
  using `column.mobileLabel ?? column.label` as the field label. Do **not** just let the
  table scroll — the existing views deliberately provide a card layout on mobile.
- `<caption class="sr-only">` when `caption` is set.
- `align: 'right'` picks `.table-th-num` / `.table-td-num`.
- Empty state defaults to `<EmptyState heading="Nothing to show" />`.
- Loading renders 5 `.skeleton-text` rows, `aria-busy="true"` on the wrapper.

### 3.6 `resources/js/components/base/ConfirmDialog.vue`

| | |
| --- | --- |
| **Replaces** | `resources/js/components/DeleteConfirm.vue` (no focus trap, no Escape, raw `bg-red-600`, no `role="dialog"`) and ~15 inline "are you sure" modals |
| **Built on** | `BaseModal.vue` — do **not** re-implement the overlay |

```
props:
  modelValue   Boolean  REQUIRED (v-model)
  title        String                                    default 'Are you sure?'
  message      String                                    default ''
  confirmLabel String                                    default 'Confirm'
  cancelLabel  String                                    default 'Cancel'
  tone         String  'danger' | 'primary'              default 'danger'
  loading      Boolean                                   default false
emits:
  update:modelValue (Boolean)
  confirm
  cancel
slots:
  default  replaces `message` for rich content
```

Structure: `<BaseModal size="sm" :close-on-backdrop="!loading">` with the message in the
default slot and two `<BaseButton>`s in `#actions` (cancel = `outline`, confirm =
`tone === 'danger' ? 'danger' : 'primary'`, `:loading="loading"`).

`BaseModal` already gives focus trap, Escape, scroll lock, focus restoration,
`role="dialog"`, `aria-modal` and `aria-labelledby`. Nothing to add.

### 3.7 `resources/js/components/base/StatCard.vue`

| | |
| --- | --- |
| **Replaces** | the KPI tile repeated 4–8× in `DashboardView.vue`, `SalesAgentDashboard.vue`, `ReportsView.vue`, `CommissionReportView.vue`, `HrView.vue` |

```
props:
  label   String  REQUIRED
  value   [String, Number]  REQUIRED
  caption String                                          default ''
  tone    String  'neutral'|'primary'|'success'|'warning'|'danger'  default 'neutral'  (icon tint only)
  to      [String, Object]  makes the whole tile a router-link      default null
slots:
  icon    heroicon; wrapped in .stat-icon with the tone tint
```

Tone → icon chip classes:

| tone | chip |
| --- | --- |
| `neutral` | `bg-slate-100 text-slate-700` |
| `primary` | `bg-primary-100 text-primary-700` |
| `success` | `bg-success-100 text-success-800` |
| `warning` | `bg-warning-100 text-warning-800` |
| `danger` | `bg-danger-100 text-danger-800` |

Note the `-700`/`-800` glyph steps — `-600` on a `-100` tint fails contrast for the small
strokes these icons use.

### 3.8 `resources/js/components/base/index.js`

```js
export { default as BaseBadge } from './BaseBadge.vue';
export { default as BaseButton } from './BaseButton.vue';
export { default as BaseCard } from './BaseCard.vue';
export { default as BaseInput } from './BaseInput.vue';
export { default as BaseModal } from './BaseModal.vue';
export { default as BaseSelect } from './BaseSelect.vue';
export { default as BaseTable } from './BaseTable.vue';
export { default as ConfirmDialog } from './ConfirmDialog.vue';
export { default as EmptyState } from './EmptyState.vue';
export { default as StatCard } from './StatCard.vue';
export { STATUS_TONE } from './BaseBadge.vue';
```

### 3.9 Not building — use the installed library instead

| Need | Use |
| --- | --- |
| Dropdown menu (user menu in `AppLayout`) | `@headlessui/vue` `Menu`/`MenuButton`/`MenuItems`/`MenuItem` + `.popover-panel` |
| Tabs (`SettingsView`, `HrView`) | `@headlessui/vue` `TabGroup`/`TabList`/`Tab`/`TabPanels` + `.tab` / `.tab-active` |
| Combobox (customer search in `InvoiceCreateView`) | `@headlessui/vue` `Combobox` — replaces the hand-rolled `Teleport` + `z-[100]` dropdown |
| Toggle switch (`SettingsView`) | `@headlessui/vue` `Switch` |
| Any icon | `@heroicons/vue/24/outline` (see §4) |

---

## 4. Icon mapping

Import style — always named, always tree-shaken, always `aria-hidden` unless the icon is
the only content:

```js
import { XMarkIcon, PlusIcon } from '@heroicons/vue/24/outline';
```

```html
<XMarkIcon class="icon" aria-hidden="true" />
```

Use `/24/outline` everywhere. `/24/solid` only inside a filled badge or an active state.
`/20/solid` only for icons rendered at 16px or below inside dense table cells.

The 24 most-repeated inline SVGs in `resources/js`, by `path d` value:

| # | Uses | Current `d` (abridged) | Heroicons 24/outline export |
| --- | --- | --- | --- |
| 1 | 17 | `M6 18L18 6M6 6l12 12` | `XMarkIcon` |
| 2 | 10 | `M4 12a8 8 0 018-8V0…` (+ `animate-spin`) | `ArrowPathIcon` — or drop the SVG for `.spinner` |
| 3 | 7 | `M10 19l-7-7m0 0l7-7m-7 7h18` | `ArrowLeftIcon` |
| 4 | 5 | `M8 7V3m8 4V3m-9 8h10M5 21h14…` | `CalendarDaysIcon` |
| 5 | 5 | `M5 13l4 4L19 7` | `CheckIcon` |
| 6 | 4 | `M19 9l-7 7-7-7` | `ChevronDownIcon` |
| 7 | 3 | `M9 5l7 7-7 7` | `ChevronRightIcon` |
| 8 | 3 | `M15 19l-7-7 7-7` | `ChevronLeftIcon` |
| 9 | 3 | `M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14…` | `EnvelopeIcon` |
| 10 | 3 | `M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684…` | `PhoneIcon` |
| 11 | 3 | `M19 7l-.867 12.142A2 2 0 0116.138 21…` | `TrashIcon` |
| 12 | 3 | `M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5…` | `ArrowDownTrayIcon` |
| 13 | 3 | `M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11…` | `PencilSquareIcon` |
| 14 | 2 | `M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5…` | `DocumentTextIcon` |
| 15 | 2 | `M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M4 8V7…` | `ArrowUpTrayIcon` |
| 16 | 2 | `M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586…` | `FunnelIcon` |
| 17 | 2 | `M21 21l-4.35-4.35M17 11a6 6 0 11-12 0…` (also `M21 21l-6-6m2-5a7 7…`) | `MagnifyingGlassIcon` |
| 18 | 2 | `M2.458 12C3.732 7.943…` + `M15 12a3 3 0 11-6 0…` | `EyeIcon` |
| 19 | 2 | `M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3…` | `ArrowRightOnRectangleIcon` |
| 20 | 2 | `M14 2H6a2 2 0 00-2 2v16…` + `M14 2v6h6` | `DocumentIcon` |
| 21 | 2 | `M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z` | `ClockIcon` |
| 22 | 2 | `M12 4v16m8-8H4` | `PlusIcon` |
| 23 | 1 | `M4 6h16M4 12h16M4 18h16` | `Bars3Icon` |
| 24 | 1 | `M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9…` | `ArrowPathIcon` |

Additional mappings needed by `SidebarNavIcon.vue`, `DashboardView.vue` and
`CustomerLeadView.vue`:

| Meaning | Current | Heroicons export |
| --- | --- | --- |
| Dashboard / home | `M3 9l9-7 9 7v11…` | `HomeIcon` |
| Users / team | `M17 20h5v-2a3 3 0 00-5.356-1.857…` | `UsersIcon` |
| Add user | `M16 21v-2a4 4 0 00-4-4H5…` + `M20 8v6M23 11h-6` | `UserPlusIcon` |
| Trending up | `M13 7h8m0 0v8m0-8l-8 8-4-4-6 6` | `ArrowTrendingUpIcon` |
| Bar chart | `M9 19v-6a2 2 0 00-2-2H5…` | `ChartBarIcon` |
| Success check circle | `M9 12l2 2 4-4m6 2a9 9 0 11-18 0…` | `CheckCircleIcon` |
| Error | (add where missing) | `XCircleIcon` |
| Warning | (add where missing) | `ExclamationTriangleIcon` |
| Info | (add where missing) | `InformationCircleIcon` |
| Settings | `M15 12a3 3 0 11-6 0…` + gear ring | `Cog6ToothIcon` |
| List | `M8 6h13M8 12h13M8 18h13M3 6h.01…` | `ListBulletIcon` |
| Clipboard | `M9 5H7a2 2 0 00-2 2v12…` | `ClipboardDocumentIcon` |
| Wallet / money | `M21 12V7H5a2 2 0 010-4h14v4` | `BanknotesIcon` |
| Send / message | (composer buttons) | `PaperAirplaneIcon` |
| Chat | (WhatsApp/SMS views) | `ChatBubbleLeftRightIcon` |
| Row overflow menu | `M8 6a2 2 0 11-4 0…` (6-dot grid) | `EllipsisVerticalIcon` |
| Sortable column | (none today) | `ChevronUpDownIcon` |

**`SidebarNavIcon.vue` special case.** It is a hand-written `v-if` chain over ~25 names.
Do **not** delete it — the nav data in `app/Support/NavSections.php` passes icon *names*,
and that file is out of scope. Instead rewrite its internals as a name→Heroicon lookup:

```js
import { HomeIcon, UsersIcon, /* … */ } from '@heroicons/vue/24/outline';
const ICONS = { dashboard: HomeIcon, users: UsersIcon, wallet: BanknotesIcon, /* … */ };
const component = computed(() => ICONS[props.name] ?? Squares2X2Icon);
```

The public prop API (`name`) and the emitted markup size stay identical, so nothing in
`AppLayout.vue` or the nav payload changes.

---

## 5. Screen patterns

Five canonical skeletons. Any new screen must match one of them.

### 5.1 Listing page

Applies to: 52 views, 24 of which do not yet use `ListingPageShell`.

```vue
<template>
    <ListingPageShell
        title="Customers"
        subtitle="One sentence saying what this list is for and how it is ordered."
        :badge="`${total} total`"
    >
        <!-- Exactly ONE btn-primary. Everything else is btn-outline. -->
        <template #actions>
            <BaseButton variant="outline" block-mobile @click="exportCsv">Export CSV</BaseButton>
            <BaseButton variant="primary" block-mobile :to="{ path: '/customers/add' }">
                <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                Add customer
            </BaseButton>
        </template>

        <!-- Every field labelled. Search first, advanced filters collapsed. -->
        <template #filters>
            <div class="listing-filters-row">
                <div class="flex-1 min-w-0 sm:max-w-md">
                    <label class="listing-label" for="cust-search">Search</label>
                    <div class="relative">
                        <MagnifyingGlassIcon
                            class="icon absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                            aria-hidden="true"
                        />
                        <input id="cust-search" v-model="filters.search" type="search"
                               class="form-input-search" placeholder="Name, phone, email…" />
                    </div>
                </div>
                <BaseSelect v-model="filters.status" label="Status" :options="statusOptions"
                            placeholder="Any status" class="w-full sm:w-48" />
                <BaseButton variant="soft" @click="apply">Filter</BaseButton>
            </div>
            <div v-if="activeChips.length" class="flex flex-wrap items-center gap-2 mt-3">
                <span class="text-xs text-slate-500">Active:</span>
                <span v-for="c in activeChips" :key="c.key" class="chip">
                    {{ c.label }}
                    <button type="button" class="chip-remove" :aria-label="`Remove ${c.label}`"
                            @click="clear(c.key)">
                        <XMarkIcon class="w-3 h-3" aria-hidden="true" />
                    </button>
                </span>
            </div>
        </template>

        <BaseTable :columns="columns" :rows="rows" :loading="loading" caption="Customers">
            <template #cell-status="{ row }">
                <BaseBadge :status="row.status" />
            </template>
            <template #actions="{ row }">
                <BaseButton variant="ghost" size="sm" @click="edit(row)">Edit</BaseButton>
                <BaseButton variant="ghost" size="sm" class="text-danger-600 hover:text-danger-800"
                            @click="askDelete(row)">Delete</BaseButton>
            </template>
            <template #empty>
                <EmptyState heading="No customers yet"
                            description="Add your first customer, or adjust the filters above.">
                    <template #action>
                        <BaseButton variant="primary" :to="{ path: '/customers/add' }">Add customer</BaseButton>
                    </template>
                </EmptyState>
            </template>
        </BaseTable>

        <template #pagination>
            <Pagination :pagination="pagination" embedded result-label="customers"
                        @page-change="onPage" />
        </template>
    </ListingPageShell>

    <ConfirmDialog v-model="confirmOpen" title="Delete customer?"
                   :message="`This permanently removes ${target?.name}.`"
                   confirm-label="Delete" :loading="deleting" @confirm="doDelete" />
</template>
```

Rules: one `btn-primary` per page. Filters live in `#filters`, never above the shell.
Deletes always route through `ConfirmDialog` — never `window.confirm`.

### 5.2 Create / edit form page

Applies to: `InvoiceCreateView`, `CustomerFormView`, `TicketCreateView`, `EmployeeEditView`,
`ExpenseFormView`.

```vue
<template>
    <div class="min-h-full bg-slate-50 w-full min-w-0">
        <div class="max-w-3xl mx-auto px-3 sm:px-gutter-lg py-6 w-full min-w-0">
            <div class="mb-6">
                <BaseButton variant="ghost" size="sm" :to="{ path: '/invoices' }" class="-ml-2 mb-3">
                    <template #icon><ArrowLeftIcon class="icon-sm" aria-hidden="true" /></template>
                    Back to invoices
                </BaseButton>
                <h1 class="text-page-title text-slate-900">
                    {{ isEdit ? 'Edit invoice' : 'Create invoice' }}
                </h1>
            </div>

            <div v-if="errorSummary.length" class="callout callout-danger mb-6"
                 role="alert" tabindex="-1" ref="errorSummaryEl">
                <p class="font-semibold">Fix {{ errorSummary.length }} field(s) before saving:</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="e in errorSummary" :key="e.field">{{ e.message }}</li>
                </ul>
            </div>

            <form class="space-y-6" novalidate @submit.prevent="submit">
                <!-- One .form-card per logical group. Hero strip on the first only. -->
                <section class="form-card">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint">Customer</h2>
                        <p class="form-section-desc-mint">Who this invoice is issued to.</p>
                    </div>
                    <div class="form-body space-y-field">
                        <div class="form-grid-2">
                            <BaseInput v-model="form.name" label="Full name" required
                                       :error="errors.name" />
                            <BaseInput v-model="form.email" label="Email" type="email"
                                       :error="errors.email" hint="Used to send the invoice." />
                        </div>
                        <BaseSelect v-model="form.terms" label="Payment terms"
                                    :options="termOptions" :error="errors.terms" />
                    </div>
                </section>

                <section class="form-card">
                    <div class="form-section-head">
                        <h2 class="form-section-title">Line items</h2>
                    </div>
                    <div class="form-body"><!-- … --></div>
                </section>

                <!-- Actions last, sticky is optional but must not cover the last field. -->
                <div class="form-actions rounded-card border border-slate-200">
                    <BaseButton variant="outline" block-mobile :to="{ path: '/invoices' }">
                        Cancel
                    </BaseButton>
                    <BaseButton variant="primary" size="lg" type="submit"
                                block-mobile :loading="saving">
                        {{ isEdit ? 'Save changes' : 'Create invoice' }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>
```

Rules: `novalidate` + an error summary `<div role="alert">` that receives focus on failed
submit; per-field `:error` also set. Submit is the last focusable element. Never disable
the submit button as validation feedback — let it submit and show the summary.

### 5.3 Modal

Every dialog. No exceptions, no `fixed inset-0` in view code.

```vue
<template>
    <BaseModal
        v-model="open"
        title="Log activity"
        description="Record a call, meeting or note against this lead."
        size="md"
        :close-on-backdrop="!dirty"
        @close="reset"
    >
        <form id="log-activity-form" class="space-y-field" @submit.prevent="submit">
            <BaseSelect v-model="form.type" label="Activity type" :options="types" required />
            <BaseInput v-model="form.notes" label="Notes" type="textarea" :error="errors.notes" />
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="open = false">Cancel</BaseButton>
            <BaseButton variant="primary" type="submit" form="log-activity-form"
                        block-mobile :loading="saving">Save activity</BaseButton>
        </template>
    </BaseModal>
</template>
```

Rules:
- `closeOnBackdrop: false` whenever the modal holds unsaved user input.
- The `<form>` lives in the default slot; the submit button in `#actions` links to it with
  `form="…"`. Do not duplicate the form element.
- `BaseModal` supplies the trap, Escape, scroll lock and `aria-labelledby`. Never add
  your own `keydown.esc` listener on top — it will double-fire.

### 5.4 Detail / workspace page

Applies to: `CustomerLeadView` (1634 lines), `TicketDetailView`, `EmployeeDetailView`,
`AppointmentDetailView`.

```vue
<template>
    <div class="min-h-full bg-slate-100 w-full min-w-0">
        <!-- Sticky context bar: back link + record identity + record-level actions -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-sticky">
            <div class="max-w-[1600px] mx-auto px-gutter sm:px-gutter-lg py-3
                        flex flex-wrap items-center justify-between gap-3">
                <BaseButton variant="ghost" size="sm" :to="listRoute" class="-ml-2">
                    <template #icon><ArrowLeftIcon class="icon-sm" aria-hidden="true" /></template>
                    Back to customers
                </BaseButton>
                <div class="flex flex-wrap gap-2">
                    <BaseButton variant="outline" size="sm" @click="openSchedule">Schedule</BaseButton>
                    <BaseButton variant="outline" size="sm" @click="openActivity">Log activity</BaseButton>
                    <BaseButton variant="primary" size="sm" :to="editRoute">Edit</BaseButton>
                </div>
            </div>
        </header>

        <div class="max-w-[1600px] mx-auto px-gutter sm:px-gutter-lg py-4 space-y-4">
            <!-- Identity card: name, contact links, status, stage actions -->
            <BaseCard :padded="false">
                <div class="card-body flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-page-title text-slate-900 break-words">{{ record.name }}</h1>
                        <p v-if="record.business_name" class="card-subtitle">{{ record.business_name }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                            <a v-if="record.phone" :href="`tel:${record.phone}`" class="link">{{ record.phone }}</a>
                            <a v-if="record.email" :href="`mailto:${record.email}`" class="link">{{ record.email }}</a>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <BaseBadge :status="record.stage" dot />
                        <BaseButton variant="success" size="sm" :disabled="record.stage === 'won'"
                                    @click="markWon">Mark won</BaseButton>
                        <BaseButton variant="danger" size="sm" :disabled="record.stage === 'lost'"
                                    @click="askLost">Mark lost</BaseButton>
                    </div>
                </div>
            </BaseCard>

            <!-- 2fr main / 1fr rail. Rail stacks under main below lg. -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                <div class="lg:col-span-2 space-y-4 min-w-0">
                    <BaseCard title="Products"><!-- … --></BaseCard>
                    <BaseCard title="Timeline"><TimelineSection :items="events" /></BaseCard>
                </div>
                <aside class="space-y-4 min-w-0 lg:sticky lg:top-20">
                    <BaseCard title="Details"><!-- … --></BaseCard>
                    <BaseCard title="Appointments"><!-- … --></BaseCard>
                </aside>
            </div>
        </div>
    </div>
</template>
```

Rules: the sticky bar uses `z-sticky`, never a bare `z-20`. The record `<h1>` appears once.
`CustomerLeadView`'s hand-rolled breadcrumb `<nav>` and its own Logout button are removed —
`AppLayout` already renders `Breadcrumbs` and the user menu.

### 5.5 Dashboard

```vue
<template>
    <div class="max-w-7xl mx-auto w-full min-w-0 px-3 sm:px-gutter-lg pb-6 space-y-5 sm:space-y-section">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-page-title sm:text-3xl text-slate-900">Welcome back, {{ name }}</h1>
                <p class="text-sm text-slate-500 mt-1.5">Here’s what’s happening with your pipeline today.</p>
            </div>
        </header>

        <p v-if="scopedToSelf" class="callout callout-info">
            You’re viewing <strong>your</strong> opportunities. Admins see the full organisation.
        </p>

        <!-- Filter bar: collapsible below md, always open from md up -->
        <BaseCard :padded="false"><!-- date range + user select --></BaseCard>

        <!-- KPI row. 1 / 2 / 4 columns. -->
        <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <StatCard label="Total leads" :value="data.total_leads_all ?? 0"
                      :caption="`All stages · ${periodLabel}`" :to="{ path: '/leads' }">
                <template #icon><DocumentTextIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
            <StatCard label="Won products" :value="data.won_product_units ?? 0" tone="success"
                      caption="Units on won line items">
                <template #icon><CheckIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
            <!-- … -->
        </div>

        <!-- Charts: 2-up on lg, full width below. Every chart needs a text summary. -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <BaseCard title="Pipeline by stage">
                <p class="sr-only">{{ pipelineTextSummary }}</p>
                <div class="h-64"><Bar :data="pipelineData" :options="chartOptions" /></div>
            </BaseCard>
        </div>
    </div>
</template>
```

Rules: KPI values are `tabular-nums`. Every `chart.js` canvas is preceded by an
`sr-only` sentence stating the same numbers — a canvas is invisible to a screen reader.
Clickable tiles use `StatCard`'s `to` prop so they are real links, keyboard-reachable and
middle-clickable.

---

## 6. Migration order

Strictly sequential phases; within a phase, files may be done in any order or in parallel.
**Run `npm run build` after every numbered step.** Each step is independently shippable.

### Phase A — additive only, zero visual change (do first)

1. `resources/css/app.css` — add all §1 tokens inside `@theme`, and the §1.2
   `@layer base` additions. Nothing renders differently yet.
2. `resources/css/app.css` — add every §2 component class. Apply the three
   `.listing-badge-*` replacements and the §2.4 table grouped selectors. Add the §2.7
   deprecation comment. **Visual delta expected: badge ring goes `-100` → `-200`. Accept it.**
3. Create `resources/js/components/base/BaseButton.vue` (§3.1).
4. Create `resources/js/components/base/BaseSelect.vue` (§3.2).
5. Create `resources/js/components/base/BaseBadge.vue` (§3.3).
6. Create `resources/js/components/base/BaseCard.vue` (§3.4).
7. Create `resources/js/components/base/StatCard.vue` (§3.7).
8. Create `resources/js/components/base/BaseTable.vue` (§3.5).
9. Create `resources/js/components/base/ConfirmDialog.vue` (§3.6).
10. Create `resources/js/components/base/index.js` (§3.8).

### Phase B — low-risk, self-contained conversions

11. `resources/js/components/SidebarNavIcon.vue` — swap the `v-if` chain for the
    Heroicons lookup (§4). Prop API unchanged; verify every name in
    `app/Support/NavSections.php` resolves and no icon falls through to the default.
12. `resources/js/components/DeleteConfirm.vue` — reimplement as a thin wrapper that
    renders `ConfirmDialog`, keeping its current `confirm`/`cancel` emits so existing call
    sites are untouched. Fixes the missing focus trap everywhere it is used, in one edit.
13. `resources/js/components/base/BaseModal.vue` — replace the inline close `<svg>` with
    `<XMarkIcon class="icon" aria-hidden="true" />`.
14. `resources/js/components/Pagination.vue` — chevrons → `ChevronLeftIcon` /
    `ChevronRightIcon`; buttons → `BaseButton variant="ghost" size="sm"`.
15. `resources/js/components/EmptyState.vue` — add an optional `icon` slot rendered above
    the heading in a `w-12 h-12 rounded-full bg-slate-100 grid place-items-center` chip.
16. `resources/js/components/Toast.vue` + `GlobalToast.vue` — icons → Heroicons
    (`CheckCircleIcon` / `ExclamationTriangleIcon` / `XCircleIcon` / `InformationCircleIcon`);
    containers → `.callout-*`; ensure the live region is `role="status" aria-live="polite"`
    (use `role="alert"` for the danger tone only).

### Phase C — the four simplest listings, proving the pattern

17. `resources/js/views/ProductsView.vue` — already the cleanest listing. Convert to §5.1
    end-to-end: `BaseTable`, `BaseButton`, `BaseBadge`, and move its product modal from the
    inline `fixed inset-0` block onto `BaseModal`. **This file is the reference
    implementation — review it before continuing.**
18. `resources/js/views/AppointmentsView.vue`
19. `resources/js/views/ExpensesView.vue`
20. `resources/js/views/TodaysActivityView.vue`
21. `resources/js/views/EmployeeListView.vue`

### Phase D — remaining hand-rolled modals (37 files)

Convert to `BaseModal`, smallest first. Each is independent.

22. `resources/js/components/IOSInstallModal.vue`
23. `resources/js/components/SmsTemplateModal.vue`
24. `resources/js/components/WhatsappTemplateModal.vue`
25. `resources/js/components/EmailHtmlImportModal.vue`
26. `resources/js/components/CustomerAssignmentModal.vue`
27. `resources/js/components/ScheduleFollowUpModal.vue`
28. `resources/js/components/FollowUpReminder.vue`
29. `resources/js/components/ImportModal.vue`
30. `resources/js/components/SendEmailModal.vue`
31. `resources/js/components/InvoiceSendEmailModal.vue`
32. `resources/js/components/LogActivityModal.vue`
33. `resources/js/components/TicketForm.vue`
34. `resources/js/components/EmployeeForm.vue`
35. `resources/js/components/CustomerForm.vue`
36. `resources/js/components/LeadForm.vue`
37. `resources/js/components/InvoiceForm.vue`
38. `resources/js/components/TemplateBuilder.vue` (largest; do last of the components)
39. Inline modals inside views, in this order:
    `ProductsView` (done in step 17) → `EmployeesView` → `EmployeeGoalsView` →
    `ExpensesView` → `InvoicesView` → `HrView` → `PosSupportTicketsView` →
    `WhatsAppTemplatesView` → `TodaysActivityView` → `CommissionManagementView` →
    `SalesAgentDashboard` → `DashboardView` → `EmailManagementView` →
    `LeadsPipelineView` → `CustomerFormView` → `CustomerLeadView`.
40. `resources/js/components/CommandPalette.vue` — keep its bespoke overlay (it is a
    command palette, not a dialog) but adopt `useFocusTrap`, `.modal-backdrop` and
    `MagnifyingGlassIcon`.
41. `resources/js/layouts/AppLayout.vue` — mobile sidebar overlay → `.modal-backdrop`
    (currently `bg-black/50 z-40`, which collides with `z-dropdown`); user menu →
    `@headlessui/vue` `Menu` + `.popover-panel`; header icons → Heroicons; add
    `<a href="#main" class="skip-link">Skip to content</a>` as the first child and
    `id="main"` on the content wrapper.

### Phase E — the 24 views not yet on `ListingPageShell`

42. Convert in this order (simplest first):
    `SalaryView` → `SalarySlipsView` → `TemplatesView` → `SmsManagementView` →
    `WhatsAppTemplatesView` → `LeadsListView` → `InvoicesView` → `ColdCallingView` →
    `AttendanceReportView` → `CommissionReportView` → `EmployeesView` → `HrView` →
    `WhatsAppManagementView` → `EmailManagementView` → `ReportsView`.

### Phase F — colour and focus normalisation (mechanical, high volume)

43. Replace raw status colours with tokens across `resources/js`:
    `emerald-*` → `success-*`, `amber-*`/`yellow-*` → `warning-*`, `red-*` → `danger-*`,
    `blue-*` → `primary-*`. **Then hand-check every one that lands on text**: `-600` text
    must become `-700` (success/primary) or `-800` (warning/danger). ~700 occurrences.
44. Replace every `focus:ring-*` / `focus:outline-none` with the `focus-visible:` form.
    Heaviest in `CustomersView.vue`, `DashboardView.vue`, `SettingsView.vue`.
45. Replace every `text-slate-400` that wraps readable text with `text-slate-500`.
    Heaviest in `DashboardView.vue` (KPI captions) and `ProductsView.vue`.
46. Remove the one hardcoded hex left in the codebase: `bg-[#22a06b]` /
    `hover:bg-[#1c8a5a]` in `resources/js/views/CustomerLeadView.vue` → `.btn-success`.
47. Replace remaining ad-hoc card class strings with `BaseCard` / `.card`.
    Heaviest in `SettingsView.vue` (22 instances of the same string).

### Phase G — clean up

48. `resources/js/views/SettingsView.vue` + `HrView.vue` — tab strips → `@headlessui/vue`
    `TabGroup` + `.tab` / `.tab-active`; emoji section icons → Heroicons.
49. `resources/js/views/InvoiceCreateView.vue` — the hand-rolled `Teleport` customer
    dropdown (`z-[100]`, no ARIA, no keyboard nav) → `@headlessui/vue` `Combobox`.
50. Delete `.listing-btn-*` and `.form-btn-*` from `app.css` once
    `rg 'listing-btn-|form-btn-' resources/js` returns nothing.
51. Verify: `rg 'fixed inset-0' resources/js` returns only `AppLayout.vue` (sidebar
    overlay), `CommandPalette.vue` and `BaseModal.vue`; `rg '<svg' resources/js` returns
    only `SidebarNavIcon.vue`; `rg 'z-\[' resources/js` returns nothing.

---

## 7. Accessibility acceptance criteria

Every migrated file must satisfy all of these before the step is considered done.

1. **Labels.** Every `<input>`, `<select>` and `<textarea>` has either a `<label for>` or
   comes from `BaseInput`/`BaseSelect`. No placeholder-as-label. Icon-only buttons carry
   `aria-label`.
2. **Focus.** Every interactive element shows a `focus-visible` ring. No
   `outline: none` without a replacement. Tab order follows visual order.
3. **Dialogs.** Rendered by `BaseModal` → focus trap, Escape, scroll lock, focus
   restoration, `role="dialog"`, `aria-modal="true"`, `aria-labelledby`.
4. **Contrast.** Body text ≥ 4.5:1, large text (≥18.66px bold / 24px) ≥ 3:1, UI borders
   and icon glyphs ≥ 3:1. See the §0 table for the tokens that fail.
5. **Colour is never the only signal.** Status badges carry text; `dot` is decorative.
   Chart series need labels or the `sr-only` summary.
6. **Live regions.** Toasts use `role="status" aria-live="polite"` (`role="alert"` for
   errors). Async tables set `aria-busy` while loading.
7. **Tables.** Real `<table>`/`<th scope="col">`; `sr-only` `<caption>`. The mobile card
   layout repeats the field label as visible text.
8. **Touch targets.** ≥ 42px (`btn-md`) for anything a finger uses; `btn-sm` (34px) only
   in desktop-only table rows.
9. **Motion.** No animation is load-bearing; the reduced-motion block in §1.2 must be
   present.
10. **Headings.** Exactly one `<h1>` per route; no level skipped.

---

## 8. Explicit non-goals

**Do not change any of the following.** Touching them puts the change out of contract.

1. **Information architecture.** The 5 sidebar groups (Sales, Service, Marketing,
   People & money, Insights, System), the user dropdown, `Breadcrumbs.vue` and the Ctrl+K
   `CommandPalette` are settled. No routes added, removed, renamed or regrouped.
   `app/Support/NavSections.php` is not edited.
2. **Anything under `app/`, `routes/`, `database/`, `config/`.** This is a
   presentation-layer contract. No controller, model, migration, request or policy changes.
3. **API contracts.** No request or response shape changes. If a view needs data it does
   not have, render the empty/unknown state — do not add an endpoint.
4. **Token names already in use.** `--color-primary-*`, `--color-success-*`,
   `--color-warning-*`, `--color-danger-*`, `--radius-control`, `--radius-card`,
   `--font-sans` and the `z-sticky`/`z-dropdown`/`z-modal`/`z-toast` utilities keep their
   current names and values. §1 only fills gaps.
5. **The primary hue.** Blue `#2563eb` stays. No second brand colour, no gradient
   accents beyond the sidebar's existing `from-primary-600 via-primary-700 to-primary-800`.
6. **Dependencies.** Nothing added to `package.json`. Everything specified here is served
   by `@headlessui/vue`, `@heroicons/vue`, `tailwindcss 4`, `chart.js`, `vue-chartjs`,
   `leaflet` and `vuedraggable`, all already installed.
7. **`BaseModal.vue`'s public API** (`modelValue`, `title`, `description`, `size`,
   `dismissible`, `closeOnBackdrop`, `#header`, `#actions`) and `useFocusTrap`'s signature.
   Extend by composition, not by editing them.
8. **`ListingPageShell.vue`'s slot names** (`#actions`, `#filters`, `#toolbar`, default,
   `#pagination`). Views adapt to the shell, not the other way round.
9. **Dark mode.** Deliberately out of scope. The `--color-surface-*` tokens in §1 are the
   prerequisite groundwork; every surface must route through them before a theme toggle is
   worth building. Adding one now, with ~700 hardcoded light-only colour classes still in
   the views, would ship a broken dark theme. Revisit after Phase F.
10. **`grapesjs` / `EmailVisualEditor.vue` / `TemplateBuilder.vue` internals.** The
    third-party editor chrome is not ours to restyle; only the surrounding modal and
    buttons are in scope.

---

## 9. Definition of done

```
rg 'fixed inset-0'        resources/js   # only AppLayout, CommandPalette, BaseModal
rg '<svg'                 resources/js   # only SidebarNavIcon.vue
rg 'z-\[|z-50|z-40|z-60'  resources/js   # empty (use z-* utilities)
rg 'listing-btn-|form-btn-' resources/js # empty
rg 'focus:ring'           resources/js   # empty (focus-visible: only)
rg 'text-slate-400'       resources/js   # only on decorative / placeholder elements
rg 'emerald-|amber-|\bred-[0-9]|\bblue-[0-9]' resources/js   # empty
npm run build                            # clean
```
