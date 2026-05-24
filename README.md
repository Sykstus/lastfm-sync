# 🎵 Last.fm Sync

**Automatic bidirectional scrobble synchronization between two Last.fm accounts.**

When one person listens to music, the scrobbles automatically copy to both accounts. Built for households where multiple people share a computer or music system and want to keep their Last.fm histories in sync.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![Android](https://img.shields.io/badge/Android-API_26+-3DDC84?style=flat&logo=android&logoColor=white)
![Kotlin](https://img.shields.io/badge/Kotlin-2.0-7F52FF?style=flat&logo=kotlin&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green?style=flat)

---

## ✨ Features

- **Smart sync** — detects who is currently listening via `nowplaying` flag and only copies in the correct direction
- **No echo loops** — if both accounts are active simultaneously, sync is paused automatically
- **Pause / Resume** — temporarily suspend syncing without touching configuration
- **Web panel** — clean light-themed dashboard with live logs, statistics, heatmap, and taste comparison
- **REST API** — full API for third-party integrations
- **Android app** — native Kotlin/Compose app with real-time now-playing, sync controls, and configurable server URL
- **Cron-based** — runs every 5 minutes on the server, no browser required

---

## 📸 Screenshots

| Dashboard | Statistics | Now Playing |
|-----------|------------|-------------|
| Live sync log split into system status and synced tracks | Activity heatmap, taste comparison, top artists/albums/tracks | Real-time now playing for both accounts |

---

## 🗂 Repository Structure

```
lastfm-sync/
├── server/          ← PHP backend (upload to your hosting)
│   ├── sync.php         Smart sync engine (called by cron)
│   ├── api.php          REST API
│   ├── dashboard.php    Web panel — main dashboard
│   ├── stats.php        Statistics page
│   ├── scrobbles.php    Scrobble history
│   ├── settings.php     Configuration (admin)
│   ├── login.php        Panel login
│   ├── auth.php         Last.fm OAuth callback
│   ├── config.php       ← EDIT THIS — database credentials
│   ├── database.sql     MySQL schema
│   └── .htaccess        Apache configuration
│
├── android/         ← Android app (open in Android Studio)
│   └── app/src/main/java/pl/hellyeah/lastfmsync/
│       ├── data/api/    Retrofit API client
│       ├── data/model/  Data classes
│       ├── viewmodel/   App logic + auto-refresh
│       └── ui/screens/  Login, Dashboard, NowPlaying, Scrobbles, Settings
│
└── README.md
```

---

## 🚀 Server Setup

### Requirements
- PHP 7.4+ with cURL extension
- MySQL 5.7+ / MariaDB
- Apache with mod_rewrite
- Cron Jobs support

### 1. Upload files

Upload everything from `server/` to your hosting directory, e.g. `/public_html/fm/`

### 2. Create the database

In phpMyAdmin, create a new database and import `server/database.sql`.

### 3. Configure database connection

Edit `server/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('BASE_URL', 'https://your-domain.com/fm/');
```

### 4. Create the data directory

Via FTP, create a `data/` folder inside your `fm/` directory with permissions `755`.

### 5. Add a Cron Job

In your hosting control panel, add a cron job to run every 5 minutes:

```bash
# Using wget (recommended for shared hosting)
*/5 * * * * wget -q -O /dev/null "https://your-domain.com/fm/sync.php?action=run_sync"

# Or using PHP CLI
*/5 * * * * php /home/user/public_html/fm/sync.php?action=run_sync >> /dev/null 2>&1
```

### 6. Open the panel

Navigate to `https://your-domain.com/fm/login.php`

Default credentials:

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | Admin |

> ⚠️ **Change the password immediately after first login** — Settings → Change password.
> 
> To add more users, insert them directly into the `panel_users` table in phpMyAdmin.
> Generate a password hash with: `php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT);"`

---

## ⚙️ Configuring Last.fm

### 1. Get an API key

Go to [last.fm/api/account/create](https://www.last.fm/api/account/create) and create an application.
Set the **Callback URL** to: `https://your-domain.com/fm/auth.php`

Copy the **API Key** and **Shared Secret**.

### 2. Authorize both accounts

In the panel **Settings**:
1. Enter API Key and API Secret
2. Click **Authorize Account A** → log in as the first Last.fm account → accept access
3. Click **Authorize Account B** → log in as the second account (use incognito mode)
4. Enter your security password and click **Save Configuration**

---

## 📱 Android App

### Requirements
- Android Studio Ladybug (2024.2+)
- Android SDK 26+
- Kotlin 2.0+

### Setup

1. Open the `android/` folder in Android Studio
2. Wait for Gradle sync
3. In `android/app/src/main/java/pl/hellyeah/lastfmsync/data/api/ApiClient.kt`, update the default URL:

```kotlin
const val DEFAULT_BASE_URL = "https://your-domain.com/fm/"
```

4. Run on your device or emulator

### Changing the server URL at runtime

You don't need to rebuild the app. In the **Settings** tab, update the **Server URL** field and tap **Save URL**. The app will immediately connect to the new server.

---

## 🔌 REST API

All endpoints require a `token` query parameter obtained via `login`.

### Authentication

```bash
# Login
POST /fm/api.php?endpoint=login
Body: {"username": "admin", "password": "yourpassword"}
# Returns: {"success": true, "data": {"token": "...", "user": {...}}}

# Use token
GET /fm/api.php?endpoint=dashboard&token=YOUR_TOKEN
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `login` | Authenticate, receive token |
| `GET` | `me` | Current user info |
| `GET` | `dashboard` | Stats, last run, last scrobble |
| `GET` | `nowplaying` | Who is currently listening |
| `GET` | `logs` | Sync log entries |
| `GET` | `runs` | Recent cron cycles |
| `GET` | `scrobbles` | Scrobble history (paginated) |
| `GET` | `top_artists` | Top artists per account |
| `GET` | `stats` | Full statistics with heatmap |
| `GET` | `pause_status` | Current pause state |
| `POST` | `pause` | Pause sync (`{"reason": "..."}`) |
| `POST` | `resume` | Resume sync |
| `POST` | `sync` | Trigger sync manually (admin) |
| `PUT` | `password` | Change password |
| `POST` | `logout` | Invalidate token |

---

## 🧠 How Sync Logic Works

```
Every 5 minutes (cron):
  ├── Check nowplaying on Account A
  ├── Check nowplaying on Account B
  │
  ├── Only A is listening  →  copy new scrobbles A → B
  ├── Only B is listening  →  copy new scrobbles B → A
  ├── Both listening       →  skip (no echo loops)
  └── Nobody listening     →  skip
```

The `nowplaying` flag has a 10-minute grace period — if Last.fm still shows `nowplaying` but the last scrobble was more than 10 minutes ago, the account is treated as inactive.

---

## 🛠 Troubleshooting

**Sync not working / "No new scrobbles"**
- Make sure both Last.fm accounts are **public** (Last.fm → Settings → Privacy)
- Verify the cron job is running in your hosting panel
- Click **Sync now** on the dashboard and watch the log

**"Invalid method signature" error**
- Session Key has expired or is invalid
- Go to Settings and re-authorize the affected account

**Android app shows no data**
- Make sure the server URL ends with a slash: `https://domain.com/fm/`
- Check the server URL in the Settings tab

**Scrobbles appearing twice**
- Edit `data/state.json` via FTP and reset `ts_a`/`ts_b` to the current Unix timestamp

---

## 📄 License

MIT License — free to use, modify, and distribute.

---

## 🙏 Credits

Built with:
- [Last.fm API](https://www.last.fm/api) — scrobble data
- [Retrofit](https://square.github.io/retrofit/) — Android HTTP client
- [Jetpack Compose](https://developer.android.com/jetpack/compose) — Android UI
- [Chart.js](https://www.chartjs.org/) — web charts
- [IBM Plex](https://www.ibm.com/plex/) + [DM Sans](https://fonts.google.com/specimen/DM+Sans) — typography
