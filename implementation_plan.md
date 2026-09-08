# HelioSense Dashboard — Implementation Plan

## Background

The existing system has a working ESP32 → WiFi → PHP API → MySQL pipeline at `C:\xampp\htdocs\heliosense_api\`. The backend stores sensor readings in the `heliosense.sensor_readings` MySQL table.

**Key findings from inspection:**

- `db.php`: Uses `mysqli`, connects to `localhost`, DB `heliosense`, user `root`, no password
- `insert_reading.php`: Accepts POST JSON, validates and inserts 15 sensor fields + `recorded_at`
- **Important gap found:** The insert API does NOT include `bmp280_temperature_c` or `irradiance_w_m2` — these columns exist in the DB schema but are never written by the ESP32 API (they will always be `NULL` from inserted readings). Dashboard must gracefully handle this.
- The existing API is **100% untouched** — the new dashboard lives in a completely separate folder.

---

## User Review Required

> [!IMPORTANT]
> **IP Address Configuration:** The existing API is at `http://192.168.1.16/...`. The dashboard will use `localhost` for its own PHP backend API calls (server-side DB queries). No change needed to ESP32 target IP. Confirm that the XAMPP server is accessible at `localhost` when browsing the dashboard locally.

> [!IMPORTANT]
> **LIVE/OFFLINE Threshold:** A reading is considered "LIVE" if `recorded_at` is within the last **60 seconds**. This is configurable in `config/db.php`. If your ESP32 sends readings less frequently (e.g., every 30s), this threshold may need to be increased. Please confirm your ESP32 polling interval.

> [!WARNING]
> **`bmp280_temperature_c` and `irradiance_w_m2`:** These fields are in the DB schema but the current `insert_reading.php` does NOT insert values for them — they will always be `NULL` for all new readings unless the ESP32 API is updated separately. The dashboard will show **"No Data"** for these fields, which is correct behavior per your requirements.

---

## Open Questions

> [!IMPORTANT]
> **ESP32 send interval:** How often does the ESP32 currently send data? (e.g., every 5s, 10s, 30s?) This affects the LIVE threshold and polling interval. The plan defaults to a 60-second LIVE threshold and 5-second dashboard polling.

---

## Proposed Changes

### 1. New Dashboard Root

A completely new folder — no existing files touched.

#### [NEW] `C:\xampp\htdocs\heliosense_dashboard\` *(entire folder)*

---

### Config Layer

#### [NEW] [`config/db.php`](file:///C:/xampp/htdocs/heliosense_dashboard/config/db.php)

- Reuses identical DB credentials as `heliosense_api/db.php`
- Adds dashboard-specific constants: `LIVE_THRESHOLD_SECONDS = 60`, `POLL_LIMIT = 100`, etc.
- Sets `error_reporting(0)` and `ini_set('display_errors', 0)` for production safety
- Returns `$conn` via `require_once`

---

### Backend API Layer (`api/`)

All endpoints return `Content-Type: application/json` and `{"success": true/false, "data": {...}}`.

#### [NEW] [`api/latest.php`](file:///C:/xampp/htdocs/heliosense_dashboard/api/latest.php)

Fetches the single most recent reading:
```sql
SELECT * FROM sensor_readings 
ORDER BY recorded_at DESC, reading_id DESC 
LIMIT 1
```
Returns: all sensor fields, `is_live` boolean (based on LIVE_THRESHOLD), `seconds_ago`, `total_readings` count, `sensor_availability` map (which fields are non-NULL).

#### [NEW] [`api/history.php`](file:///C:/xampp/htdocs/heliosense_dashboard/api/history.php)

Paginated history table endpoint:
- Accepts: `page`, `per_page` (default 25), `sort` (asc/desc), `range` (1h/6h/12h/24h/7d), `start`, `end` (custom range), `search` (reading_id)
- Filters on `recorded_at` using prepared statements
- Returns rows with proper NULL handling
- Supports CSV export mode (`format=csv`)

#### [NEW] [`api/chart.php`](file:///C:/xampp/htdocs/heliosense_dashboard/api/chart.php)

Optimized chart data endpoint:
- Accepts: `fields` (comma-separated column names), `range` (1h/6h/12h/24h/7d), `start`, `end`
- Only selects `recorded_at` + requested fields (never `SELECT *` on all fields)
- Limits to max 500 data points (downsamples using `MOD` on `reading_id` for large ranges)
- Returns `{labels: [...], datasets: {field_name: [...]}}`

