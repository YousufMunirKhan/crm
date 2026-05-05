# Cold calling (Marketing) — postcode leads, save/search, logs, export

## Product vision (your idea, captured)

1. Under **Marketing**, user opens **Cold calling** (dedicated menu label).
2. User enters a **UK postcode** (and later: radius / types if you want).
3. System returns **everything we can legally and reliably get**: at minimum **business name, phone, address, website**; **email** only when enrichment, manual entry, or a future scraper/adapter finds it (Google Places does not supply email).
4. Data is **saved in the database** (not just a one-off screen): you **accumulate** a library of businesses discovered over time.
5. **Per postcode**: you can open a postcode (or area) and see **existing saved rows**; new runs **merge** in without duplicating the same venue (global dedupe, e.g. Google `place_id`).
6. **Search** across all saved cold-calling records (by name, postcode, phone, etc.).
7. **Export** (CSV/XLSX) and optional **promote to prospect**.
8. **Full logs / audit**: who triggered each run, when, which postcode, how many new vs duplicate vs errors (and optionally per-user export actions).

## What you asked for (technical summary)

User enters a **UK postcode** → the CRM returns **businesses** with **name, phone, and email when available**, in a clear table, with **export**, **persistent storage**, **search**, **audit logs**, and **no duplicate venue** across runs.

## Recommended primary source: Google Places API (not scraping Google)

**Suggestion:** use the **Google Places API (New)** for discovery. It is the compliant, stable way to get “what’s near this postcode” without scraping `google.com` / Maps HTML (which breaks often and conflicts with Google’s terms).

### Typical flow

1. **Postcode → coordinates**: UK postcode to lat/lng via **Geocoding API** or **Address Validation API** (or Places **Autocomplete** restricted to UK if you prefer).
2. **Nearby businesses**: **Places API (New) — Nearby Search** (or **Text Search** with query like `restaurants in SW1A 1AA`) with:
   - `location` + `radius` (meters), and/or
   - `includedTypes` / primary type filters (e.g. `restaurant`, `cafe`, `store`, `bakery`, etc.).
3. **Pagination**: follow `nextPageToken` until you hit a sensible cap (cost + UX).
4. **Richer fields per place**: **Place Details** for each `place_id` to get **formatted phone**, **website URI**, **formatted address**, **display name**, **types**.

### Critical limitation (email)

- **Google Places does not provide business email** for listings in the normal Places product.
- So the CRM should **show phone + website + name + address** from Google, and treat **email** as:
  - **empty** unless you add a separate step, or
  - **optional enrichment** (e.g. parse contact page from `websiteUri` — slower, fragile, legally sensitive), or
  - **manual** entry / future third-party enrichment service.

If marketing **requires** email for every row, you must combine **Places (for discovery)** with **another lawful source** or **opt-in lists**—not Google alone.

## Deduplication (fits Google perfectly)

- Use Google’s **`place_id`** as the stable unique key (store it in `cold_calling_contacts.place_id` with a **unique index**).
- Re-running the same postcode or overlapping radius **skips** existing `place_id`s → “never show again” in your database.

## Compared to custom scraping

| Approach | Pros | Cons |
|----------|------|------|
| **Places API** | Allowed by ToS, stable schema, `place_id` dedupe | Billing, quotas, **no email** |
| **HTML scraping** | Might find extra fields on some sites | Blocks, CAPTCHA, legal/ToS risk, high maintenance |

**Practical hybrid:** **Places API as default** in the Marketing screen; keep the earlier **adapter pattern** only if you later add specific **non-Google** directory sites (each adapter maintained separately).

## UI flow (Cold calling screen)

- Marketing → **Cold calling**.
- **Tab or step 1 — New run**: postcode (+ radius, place types when you add them) → **Run** → queued job → progress/status.
- **Tab or step 2 — Saved data**: default list **filterable by postcode** (“show me everything we already have for `M1 1AE`”) + **global search** (name, phone, postcode).
- Row detail / inline edit: **manual email** or notes; optional **Run enrichment** on selected rows (website → email attempt), if you enable it later.
- **Export** CSV/XLSX (whole filtered set or selection); optional **Promote to prospect** on [`Customer`](app/Modules/CRM/Models/Customer.php) with `source` tag.
- **Activity / logs** page or panel: table of **discovery runs** with `user_id`, postcode, started/finished, `new_count`, `duplicate_count`, `error_message`, link to Google billing-friendly **run id**.

## APIs you need (Google Cloud)

Enable **billing** on a Google Cloud project and create an API key (restrict by HTTP referrer for SPA if any client-side use; preferably **server-side only** from Laravel).

| Need | Google product (typical) | Purpose |
|------|---------------------------|---------|
| Postcode → map point | **Geocoding API** (or **Address Validation API**) | UK postcode → lat/lng for “nearby” search |
| Find businesses near that point | **Places API (New)** — **Nearby Search** and/or **Text Search** | List venues with `place_id`, rough location |
| Name, phone, website, address | **Places API (New)** — **Place Details** (per `place_id`) | Fill the fields your reps need for calling |

