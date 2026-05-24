# Changelog

All notable changes to this project will be documented in this file.

---

## [Unreleased]

---

## [1.3.0] - 2026-05-24

### Added
- **Statistics page** — complete overhaul with Last.fm-style features:
  - Activity calendar (GitHub contributions style) — last 365 days
  - Weekly comparison — this week vs last week vs one year ago
  - Hourly activity chart with peak hour detection
  - Day-of-week activity chart with peak day detection
  - Activity heatmap (hour × day of week)
  - "New artists this period" — artists heard for the first time
  - Listening streak counter 🔥
  - Top list size selector (10 / 25 / 50)
  - Period filter extended with "All time" option
- **Split live log** on dashboard — system status (cycles, nowplaying) separated from synced tracks (✓)
- **Last 5 scrobbles** widget on dashboard — auto-refreshes with the log

### Fixed
- **Echo loop bug** — when person A finished listening and person B started, old scrobbles were incorrectly copied back. Fixed by advancing both `ts_a` and `ts_b` timestamps after every sync cycle regardless of direction.
- Duplicate `loadLog()` function in dashboard JavaScript causing chart not to render
- `NP_GRACE_SECONDS` constant missing after edits causing fatal PHP error
- URL-encoded square brackets in POST body (`track%5B0%5D`) breaking Last.fm API signature — switched to manual URL encoding
- Active period/limit button highlighting on statistics page

---

## [1.2.0] - 2026-05-24

### Added
- **Pause / Resume sync** — suspend synchronization without touching configuration. Shows who paused it, when, and why
- **Security password** — required before saving Last.fm configuration to prevent accidental overwrites
- **Countdown timer** — shows time until next cron cycle on dashboard
- **Last sync cycle info** cards on dashboard (date + time to the second)
- **Last synced scrobble** card on dashboard (artist, track, direction)
- **Dynamic cron history** — last 4 cycles auto-refresh with the live log
- **Android app** — native Kotlin/Jetpack Compose app with:
  - Dashboard with stats, pause/resume, live log
  - Now Playing screen (auto-refresh every 15s)
  - Scrobbles history with A→B/B→A filtering and pagination
  - Settings screen with configurable server URL (works with any instance)
  - Token-based authentication persisted in DataStore
- **REST API** (`api.php`) — full API for third-party integrations:
  - Authentication with 30-day tokens
  - Dashboard, scrobbles, logs, runs, nowplaying, top artists, stats
  - Pause/resume endpoints
  - Password change endpoint

### Changed
- Dashboard log split into two columns: system status | synced tracks
- Statistics page moved to separate `stats.php`
- Navigation updated with Statistics tab

---

## [1.1.0] - 2026-05-23

### Added
- **Smart bidirectional sync** — detects `nowplaying` flag on both accounts:
  - Only A listening → copy A→B
  - Only B listening → copy B→A
  - Both listening → skip (no echo loops)
  - Nobody listening → skip
- **NP grace period** (10 minutes) — if `nowplaying` is still set but last scrobble was over 10 minutes ago, account is treated as inactive
- **MySQL backend** — sync history, scrobbles, logs, configuration all stored in database
- **Web panel** with light theme (DM Sans + DM Serif Display typography):
  - Dashboard with live stats, cron history, split logs
  - Scrobbles history page with filters and pagination
  - Statistics page with heatmap, top artists/albums/tracks, taste comparison
  - Settings page (admin) with history of configuration changes
  - Login system with roles (admin / user)
- **Cron job** support — sync runs every 5 minutes without browser

### Changed
- Replaced single-direction sync with smart bidirectional engine
- Configuration moved from JSON files to MySQL database

### Fixed
- Timezone issue — PHP server timezone mismatch with Last.fm API timestamps causing `from` parameter to be set 2 hours in the future

---

## [1.0.0] - 2026-05-23

### Added
- Initial release — basic Last.fm scrobble synchronization
- PHP sync engine called by cron
- Last.fm OAuth authorization for both accounts
- Simple web configuration panel
- Single-direction sync (A→B only)
- Lock file to prevent concurrent cron runs
- Sync pause/resume via file flag
