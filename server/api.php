<?php
/**
 * Last.fm Panel — REST API
 * Plik: api.php
 *
 * Endpointy:
 *   POST /api.php?endpoint=login          — logowanie, zwraca token
 *   POST /api.php?endpoint=logout         — wylogowanie
 *   GET  /api.php?endpoint=me             — dane zalogowanego użytkownika
 *   GET  /api.php?endpoint=dashboard      — statystyki główne
 *   GET  /api.php?endpoint=scrobbles      — historia scrobbli (?page=1&dir=a2b&artist=)
 *   GET  /api.php?endpoint=runs           — historia cykli crona
 *   GET  /api.php?endpoint=logs           — logi synchronizacji
 *   GET  /api.php?endpoint=config         — aktualna konfiguracja (bez sekretów)
 *   POST /api.php?endpoint=config         — zapisz konfigurację (tylko admin)
 *   POST /api.php?endpoint=sync           — uruchom sync teraz (tylko admin)
 *   POST /api.php?endpoint=clear_log      — wyczyść log (tylko admin)
 *   GET  /api.php?endpoint=nowplaying     — kto teraz słucha
 *   GET  /api.php?endpoint=top_artists    — top artyści
 *   GET  /api.php?endpoint=chart          — dane do wykresu (30 dni)
 *   PUT  /api.php?endpoint=password       — zmiana hasła
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Obsłuż preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

define('TOKEN_LIFETIME', 86400 * 30); // 30 dni

// ─── ROUTING ─────────────────────────────────────────────────────────────────

$endpoint = $_GET['endpoint'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];

// Endpointy publiczne (bez tokenu)
if ($endpoint === 'login' && $method === 'POST') { handleLogin(); exit; }

// Wszystkie pozostałe wymagają tokenu
$currentUser = requireToken();

switch ($endpoint) {
    case 'me':          handleMe($currentUser);          break;
    case 'dashboard':   handleDashboard($currentUser);   break;
    case 'scrobbles':   handleScrobbles($currentUser);   break;
    case 'runs':        handleRuns($currentUser);        break;
    case 'logs':        handleLogs($currentUser);        break;
    case 'nowplaying':  handleNowplaying($currentUser);  break;
    case 'top_artists': handleTopArtists($currentUser);  break;
    case 'chart':       handleChart($currentUser);       break;
    case 'stats':       handleStats($currentUser);       break;
    case 'config':
        if ($method === 'GET')  handleGetConfig($currentUser);
        if ($method === 'POST') handleSaveConfig($currentUser);
        break;
    case 'sync':        handleSync($currentUser);        break;
    case 'pause':       handlePause($currentUser);       break;
    case 'resume':      handleResume($currentUser);      break;
    case 'pause_status':handlePauseStatus($currentUser); break;
    case 'clear_log':   handleClearLog($currentUser);    break;
    case 'logout':      handleLogout($currentUser);      break;
    case 'password':    handlePassword($currentUser);    break;
    default: apiError('Nieznany endpoint', 404);
}

// ─── AUTH ─────────────────────────────────────────────────────────────────────

function requireToken(): array {
    $token = null;

    // Apache często blokuje Authorization header — sprawdzamy wszystkie możliwe miejsca
    $auth = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }

    // Fallback: token w URL (?token=...)
    if (!$token && !empty($_GET['token'])) {
        $token = trim($_GET['token']);
    }

    // Fallback: token w headerze X-API-Token
    if (!$token && !empty($_SERVER['HTTP_X_API_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_API_TOKEN']);
    }

    if (!$token) apiError('Brak tokenu autoryzacji', 401);

    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT u.* FROM panel_users u JOIN api_tokens t ON t.user_id = u.id WHERE t.token = ? AND t.expires_at > NOW()');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if (!$user) apiError('Nieprawidłowy lub wygasły token', 401);

        $db->prepare('UPDATE api_tokens SET last_used = NOW() WHERE token = ?')->execute([$token]);

        return $user;
    } catch (Exception $e) {
        apiError('Błąd bazy danych: ' . $e->getMessage(), 500);
    }
}

function requireAdmin(array $user) {
    if ($user['role'] !== 'admin') apiError('Brak uprawnień', 403);
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function apiOut($data, int $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function apiError(string $msg, int $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

// ─── HANDLERS ────────────────────────────────────────────────────────────────

function handleLogin() {
    $body = getBody();
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$password) apiError('Podaj login i hasło');

    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM panel_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            apiError('Nieprawidłowy login lub hasło', 401);
        }

        // Utwórz token
        $token     = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_LIFETIME);

        // Utwórz tabelę tokenów jeśli nie istnieje
        $db->exec('CREATE TABLE IF NOT EXISTS api_tokens (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            token      VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            last_used  DATETIME DEFAULT NULL,
            device     VARCHAR(128) DEFAULT NULL,
            INDEX idx_token (token),
            FOREIGN KEY (user_id) REFERENCES panel_users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $device = $body['device'] ?? 'unknown';
        $db->prepare('INSERT INTO api_tokens (user_id, token, expires_at, device) VALUES (?,?,?,?)')->execute([$user['id'], $token, $expiresAt, $device]);

        // Aktualizuj last_login
        $db->prepare('UPDATE panel_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

        apiOut([
            'token'      => $token,
            'expires_at' => $expiresAt,
            'user'       => [
                'id'          => $user['id'],
                'username'    => $user['username'],
                'lastfm_user' => $user['lastfm_user'],
                'role'        => $user['role'],
            ]
        ]);
    } catch (Exception $e) {
        apiError('Błąd serwera: ' . $e->getMessage(), 500);
    }
}

function handleLogout(array $user) {
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    preg_match('/Bearer\s+(.+)/i', $auth, $m);
    $token = $m[1] ?? ($_GET['token'] ?? '');
    if ($token) {
        getDB()->prepare('DELETE FROM api_tokens WHERE token = ?')->execute([$token]);
    }
    apiOut(['message' => 'Wylogowano']);
}

function handleMe(array $user) {
    $db     = getDB();
    $tokens = $db->prepare('SELECT id, created_at, expires_at, last_used, device FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC')->execute([$user['id']]);

    apiOut([
        'id'          => $user['id'],
        'username'    => $user['username'],
        'lastfm_user' => $user['lastfm_user'],
        'role'        => $user['role'],
        'last_login'  => $user['last_login'],
    ]);
}

function handleDashboard(array $user) {
    $db = getDB();

    $totals     = $db->query('SELECT SUM(synced_a2b) a2b, SUM(synced_b2a) b2a, COUNT(*) runs, MAX(ran_at) last_run FROM sync_runs')->fetch();
    $lastScrobble = $db->query('SELECT artist, track, direction, synced_at FROM scrobbles ORDER BY id DESC LIMIT 1')->fetch();
    $cfg        = $db->query('SELECT a_user, b_user, saved_at FROM config ORDER BY id DESC LIMIT 1')->fetch();

    // Aktywność dziś
    $today = $db->query("SELECT SUM(synced_a2b) a2b, SUM(synced_b2a) b2a FROM sync_runs WHERE DATE(ran_at) = CURDATE()")->fetch();

    // Ostatni cykl z nowplaying
    $lastRunFull = $db->query("SELECT * FROM sync_runs ORDER BY id DESC LIMIT 1")->fetch();

    apiOut([
        'totals'       => [
            'a2b'  => (int)($totals['a2b'] ?? 0),
            'b2a'  => (int)($totals['b2a'] ?? 0),
            'runs' => (int)($totals['runs'] ?? 0),
            'total'=> (int)($totals['a2b'] ?? 0) + (int)($totals['b2a'] ?? 0),
        ],
        'today'        => [
            'a2b' => (int)($today['a2b'] ?? 0),
            'b2a' => (int)($today['b2a'] ?? 0),
        ],
        'last_run'     => $totals['last_run'],
        'last_scrobble'=> $lastScrobble ?: null,
        'last_run_full'=> $lastRunFull ?: null,
        'accounts'     => [
            'a' => $cfg['a_user'] ?? null,
            'b' => $cfg['b_user'] ?? null,
        ],
    ]);
}

function handleScrobbles(array $user) {
    $db      = getDB();
    $cfg     = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
    $isAdmin = $user['role'] === 'admin';

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;
    $dir     = $_GET['dir'] ?? 'all';
    $artist  = trim($_GET['artist'] ?? '');

    // Ustal kierunek dla zwykłego użytkownika
    $myDirection = null;
    if (!$isAdmin) {
        if ($user['lastfm_user'] === ($cfg['a_user'] ?? '')) $myDirection = 'a2b';
        elseif ($user['lastfm_user'] === ($cfg['b_user'] ?? '')) $myDirection = 'b2a';
    }

    $where = []; $params = [];
    if (!$isAdmin && $myDirection) { $where[] = 'direction = ?'; $params[] = $myDirection; }
    elseif ($dir !== 'all') { $where[] = 'direction = ?'; $params[] = $dir; }
    if ($artist !== '') { $where[] = 'artist LIKE ?'; $params[] = '%' . $artist . '%'; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = $db->prepare("SELECT COUNT(*) FROM scrobbles $whereSQL");
    $total->execute($params);
    $total = (int)$total->fetchColumn();

    $rows = $db->prepare("SELECT * FROM scrobbles $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $rows->execute($params);

    apiOut([
        'items'      => $rows->fetchAll(),
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage,
        'pages'      => (int)ceil($total / $perPage),
    ]);
}

function handleRuns(array $user) {
    requireAdmin($user);
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $runs  = getDB()->query("SELECT * FROM sync_runs ORDER BY id DESC LIMIT $limit")->fetchAll();
    apiOut($runs);
}

function handleLogs(array $user) {
    $limit  = min(500, max(1, (int)($_GET['limit'] ?? 100)));
    $side   = $_GET['side'] ?? 'all';
    $where  = $side !== 'all' ? 'WHERE side = ?' : '';
    $params = $side !== 'all' ? [$side] : [];
    $stmt   = getDB()->prepare("SELECT * FROM logs $where ORDER BY id DESC LIMIT $limit");
    $stmt->execute($params);
    $logs = array_reverse($stmt->fetchAll());
    apiOut($logs);
}

function handleNowplaying(array $user) {
    $db  = getDB();
    $cfg = $db->query('SELECT api_key, a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
    if (!$cfg) apiError('Brak konfiguracji');

    $result = ['a' => null, 'b' => null];

    foreach (['a', 'b'] as $side) {
        $lfmUser = $cfg[$side . '_user'];
        try {
            $url = 'https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user='
                . urlencode($lfmUser) . '&api_key=' . urlencode($cfg['api_key'])
                . '&format=json&limit=1';
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_USERAGENT=>'LFM-API/1.0']);
            $res  = curl_exec($ch); curl_close($ch);
            $data = json_decode($res, true);
            $track = $data['recenttracks']['track'][0] ?? null;
            if ($track) {
                $result[$side] = [
                    'user'       => $lfmUser,
                    'nowplaying' => !empty($track['@attr']['nowplaying']),
                    'artist'     => $track['artist']['#text'] ?? '',
                    'track'      => $track['name'] ?? '',
                    'album'      => $track['album']['#text'] ?? '',
                    'image'      => $track['image'][2]['#text'] ?? null, // medium
                    'date'       => $track['date']['#text'] ?? null,
                ];
            }
        } catch (Exception $e) {
            $result[$side] = ['user' => $lfmUser, 'error' => $e->getMessage()];
        }
    }

    apiOut($result);
}

function handleTopArtists(array $user) {
    $db      = getDB();
    $isAdmin = $user['role'] === 'admin';
    $cfg     = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
    $limit   = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $days    = (int)($_GET['days'] ?? 30);

    $dateFilter = $days > 0 ? "AND scrobbled_at >= DATE_SUB(NOW(), INTERVAL $days DAY)" : '';

    // Ustal kierunek
    $myDir = null;
    if (!$isAdmin) {
        if ($user['lastfm_user'] === ($cfg['a_user'] ?? '')) $myDir = 'a2b';
        elseif ($user['lastfm_user'] === ($cfg['b_user'] ?? '')) $myDir = 'b2a';
    }

    $dirA = (!$isAdmin && $myDir) ? "AND direction = '$myDir'" : "AND direction = 'a2b'";
    $dirB = (!$isAdmin && $myDir) ? "AND direction = '$myDir'" : "AND direction = 'b2a'";

    $topA = $db->query("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE 1=1 $dirA $dateFilter GROUP BY artist ORDER BY cnt DESC LIMIT $limit")->fetchAll();
    $topB = $db->query("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE 1=1 $dirB $dateFilter GROUP BY artist ORDER BY cnt DESC LIMIT $limit")->fetchAll();

    apiOut(['a' => $topA, 'b' => $topB]);
}

function handleChart(array $user) {
    $days = min(365, max(7, (int)($_GET['days'] ?? 30)));
    $data = getDB()->query("
        SELECT DATE(ran_at) day, SUM(synced_a2b) a2b, SUM(synced_b2a) b2a
        FROM sync_runs
        WHERE ran_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY DATE(ran_at)
        ORDER BY day
    ")->fetchAll();
    apiOut($data);
}

function handleGetConfig(array $user) {
    requireAdmin($user);
    $cfg = getDB()->query('SELECT api_key, a_user, b_user, saved_at FROM config ORDER BY id DESC LIMIT 1')->fetch();
    apiOut($cfg ?: []);
}

function handleSaveConfig(array $user) {
    requireAdmin($user);
    $body   = getBody();
    $fields = ['api_key','api_secret','a_user','a_sk','b_user','b_sk'];
    foreach ($fields as $f) {
        if (empty($body[$f])) apiError('Brakuje pola: ' . $f);
    }
    try {
        getDB()->prepare('INSERT INTO config (api_key,api_secret,a_user,a_sk,b_user,b_sk) VALUES (?,?,?,?,?,?)')
               ->execute([$body['api_key'],$body['api_secret'],$body['a_user'],$body['a_sk'],$body['b_user'],$body['b_sk']]);
        file_put_contents(__DIR__.'/data/state.json', json_encode(['ts_a'=>time()-60,'ts_b'=>time()-60]));
        apiOut(['message' => 'Konfiguracja zapisana']);
    } catch (Exception $e) {
        apiError($e->getMessage(), 500);
    }
}

function handleSync(array $user) {
    requireAdmin($user);
    // Deleguj do sync.php
    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
         . dirname($_SERVER['REQUEST_URI']) . '/sync.php?action=run_sync';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60]);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true) ?: ['raw' => $res];
    apiOut($data);
}

function handleClearLog(array $user) {
    requireAdmin($user);
    getDB()->exec('DELETE FROM logs');
    apiOut(['message' => 'Log wyczyszczony']);
}

function handlePassword(array $user) {
    $body    = getBody();
    $current = $body['current_password'] ?? '';
    $new     = $body['new_password'] ?? '';

    if (!$current || !$new) apiError('Podaj aktualne i nowe hasło');
    if (strlen($new) < 6)   apiError('Nowe hasło musi mieć min. 6 znaków');

    $db   = getDB();
    $stmt = $db->prepare('SELECT password FROM panel_users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row  = $stmt->fetch();

    if (!password_verify($current, $row['password'])) apiError('Nieprawidłowe aktualne hasło', 401);

    $db->prepare('UPDATE panel_users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
    apiOut(['message' => 'Hasło zmienione']);
}

// ─── PAUSE / RESUME ──────────────────────────────────────────────────────────

define('PAUSE_FILE', __DIR__ . '/data/sync.pause');

function handlePause(array $user) {
    $body   = getBody();
    $reason = $body['reason'] ?? '';
    file_put_contents(PAUSE_FILE, json_encode([
        'by'     => $user['username'],
        'reason' => $reason,
        'since'  => date('Y-m-d H:i:s'),
    ]));
    try {
        getDB()->prepare('INSERT INTO logs (side, message, type) VALUES (?,?,?)')
               ->execute(['system', 'Sync wstrzymany przez '.$user['username'].($reason?' · "'.$reason.'"':''), 'warn']);
    } catch(Exception $e) {}
    apiOut(['status' => 'paused', 'by' => $user['username'], 'reason' => $reason]);
}

function handleResume(array $user) {
    if (file_exists(PAUSE_FILE)) unlink(PAUSE_FILE);
    try {
        getDB()->prepare('INSERT INTO logs (side, message, type) VALUES (?,?,?)')
               ->execute(['system', 'Sync wznowiony przez '.$user['username'], 'info']);
    } catch(Exception $e) {}
    apiOut(['status' => 'resumed', 'by' => $user['username']]);
}

function handlePauseStatus(array $user) {
    if (file_exists(PAUSE_FILE)) {
        $info = json_decode(file_get_contents(PAUSE_FILE), true) ?? [];
        apiOut(['paused' => true, 'info' => $info]);
    } else {
        apiOut(['paused' => false, 'info' => null]);
    }
}

// ─── STATS ────────────────────────────────────────────────────────────────────

function handleStats(array $user) {
    $db      = getDB();
    $isAdmin = $user['role'] === 'admin';
    $days    = min(365, max(7, (int)($_GET['days'] ?? 90)));
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

    $cfg   = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
    $aUser = $cfg['a_user'] ?? 'A';
    $bUser = $cfg['b_user'] ?? 'B';

    // Totale
    $totals = $db->prepare("
        SELECT
            SUM(CASE WHEN direction='a2b' THEN 1 ELSE 0 END) a2b,
            SUM(CASE WHEN direction='b2a' THEN 1 ELSE 0 END) b2a,
            COUNT(DISTINCT artist) artists,
            COUNT(DISTINCT track) tracks
        FROM scrobbles WHERE scrobbled_at >= ?
    "); $totals->execute([$dateFrom]); $totals = $totals->fetch();

    // Top artyści
    $topA = $db->prepare("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT 10");
    $topA->execute([$dateFrom]); $topA = $topA->fetchAll();

    $topB = $db->prepare("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT 10");
    $topB->execute([$dateFrom]); $topB = $topB->fetchAll();

    // Porównanie gustów
    $artistsA = array_column($topA, 'cnt', 'artist');
    $artistsB = array_column($topB, 'cnt', 'artist');
    $common   = array_intersect_key($artistsA, $artistsB);
    arsort($common);
    $totalUnique = count(array_unique(array_merge(array_keys($artistsA), array_keys($artistsB))));
    $similarity  = $totalUnique > 0 ? round(count($common) / $totalUnique * 100) : 0;

    // Wykres dzienny
    $daily = $db->prepare("
        SELECT DATE(scrobbled_at) day, direction, COUNT(*) cnt
        FROM scrobbles WHERE scrobbled_at >= ?
        GROUP BY DATE(scrobbled_at), direction ORDER BY day
    "); $daily->execute([$dateFrom]); $daily = $daily->fetchAll();

    // Heatmapa
    $heat = $db->prepare("
        SELECT HOUR(scrobbled_at) h, WEEKDAY(scrobbled_at) d, direction, COUNT(*) cnt
        FROM scrobbles WHERE scrobbled_at >= ?
        GROUP BY h, d, direction
    "); $heat->execute([$dateFrom]); $heat = $heat->fetchAll();

    apiOut([
        'period'     => $days,
        'date_from'  => $dateFrom,
        'accounts'   => ['a' => $aUser, 'b' => $bUser],
        'totals'     => $totals,
        'top_a'      => $topA,
        'top_b'      => $topB,
        'similarity' => $similarity,
        'common'     => array_map(fn($k,$v) => ['artist'=>$k,'cnt'=>$v], array_keys($common), array_values($common)),
        'daily'      => $daily,
        'heatmap'    => $heat,
    ]);
}
