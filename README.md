# Khawchin ☔

> *Ruah sur beisei a nih hun tur chiah tarlan a ni* — "shows only the hours when heavy rain is expected."

Khawchin is a tiny, dependency-free web app that answers one question for **Aizawl, Mizoram, India**: *which hours will it actually rain?* It loads the hourly forecast pages for **today and tomorrow** from **AccuWeather** and **Weather Underground**, scrapes the rain chance and amount for every hour in the browser, and highlights the hours with a **≥ 50 % chance of rain** — alongside an "all raw data" view.

No build step, no frameworks, no API keys.

## How it works

Weather sites block cross-origin browser requests and serve bot-check pages to plain HTTP clients, so page fetching is proxied and done with a browser-like fingerprint:

1. The single-page app (`weatherapp/index.html`) builds the source URLs (today + tomorrow) for each of the two forecast providers.
2. Each URL is fetched through a small **PHP CORS proxy** (`index.php`) as `GET <proxy>/?url=<encoded-url>`. The proxy requests the page with cURL impersonating desktop Chrome 122 (full header set) so the sites return their real HTML.
3. The returned HTML is parsed **in the browser** with `DOMParser`, and for every hour the precipitation probability and rain amount are extracted.
4. Results are rendered as four tables — filtered and raw — refreshed by a button (and automatically on page load).

```
┌─────────────────┐  GET /?url=<target>  ┌──────────────┐   HTTPS, desktop-Chrome
│ weatherapp/     │ ───────────────────▶ │  index.php   │ ─────────────────────▶ AccuWeather hourly
│ index.html      │ ◀─────────────────── │ (PHP + cURL) │ ─────────────────────▶ Wunderground hourly
│ (static SPA)    │  HTML + CORS *       └──────────────┘
└─────────────────┘
        │ DOMParser → tables
        ▼
   AccuWeather │ Wunderground │ AccuWeather (All) │ Wunderground (All)
```

## Project structure

```
.
├── weatherapp/
│   └── index.html     # the entire frontend: markup, styles, fetch/proxy logic, parsers
├── index.php          # CORS proxy — deploy on a PHP host (not published by Firebase)
├── firebase.json      # Firebase Hosting: public dir + SPA rewrite
├── .firebaserc        # Firebase project: rainfall-sensor (hosting site: khawchin)
└── .gitignore
```

## Data sources

| Source | Today | Tomorrow | Parsed from |
|---|---|---|---|
| AccuWeather | `accuweather.com/en/in/aizawl/189561/hourly-weather-forecast/189561` | same URL with `?day=2` | `.accordion-item.hour` cards — `.precip` percentage and the `Rain` value in the detail panel |
| Wunderground | `wunderground.com/hourly/in/aizawl` | `.../date/<YYYY-MM-DD>` (date computed client-side) | `.table-scroll` hourly table — time, precip % and amount columns |

**Filtering rules** (applied to the two main cards; the "All Raw Data" cards skip the filter):

- Keep an hour only if the precipitation probability is **≥ 50 %**.
- AccuWeather: additionally requires a `Rain` amount for that hour; the value is shown as-is.
- Wunderground: additionally requires a parseable amount, which is **converted from inches to mm** (× 25.4, rounded to 2 decimals).

## UI

- Four cards: **AccuWeather**, **Wunderground**, and their **(All Raw Data)** twins — each a *Time / Precip / Rain* table.
- Status badge per card: `loading… → fetching… → "N hours"` (green) · `No data / rain` (amber) · `fetch failed — <reason>` (red). A response under 500 characters is treated as a likely bot-check page and rejected.
- A refresh button (disabled while fetching), an "Updated hh:mm:ss" timestamp, and the four sources load **in parallel**.
- Responsive: two-column grid, collapsing to one column below 900 px.

## The proxy (`index.php`)

- Sends `Access-Control-Allow-Origin: *` and answers `OPTIONS` preflights with 200.
- Reads the target from the `?url=` query parameter (400 if missing); prefixes `https://` when the scheme is omitted.
- cURL settings: follows up to 5 redirects, 20 s timeout, HTTP/1.1, automatic decompression, and a full **desktop Chrome 122** header set (spoofed `User-Agent`, `Sec-Ch-Ua*`, `Sec-Fetch-*`, …).
- Echoes the upstream body with the upstream HTTP status code; responds 500 with the cURL error on transport failure.

> ⚠️ This is an **open, unauthenticated proxy** (any origin, any `url`). Fine for personal use — restrict allowed origins/hosts before exposing it publicly.

## Requirements

- Any static host for the frontend — the repo is wired for **Firebase Hosting** (CLI required to deploy).
- PHP with the **cURL extension** for the proxy.
- Nothing else — the frontend has zero dependencies and no build step.

## Local development

1. Map the dev proxy host to loopback in `/etc/hosts`:
   ```
   127.0.0.1   cors.test
   ```
2. Serve the **repo root** on port 80 so `index.php` handles `/`:
   ```
   sudo php -S cors.test:80
   ```
3. Open the app over **http** (e.g. `http://cors.test/weatherapp/index.html`). Pages served over `http:` use the `cors.test` proxy; pages served over `https:` use the production proxy — that choice lives in the `endpoint` logic at the top of `weatherapp/index.html`.

## Deployment

**Frontend (Firebase Hosting):**

```bash
npm install -g firebase-tools
firebase login
firebase deploy --only hosting
```

`firebase.json` publishes the `weatherapp/` directory to hosting site **`khawchin`** in project **`rainfall-sensor`** and rewrites all routes to `/index.html`.

**Proxy (PHP host):** deploy `index.php` so it answers at `https://khawchin.kawnek.com/?url=...` (hard-coded as the production endpoint in `index.html`). If your proxy lives elsewhere, update the `endpoint` constant near the top of the `<script>` in `weatherapp/index.html`.

## Configuration knobs

All in `weatherapp/index.html`:

| Knob | Where | Default |
|---|---|---|
| Proxy host | `endpoint` constant | `khawchin.kawnek.com` (https) / `cors.test` (http) |
| Provider URLs & city | `SOURCES.*.buildUrls()` | Aizawl (AccuWeather id `189561`, Wunderground `/hourly/in/aizawl`) |
| Rain threshold | parsers (`precip <= 49` / `percent < 50` filtered out) | 50 % |
| Fetch strategy chain | `SOURCES.*.fetchVia` + `FETCH_STRATEGIES` | `["proxy"]` |
| Fetch timeout | `fetchWithStrategies` options | 20 s |

## Caveats

- Scraping relies on live DOM markers (`.hourly-wrapper`, `.accordion-item.hour`, `.table-scroll`, …); a site redesign will break the parsers.
- Even with the Chrome fingerprint, the sites may occasionally return consent/bot-check pages — failed cards are shown as `fetch failed` and a retry (Refresh) usually helps.
- Data is scraped forecast HTML, not an official API — informational only.