#### [NEW] [`api/stats.php`](file:///C:/xampp/htdocs/heliosense_dashboard/api/stats.php)

Aggregated statistics:
- Accepts: `range` param
- Returns: `peak_fixed_power`, `avg_fixed_power`, `peak_movable_power`, `avg_movable_power`, `peak_ambient_temp`, `avg_humidity`, `total_readings_in_range`, `estimated_energy_wh` (with proper time-interval calculation)
- Energy calculation uses `SUM(power * interval_seconds / 3600)` via subquery with LAG or application-level interval math

#### [NEW] [`api/status.php`](file:///C:/xampp/htdocs/heliosense_dashboard/api/status.php)

System health check:
- Returns: `db_connected`, `latest_recorded_at`, `seconds_since_last`, `is_live`, `total_readings`, `sensor_availability`
- Lightweight — only runs 2 simple queries

---

### Frontend Layer

#### [NEW] [`index.php`](file:///C:/xampp/htdocs/heliosense_dashboard/index.php)

Single-page application shell. Navigation handled by JS (hash routing). Sections shown/hidden via JS — no full page reloads. Includes:
- `<head>` with SEO meta tags, Google Fonts (Inter), Chart.js CDN, Lucide Icons CDN
- Navigation sidebar (desktop) + hamburger menu (mobile)
- Dark/Light mode toggle (preference stored in `localStorage`)
- Five sections as `<div id="section-*">` panels:
  1. `#section-dashboard` — Main overview
  2. `#section-environment` — Environment monitoring
  3. `#section-panels` — Fixed & Movable panel detail + comparison
  4. `#section-analytics` — All 15 charts with time range selector
  5. `#section-history` — Data history table + CSV export
  6. `#section-status` — System status & sensor availability

#### [NEW] [`assets/css/style.css`](file:///C:/xampp/htdocs/heliosense_dashboard/assets/css/style.css)

Full custom CSS design system:
- **Color palette:** Dark-mode-first with HSL variables; solar amber `#F59E0B`, sky blue `#38BDF8`, deep navy `#0F172A`, card surface `#1E293B`
- **Typography:** Inter font, fluid type scale
- **Components:** `.card`, `.badge-live`, `.badge-offline`, `.stat-value`, `.sensor-card`, `.gauge-container`, `.comparison-bar`
- **Animations:** Subtle pulse on LIVE badge, smooth card entry, chart fade-in, value-update flash
- **Responsive grid:** CSS Grid with `auto-fill` + `minmax(280px, 1fr)` for cards
- **Mobile nav:** Collapsible sidebar → bottom nav on ≤768px
- **Dark/Light toggle:** CSS custom properties swap on `[data-theme="light"]`

#### [NEW] [`assets/js/dashboard.js`](file:///C:/xampp/htdocs/heliosense_dashboard/assets/js/dashboard.js)

Main JavaScript (~700 lines), structured as a module:

**Core architecture:**
```
HelioSense = {
  state: { latestReading, charts, currentRange, theme, pollTimer },
  init(),
  poll.start() / poll.stop(),
  ui.update(data),
  ui.updateTimestamps(),
  charts.init(),
  charts.update(data),
  history.load(page),
  nav.switchSection(id),
  theme.toggle()
}
```

**Live polling logic:**
- `setInterval` every 5000ms calls `/api/latest.php`
- Compares `reading_id` with last known; if new → triggers UI update with flash animation
- Updates relative time ("X seconds ago") every 1 second via a separate `setInterval`
- LIVE/OFFLINE determined by `is_live` from API (backed by `recorded_at` comparison server-side)

**NULL/No Data handling:**
```js
function formatValue(val, unit, decimals = 2) {
  if (val === null || val === undefined || val === '') return 'No Data';
  const n = parseFloat(val);
  if (isNaN(n)) return 'No Data';
  return n.toFixed(decimals) + (unit ? ' ' + unit : '');
}
```

**Chart management:**
- 15 Chart.js line charts, lazy-initialized when Analytics section is opened
- Each chart uses `recorded_at` labels (formatted as `HH:mm:ss` or `MM/DD HH:mm` depending on range)
- Charts skip NULL datapoints (`null` in dataset = gap, not zero)
- Time range selector triggers `fetchChartData(fields, range)` → updates all visible charts