**You do not need a separate “Maps JavaScript API”** for this backend-only flow unless you later embed a map picker in the UI.

**Email:** not from the above; add optional **enrichment** (your server fetching business websites — careful with scale and legality) or **scraper adapters** to other sites, or manual entry.

## Scrapers in this design

- **Do not scrape Google Maps HTML**; use the **Places API** instead.
- **Optional**: pluggable **directory scrapers** (non-Google sites) as separate adapters, each with maintenance and ToS risk, writing into the **same** `cold_calling_contacts` table with a `source` field (`google_places`, `manual`, `yell`, etc.) and the same **dedupe** rules where possible (`place_id` for Google; other sites may use URL id or phone hash).

## Cold calling vs cold email — what to build for your team

**Cold calling (easiest to support well)**  
- **Google Places** already gives **business phone numbers** and **trading names** for many UK venues—good raw material for a dialler or call list.  
- In the CRM: export columns **name, phone, address, website, postcode, types**; optional **“Promote to prospect”** so reps work from **Follow-ups / Leads** with the same dedupe rules (`place_id`).

**Cold email (harder; Places alone is not enough)**  
- You will **not** get verified marketing emails from Places. Realistic options to layer on:
  1. **Website guess / crawl (enrichment)** — try common patterns (`info@`, `hello@`, `contact@`) or parse a public contact page **only** when a website URL exists; mark field as **unverified**; expect gaps and breakage when sites change.  
  2. **Manual research** — rep fills email in CRM after a call or from the site.  
  3. **Licensed B2B data** — purchased lists or a data vendor API (contract + compliance is on you).  
- Product-wise: keep **email** nullable; add **email_source** (`enrichment`, `manual`, `import`, empty) and **do_not_email** / **unsubscribed** flags so you do not blast the same person twice.

**Compliance (UK — high level only, not legal advice)**  
- **Cold calls** to businesses are still regulated (e.g. CTPS/TPS for some numbers, timing, identification). Your ops should follow your dial process.  
- **Cold email** to individuals is tightly constrained under **PECR**; **corporate** outreach has different considerations but is not “anything goes.” **Get brief legal guidance** before scaling email; the CRM can still store **source**, **consent notes**, and **suppression** to support compliant processes.

**Suggested CRM additions for “give to my people”**  
- **Export presets**: “Call list” CSV (name, phone, address) vs “Email prep” CSV (name, website, email if any).  
- **Assign / territory** (optional): tag rows by campaign or rep.  
- **Promote to prospect** with `source=local_discovery` so activity lives in your existing pipeline.  
- **Suppression**: global dedupe by phone/email + respect your existing unsubscribe / DNC if you link discovered contacts to customers.

## Data model notes (save + search + logs)

- **`cold_calling_contacts`** (or `local_discovery_contacts`): one row per business globally; columns include `place_id` (unique nullable), normalised `postcode` / area, `phone`, `email`, `email_source`, address, `website`, `name`, `types` JSON, `source` (`google_places`, `manual`, future scraper id), `first_seen_at`, `last_seen_at`; link **which postcodes** found this place via a join table **`cold_calling_contact_postcodes`** (`contact_id`, `postcode`, `run_id`, `first_seen_at`) if you want a clear audit trail per area without duplicating the business row.
- **`cold_calling_runs`**: each “Run” click — `user_id`, input postcode, radius, status, `new_count`, `duplicate_count`, errors, timestamps — **who did what, when, for which postcode**.
- **Search**: indexes on `postcode` (and join table), `phone`, `name`; optional full-text later.
- **Optional `cold_calling_export_logs`**: `user_id`, filter snapshot, row count, `created_at` for “who exported what”.

## Config / ops

- **`.env`**: `GOOGLE_MAPS_API_KEY` — restrict the key to **Geocoding API** + **Places API (New)** only; use server-side calls from Laravel only where possible.
- **Rate limits & cost**: batch Place Details with concurrency limits; cap results per run in settings.
- **UK compliance**: PECR/GDPR still apply to **outbound** email/SMS using stored contacts—product can store `source`, `place_id`, timestamps for audit.

## Implementation todos (aligned with this approach)

1. Migrations/models: **`cold_calling_runs`** (audit) + **`cold_calling_contacts`** (saved businesses) with **`place_id` unique**, postcode + phone indexes for search; nullable **email** + **email_source** + **do_not_email** (or reuse customer flags after promote).
2. Service: `GooglePlacesDiscoveryService` (geocode postcode → nearby search → details loop).
3. `RunColdCallingDiscoveryJob` + API + Vue **Cold calling** view under Marketing: new run, saved list + filters + search, run status polling.
4. Export presets (**call list** vs **email prep**) + optional **export log** + optional promote to prospect with `source=cold_calling` / `local_discovery`.
5. (Optional later) Enrichment job for website → contact email; **scraper adapters** for non-Google directories — feature-flagged; legal review before enabling.

---

*Google Places is the primary API set; scrapers are optional add-ons for non-Google sources. Email expectations must stay realistic unless enrichment or licensed data is added.*
