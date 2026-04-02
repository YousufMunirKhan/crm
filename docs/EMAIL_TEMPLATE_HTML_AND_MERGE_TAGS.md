# Email templates: HTML rendering and dynamic merge tags

This document explains **how** the CRM turns stored template data into the final HTML email, **where** each dynamic value comes from, and **how to write** HTML that uses merge tags correctly.

**Related:** [Email Management (bulk sends & lists)](./EMAIL_MANAGEMENT_SPEC.md) · **[AI / copy-paste merge tag pack (tables + prompt block)](./EMAIL_MERGE_TAGS_AI_PROMPT_PACK.md)**

---

## 1. End-to-end flow

1. **Storage** — Each email template row in `email_templates` has:
   - `subject` (string, may contain merge tags)
   - `content` (JSON): optional `preview_line`, optional `skip_brand_footer`, and `sections[]`

2. **When an email is sent or previewed** (customer page, Email Management bulk send, test send, template preview):
   - The app loads the **recipient** (`Customer` model: name, email, phone, leads/products).
   - It loads **Settings** keys (company name, phone, address, website, logo URL, social URLs).
   - **`replaceVariables()`** runs on:
     - The **subject** line
     - **Each section** of the template (text, buttons, and the full string inside `raw_html`)

3. **HTML assembly** — `renderTemplate()` wraps the merged sections in:
   - A full HTML document (`<!DOCTYPE html>`, `<head>` with responsive CSS, `<body>`)
   - An inner `.email-wrapper` div (max-width 600px)
   - Optional hidden **preview line** (preheader) if `content.preview_line` is set
   - Unless `content.skip_brand_footer` is true, a **default CRM footer** (company block + unsubscribe link)

4. **Result** — One HTML string is passed to the mailer or preview iframe. **No server-side template engine** (Blade) runs on your custom HTML; substitution is plain **`str_replace`** on the exact tokens listed below.

---

## 2. Template shapes: builder sections vs raw HTML

| Type | What happens |
|------|----------------|
| `header`, `text`, `image`, `button`, `two_column`, `footer` | Built by the visual builder. Text fields run through `replaceVariables()`; some nodes also use `e()` for HTML escaping where noted in code. |
| `raw_html` | The entire `content.html` string is appended after `replaceVariables()` **without** wrapping each field in `e()`. **You** control HTML. Merge tags in that string are still replaced. **Use only trusted/admin-written HTML.** |

**Import HTML** creates a template with a single `raw_html` section.

---

## 3. Merge tag rules

- **Syntax:** Always double curly braces, no spaces: `{{first_name}}` (not `{{ first_name }}`).
- **Case-sensitive:** Use exactly the names below.
- **Where they work:** Subject, `preview_line`, and any section content including the full `raw_html` body.
- **URLs:** Tags that end in `_url` resolve to **absolute** `https://...` (or `http://...` from `APP_URL`) where applicable. Website and social tags use a helper: if the setting is empty, link tags become `#`; if the value has no scheme, `https://` is prepended.

---

## 4. Complete merge tag reference

### 4.1 Recipient (from the customer / lead data)

| Tag | Source | Notes |
|-----|--------|--------|
| `{{first_name}}` | First word of `customers.name` | Empty if name is empty. |
| `{{customer_name}}` | `customers.name` | Full name. |
| `{{customer_email}}` | `customers.email` | |
| `{{customer_phone}}` | `customers.phone` | |
| `{{prospect_products}}` | Derived from open leads / lead items | Comma-separated product names (not yet “won”). |
| `{{customer_products}}` | Derived from won leads / items | Comma-separated owned products. |

### 4.2 Company (from Settings)

Settings are rows in `settings` (keys below). Exposed in **Settings** in the CRM UI.

| Tag | Setting key | Fallback / behaviour |
|-----|-------------|----------------------|
| `{{company_name}}` | `company_name` | Default text `Switch & Save` if unset. |
| `{{company_phone}}` | `company_phone` | Empty string if unset. |
| `{{company_address}}` | `company_address` | May contain line breaks; rendered as you placed it in HTML. |
| `{{company_website}}` | `company_website` | Normalised for links: `https://` added if missing; empty → `#`. |

### 4.3 Logo and static email assets

| Tag | Meaning |
|-----|--------|
| `{{logo_src}}` | **Settings → logo URL** only. If relative (e.g. `/storage/...`), prefixed with `APP_URL`. **Empty** if no logo is configured. |
| `{{header_logo_url}}` | **Header logo:** if `public/images/email/welcome/main-logo.png` exists, that URL is used first (so a broken Settings logo does not block the image). Otherwise **`{{logo_src}}`**. Otherwise `APP_URL/images/email/welcome/main-logo.png`. Use **`{{logo_src}}`** when you want the Settings logo only. |
| `{{email_welcome_dir_url}}` | Directory URL for `public/images/email/welcome/` (same base as `main-logo.png`). Use for extra images: `{{email_welcome_dir_url}}/partners-row.png`. |
| `{{app_url}}` | `rtrim(config('app.url'), '/')` — no trailing slash. |

