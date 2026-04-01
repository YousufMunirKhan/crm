# Email merge tags — copy-paste pack for AI template authoring

Use this file when you ask an AI (or a designer) to build **HTML email templates** for this CRM. Paste the **“Prompt for your AI”** block below into ChatGPT, Claude, etc., together with your brand brief.

**Canonical technical reference (rendering flow, import, API):** [EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md](./EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md)

---

## Rules the AI must follow

1. **Exact syntax:** Double curly braces, **no spaces** inside: `{{first_name}}` — not `{{ first_name }}`.
2. **Case-sensitive:** Use the tags **exactly** as listed (lowercase, underscores).
3. **Where they work:** Subject line, preview line, and **raw HTML body** (and builder text fields). The server does simple text replacement — not Blade/Twig.
4. **URLs:** Tags ending in `_url` become **absolute** `https://…` URLs when sent (or `#` if the setting is empty, for website/social).
5. **Images:** Put merge tags **inside** `src="…"` or `href="…"` as shown. Recipients load images from **your live CRM URL** (`APP_URL`); `localhost` does not work for real sends.
6. **Footer:** If the template includes its own footer and unsubscribe link, the template can use **“skip default CRM footer”** on import. Always include `{{unsubscribe_url}}` in marketing emails.

---

## Full tag list — HTML usage, meaning, and example output

After merge, values come from **the recipient (customer)** and **Settings** in the CRM. Examples below are **illustrative**.

| Merge tag | Typical HTML usage | What it is | Example after merge (illustrative) |
|-----------|-------------------|------------|-------------------------------------|
| `{{first_name}}` | `<p>Hi {{first_name}},</p>` | First word of contact name | `Jane` |
| `{{customer_name}}` | `<p>{{customer_name}}</p>` | Full contact name | `Jane Smith` |
| `{{customer_email}}` | `<a href="mailto:{{customer_email}}">Reply</a>` | Contact email | `jane@example.com` |
| `{{customer_phone}}` | `<a href="tel:{{customer_phone}}">Call us</a>` | Contact phone | `+44 7700 900000` |
| `{{prospect_products}}` | `<p>Interested in: {{prospect_products}}</p>` | Comma-separated products (pipeline / not won) | `Card Machine, Funding` |
| `{{customer_products}}` | `<p>Your products: {{customer_products}}</p>` | Comma-separated products (won) | `EPOS` |
| `{{company_name}}` | `<strong>{{company_name}}</strong>` | Company name (Settings) | `Switch & Save` |
| `{{company_phone}}` | `<a href="tel:{{company_phone}}">📞 {{company_phone}}</a>` | Company phone (Settings) | `0333 038 9707` |
| `{{company_address}}` | `<p>{{company_address}}</p>` | Company address; line breaks allowed | `1 High Street` |
| `{{company_website}}` | `<a href="{{company_website}}">Website</a>` | Website URL; `https://` added if missing | `https://switch-and-save.uk` |
| `{{header_logo_url}}` | `<img src="{{header_logo_url}}" alt="Logo" width="200">` | Header logo URL (welcome asset **or** Settings logo **or** fallback path) | `https://your-crm.com/images/email/welcome/main-logo.png` |
| `{{logo_src}}` | `<img src="{{logo_src}}" alt="Logo" width="110">` | Settings logo **only** (empty if not set) | `https://your-crm.com/storage/.../logo.png` or empty |
| `{{email_welcome_dir_url}}` | `<img src="{{email_welcome_dir_url}}/partners.png" alt="Partners">` | Base URL for files in `public/images/email/welcome/` | `https://your-crm.com/images/email/welcome` |
| `{{app_url}}` | `<a href="{{app_url}}/login">Login</a>` | Site base URL, no trailing slash | `https://your-crm.com` |
| `{{social_facebook_url}}` | `<a href="{{social_facebook_url}}"><img src="…" alt="FB"></a>` | Facebook URL or `#` if empty | `https://facebook.com/...` or `#` |
| `{{social_linkedin_url}}` | `<a href="{{social_linkedin_url}}">LinkedIn</a>` | LinkedIn URL or `#` | `https://linkedin.com/...` or `#` |
| `{{social_instagram_url}}` | `<a href="{{social_instagram_url}}">Instagram</a>` | Instagram URL or `#` | `https://instagram.com/...` or `#` |
| `{{social_tiktok_url}}` | `<a href="{{social_tiktok_url}}">TikTok</a>` | TikTok URL or `#` | `https://tiktok.com/...` or `#` |
| `{{unsubscribe_url}}` | `<a href="{{unsubscribe_url}}">Unsubscribe</a>` | Unsubscribe link for this recipient | `https://your-crm.com/unsubscribe?email=jane%40example.com` |
| `{{current_year}}` | `<p>© {{current_year}} {{company_name}}</p>` | Year when the email is sent | `2026` |

