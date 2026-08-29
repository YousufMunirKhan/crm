# Switch & Save CRM — Module Status

Verified against the code, not from memory. Last reviewed: 2026-08-29.

> The previous version of this file was wrong in both directions — it listed
> shipped modules as "not implemented" and unimplemented ones as complete.
> Anything below marked ✅ has been checked in the source or is covered by a test.

## Architecture

| Surface | Stack | Path |
|---|---|---|
| Field / sales | Vue 3 SPA (installable PWA) | `/` |
| Back office | Filament 4 | `/admin` |
| Integrations | Laravel API (Sanctum) | `/api` |

Both surfaces share one `manifest.json` with `scope: "/"`, so a single
home-screen install covers them.

---

## ✅ Complete

| Module | Notes |
|---|---|
| Authentication & roles | Sanctum tokens for the API, session auth for the panel. Password reset works end to end (the notification previously threw `RouteNotFoundException` — there was no `password.reset` route). |
| Authorisation | 16 policies in `app/Policies`, registered explicitly and covered by `PolicyEnforcementTest`. Route middleware for roles, staff/portal separation and nav sections. |
| CRM — leads & customers | Pipeline stages, assignment, activities, follow-ups, soft deletes with cascade to activities and line items. |
| Product catalogue | Name, SKU, sale price, cost price, tax rate, currency, unit, image. Cross-sell/upsell links are writable (they previously had a read path only). |
| Invoicing | Payments ledger is the single source of truth for `amount_paid`. Race-free numbering. VAT rate from Settings. **Lead → invoice conversion** carries line items and product links. |
| Tickets & POS support | Priorities, messages, attachments, assignees. |
| HR | Attendance, salaries, expenses, targets, documents. |
| Commission | Allocation workspace, monthly reports, PDFs. Grouped on product id rather than name. |
| Communications | Email / SMS / WhatsApp send, templates, WhatsApp Cloud API with required signature verification. |
| Consent & suppression | `contact_consents` covers email, SMS and WhatsApp. STOP keyword handling, tokenised unsubscribe, `List-Unsubscribe` headers, GDPR export/erase command. |
| Reporting | Executive, funnel, geo, communications, agents, targets. Revenue is de-duplicated across won leads and invoices. Product performance report with margin. |
| Import / export | CSV / XLSX via maatwebsite/excel 4, covered by `ExportAndPdfTest`. |
| Settings | Company, branding, SMTP, SMS, WhatsApp, Facebook, cold calling, PWA. |
| Back-office panel | 15 Filament resources across 5 navigation groups, 7 relation managers. |

## ⚠️ Partial

| Module | What's missing |
|---|---|
| Notifications | Table, endpoints and events exist; **no producers write to it**. Build or remove. |
| Customer portal | Backend complete (`CustomerPortalController`); no frontend. |
| Import / export UI | Backend and API complete; the SPA has no screen for it. |
| WhatsApp inbox | Conversations are stored and retrievable by API; no UI reads them. |
| Marketing | Bulk send, filters, templates and open tracking work. No campaign object, scheduling, click tracking or saved segments. |
| SLA | Due dates are computed and displayed; no breach detection or escalation. |
| Geo analytics | Backend is fast and correct; no map in the UI. |

## ❌ Not implemented

- Public lead capture (no unauthenticated lead endpoint)
- UTM / referrer attribution columns
- A/B testing, lead scoring
- Bounce and complaint webhook processing
- Multi-currency on invoices (`currency` is a single-value enum)

---

## Testing

```bash
php artisan test
```

Covers suppression and consent, revenue de-duplication, lead → invoice
conversion, invoice overdue transitions, export/PDF rendering, Filament panel
access, every resource page, relation managers, and policy enforcement.

CI runs tests, the frontend build and a PHP lint sweep on every push
(`.github/workflows/ci.yml`).