**Servo gauge:**
- SVG-based semi-circle gauge (0°–180°)
- Needle animates to current `servo_angle_deg`
- Color-coded arc (green 80–100°, yellow outside)

**LDR comparison:**
- Shows directional indicator with threshold: balanced if `|left - right| < 50` (configurable constant)

---

### Section Details

#### Main Dashboard (`#section-dashboard`)
```
Header: HELIO SENSE | Solar Monitoring & Tracking System
        [● LIVE] Last Reading: Sep 08 2026, 03:23:52 AM | Updated 12s ago

Row 1 — System Overview (4 mini-stat cards):
  System Status | Latest Reading | Total Readings | Data Status

Row 2 — Environment (6 cards, 3-col on desktop):
  Ambient Temp | Humidity | Panel Temp | BMP280 Temp | Pressure | Irradiance

Row 3 — Fixed Panel (3 cards) | Movable Panel (5 cards)

Row 4 — Tracking (3 cards + mini gauge + LDR indicator)

Row 5 — Power Comparison (inline comparison bar + diff stats)

Row 6 — Recent Readings (mini table, 5 rows)
```

#### Environment Section
- 6 large sensor cards with animated icon, current value, unit, sensor label, relative timestamp
- Mini sparkline chart per card (last 20 readings)

#### Panels Section
- Tab switcher: Fixed | Movable | Comparison
- Fixed tab: V, A, W cards + Fixed Power vs Time chart + peak/avg stats
- Movable tab: V, A, W, Lux, Irradiance cards + 3 charts + peak/avg stats
- Comparison tab: Side-by-side metric table + grouped bar chart + advantage %

#### Analytics Section
- Time range selector: `1h | 6h | 12h | 24h | 7d | Custom`
- Chart category tabs: Power | Environment | Light | Electrical | Tracking
- All 15 charts rendered in responsive 2-column grid
- Each chart has proper axis labels, unit in Y-axis title, responsive height

#### History Section
- Table with all 20 columns, `No Data` for NULLs
- Pagination: 25/50/100 per page selector
- Sort by Date (newest/oldest)
- Date range filter inputs
- **Export CSV** button: calls `api/history.php?format=csv&range=...`

#### System Status Section
- Database connection status card
- ESP32 data status card
- Sensor availability grid (one row per logical sensor group)
- Raw latest reading JSON viewer (collapsible, for debugging)

---

## File Tree

```
C:\xampp\htdocs\heliosense_dashboard\
│
├── index.php                    ← SPA shell, all navigation sections
│
├── config\
│   └── db.php                   ← DB connection + dashboard constants
│
├── api\
│   ├── latest.php               ← Latest reading + live status
│   ├── history.php              ← Paginated history + CSV export
│   ├── chart.php                ← Chart data with time range
│   ├── stats.php                ← Aggregated statistics
│   └── status.php              ← System health check
│
└── assets\
    ├── css\
    │   └── style.css            ← Complete design system
    └── js\
        └── dashboard.js         ← All frontend logic
```

**Total new files: 9**
**Existing files modified: 0** ✅

---

## Database Changes

> [!NOTE]
> **No database changes are made.** The existing `heliosense.sensor_readings` table is used read-only by the dashboard. No new tables, no schema changes, no data deletions.

---

## Verification Plan

### Automated
- Dashboard PHP endpoints return valid JSON (browser test each `api/*.php` URL directly)
- No PHP warnings/errors visible in responses

### Manual Tests
1. **Single sensor:** Comment out fields in latest reading → dashboard shows "No Data" correctly
2. **Multi-sensor:** All cards populate from DB values
3. **Live polling:** Insert a new DB row → dashboard updates within 5 seconds without page reload
4. **OFFLINE:** Wait >60s without new data → badge switches to OFFLINE
5. **NULL irradiance:** Confirm "No Data" shown, not "0" or "null"
6. **Charts:** Select each time range → charts re-render with correct data points
7. **History table:** Paginate, sort, filter dates, export CSV
8. **Mobile:** Resize to 375px → layout adapts, nav collapses
9. **Dark/Light toggle:** Preference persists after page refresh
10. **URL:** Open `http://localhost/heliosense_dashboard/` in browser

### Dashboard URL
```
http://localhost/heliosense_dashboard/
```