---

## Quick copy-paste HTML snippets (valid in this CRM)

```html
<!-- Greeting -->
<p style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:16px;">Hello {{first_name}},</p>

<!-- Main logo (recommended) -->
<img src="{{header_logo_url}}" alt="" width="220" style="display:block;max-width:220px;height:auto;border:0;">

<!-- Optional: footer logo (Settings only — may be empty) -->
<img src="{{logo_src}}" alt="" width="120" style="display:block;max-width:120px;height:auto;border:0;">

<!-- Asset next to main logo on server: public/images/email/welcome/partners.png -->
<img src="{{email_welcome_dir_url}}/partners.png" alt="Partners" width="500" style="display:block;max-width:100%;height:auto;border:0;">

<!-- Links -->
<a href="{{company_website}}" style="color:#004aad;font-weight:bold;">Visit our website</a>
<a href="tel:{{company_phone}}">Call {{company_phone}}</a>
<a href="{{unsubscribe_url}}" style="color:#64748b;font-size:12px;">Unsubscribe</a>

<!-- Social row -->
<a href="{{social_facebook_url}}">Facebook</a> ·
<a href="{{social_linkedin_url}}">LinkedIn</a> ·
<a href="{{social_instagram_url}}">Instagram</a> ·
<a href="{{social_tiktok_url}}">TikTok</a>

<!-- Legal line -->
<p style="font-size:12px;color:#64748b;">© {{current_year}} {{company_name}} · {{company_address}}</p>
```

---

## Prompt for your AI (copy everything inside the box)

```
You are building a transactional/marketing HTML email for a Laravel CRM.

REQUIREMENTS:
- Output a single HTML document: <!DOCTYPE html>, <html>, <head> with <meta charset="UTF-8"> and viewport, <style> for layout (email-safe: prefer tables for main structure; use inline styles where critical).
- Use ONLY these merge tags for dynamic content. Copy them EXACTLY (double braces, no spaces, lowercase):

  {{first_name}} {{customer_name}} {{customer_email}} {{customer_phone}}
  {{prospect_products}} {{customer_products}}
  {{company_name}} {{company_phone}} {{company_address}} {{company_website}}
  {{header_logo_url}} {{logo_src}} {{email_welcome_dir_url}} {{app_url}}
  {{social_facebook_url}} {{social_linkedin_url}} {{social_instagram_url}} {{social_tiktok_url}}
  {{unsubscribe_url}} {{current_year}}

- Put tags verbatim in HTML, e.g. src="{{header_logo_url}}", href="{{company_website}}".
- Include a visible unsubscribe link for marketing: <a href="{{unsubscribe_url}}">Unsubscribe</a>.
- Do not use JavaScript. Do not use {{ spaces }} inside tags.

Now, given my brand details and layout request below, produce the full HTML email.
```

**After the box:** Paste your own instructions (colours, sections, tone, mobile behaviour, etc.).

---

## Minimal starter template (copy and customise)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email</title>
  <style>
    body { margin:0; padding:0; background:#f4f4f4; font-family: Arial, Helvetica, sans-serif; }
    .wrap { max-width: 600px; margin: 0 auto; background: #ffffff; }
  </style>
</head>
<body>
  <div class="wrap" style="padding:24px;">
    <img src="{{header_logo_url}}" alt="{{company_name}}" width="200" style="display:block;max-width:200px;height:auto;margin:0 auto 24px;">
    <p style="margin:0 0 12px;font-size:18px;font-weight:bold;">Hello {{first_name}},</p>
    <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#333;">
      Thank you for choosing {{company_name}}. We’re here to help at {{company_phone}}.
    </p>
    <p style="margin:0 0 24px;">
      <a href="{{company_website}}" style="display:inline-block;background:#004aad;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">Visit website</a>
    </p>
    <p style="margin:0;font-size:12px;color:#64748b;">
      <a href="{{unsubscribe_url}}" style="color:#64748b;">Unsubscribe</a> · © {{current_year}} {{company_name}}
    </p>
  </div>
</body>
</html>
```

---

## Keeping this pack accurate

Tags are defined in `App\Http\Controllers\EmailTemplateController::emailMergeTagDefinitions()` and replaced in `replaceVariables()`. If code adds a tag, update this file and [EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md](./EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md).
