# Cold calling sweep runner

Finds businesses around the places we already have customers, and puts them in
the CRM under **Cold calling → Saved contacts**.

The CRM picks the areas; this folder does the scraping. It has to run on a PC
because the scraper is a Docker container and the live host cannot run Docker.

```
CRM (Cold calling → Around our customers)        Office PC (this folder)
  areas ranked from customers + leads
  "Queue sweep"  ───────────────── queue ──────▶  php sweep.php
                                                    └─ Google Maps scraper (Docker)
  Saved contacts ◀──────────── listings ─────────  posts rows back
```

## One-time setup

1. Install and start **Docker Desktop**.
2. On the CRM server, add a long random key to `.env` and clear the config cache:
   ```
   COLD_CALLING_SWEEP_KEY=<long random string>
   ```
3. In this folder, copy `.env.example` to `.env` and fill in `CRM_URL` and the
   same key as `CRM_KEY`.

## Each time

1. In the CRM: **Cold calling → Around our customers**, tick areas, **Queue selected**.
2. On the PC, with Docker Desktop running:
   ```
   php scripts/gmaps-sweep/sweep.php
   ```
   It starts the scraper container if it is not up, works through the queue one
   area at a time, and stops when the queue is empty. `--max=3` stops after three.

The first run downloads the scraper image and a browser (a few hundred MB).

## Things to know

- **Rate limits.** This searches Google Maps for real, from this PC's IP. Many
  areas back to back, a high depth, or many business types can get the IP
  rate-limited for a few hours; jobs then fail or come back empty. The runner
  stops after two failed areas in a row and leaves the rest queued. Start with
  a handful of areas at depth 5. `PROXIES` in `.env` is the fix for volume.
- **Nothing is saved twice.** Listings are matched on Google's place id, the
  same id the Google Places search uses, so both routes share one list.
- **Existing customers are not offered as cold calls.** A listing whose phone
  number matches a customer is linked to that customer.
- **Before anybody dials.** New contacts arrive as `entity_type = unknown` and
  `tps_status = unscreened`. Sole traders and partnerships are not covered by
  the corporate-subscriber exemption, and numbers must be screened against
  TPS/CTPS before a marketing call.
- Scraping Google Maps is against Google's terms of service. Keep the volume
  modest and treat the output as leads to verify, not data to resell.

## Without the runner

A CSV the scraper already wrote can be loaded by hand at the bottom of the
**Around our customers** tab.

The scraper is [gosom/google-maps-scraper](https://github.com/gosom/google-maps-scraper)
(MIT), set up as in [google-maps-scraper-kit](https://github.com/Mahanaicoach/google-maps-scraper-kit).