**Rendering in HTML**

```html
<!-- Header logo (recommended) -->
<img src="{{header_logo_url}}" alt="Logo" width="200" style="display:block;max-width:200px;height:auto;">

<!-- Only if you insist on Settings logo with no fallback -->
<img src="{{logo_src}}" alt="Logo" width="200" style="display:block;">

<!-- Image shipped in public/images/email/welcome/ -->
<img src="{{email_welcome_dir_url}}/icon-facebook.png" alt="" width="24" height="24">
```

**Important:** Recipients’ mail clients load images from **your public URL**. Production must set **`APP_URL`** (and deploy files under `public/`). `localhost` URLs will not work for real recipients.

### 4.4 Social links (from Settings)

| Tag | Setting key | If empty |
|-----|-------------|----------|
| `{{social_facebook_url}}` | `social_facebook_url` | `#` |
| `{{social_linkedin_url}}` | `social_linkedin_url` | `#` |
| `{{social_instagram_url}}` | `social_instagram_url` | `#` |
| `{{social_tiktok_url}}` | `social_tiktok_url` | `#` |

```html
<a href="{{social_facebook_url}}">Facebook</a>
```

### 4.5 Legal / footer helpers

| Tag | Meaning |
|-----|--------|
| `{{unsubscribe_url}}` | `APP_URL/unsubscribe?email=<url-encoded recipient email>` |
| `{{current_year}}` | Server year when the email is rendered (e.g. `2026`). |

---

## 5. Subject and preheader

- **Subject** — Same `replaceVariables()` as the body. Example: `Hello {{first_name}} — {{company_name}}`.
- **Preview line** (`content.preview_line`) — Merged with the same tags, then injected as a **hidden** preheader div at the top of the HTML (inbox snippet next to subject). It is passed through `e()` for the hidden block only.

---

## 6. Default CRM footer (`skip_brand_footer`)

If `content.skip_brand_footer` is **false** or missing:

- After all sections, the renderer appends **`buildBrandFooter()`**: company name, address, phone, website link, social text links, registration/VAT if set, and an **unsubscribe** link.

If **`skip_brand_footer` is true** (recommended for full branded HTML emails):

- That block is **omitted**. Your `raw_html` should include your own footer and **`{{unsubscribe_url}}`** where required for marketing mail.

---

## 7. API reference (for integrators)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/email-templates/merge-tags` | JSON list of all tags + `html_examples` snippets (admin). |
| POST | `/api/email-templates/import-html` | Multipart: create template from `html_file` + metadata. |
| POST | `/api/email-templates/preview-html` | JSON `content` → full rendered HTML (sample customer, admin). |

Implementation lives mainly in:

- `App\Http\Controllers\EmailTemplateController` — send, preview, import, `replaceVariables`, `renderTemplate`, `renderSection`
- `App\Http\Controllers\EmailManagementController` — bulk preview/send uses the same merge + render logic for consistency

---

## 8. Import HTML checklist

1. Use **inline CSS** for critical layout (many clients ignore `<style>` in `<head>` when you extract body only).
2. Put merge tags **verbatim** in attributes: `src="{{header_logo_url}}"`, `href="{{company_website}}"`.
3. **Scripts** are stripped on import (`<script>` removed).
4. Prefer **Extract `<body>` only** for full pages; otherwise you may nest invalid HTML inside the CRM wrapper.
5. **Preview vs real inboxes:** The template builder preview uses a normal browser (Chrome), so **flexbox**, **gap** / **row-gap** / **column-gap**, and very new properties (e.g. `white-space-collapse`, `text-wrap-mode`) can look perfect there but **break in Outlook** and some webmail. For multi-column rows (cards, offer + CTA, footer logo | URL | icons), use nested **`<table role="presentation">`** layouts with **`<tr><td>`**, not `display:flex`. See `resources/email-templates/welcome-grapes-outlook-safe.html` for a table-based version of a typical welcome layout.

---

## 9. Quick copy-paste snippets

```html
<p>Hello {{first_name}},</p>
<img src="{{header_logo_url}}" alt="" width="220" style="display:block;">
<a href="{{company_website}}">Visit our website</a>
<a href="{{social_facebook_url}}">Facebook</a>
<a href="{{social_linkedin_url}}">LinkedIn</a>
<a href="{{social_instagram_url}}">Instagram</a>
<a href="{{social_tiktok_url}}">TikTok</a>
<a href="{{unsubscribe_url}}">Unsubscribe</a>
```

---

## 10. Keeping this doc in sync with code

The canonical list of tags is defined in code:

- `EmailTemplateController::emailMergeTagDefinitions()`
- The same tokens must appear in `replaceVariables()` search/replace arrays (and the duplicate logic in `EmailManagementController`).

If you add a new tag in PHP, add it to `emailMergeTagDefinitions()`, `variableKeysFromMergeDefinitions()` (used on import), and update this document.
