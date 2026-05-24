<?php
/**
 * Last.fm Smart Sync — Backend PHP z MySQL
 * sync.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

define('LOCK_FILE',  __DIR__ . '/data/sync.lock');
define('PAUSE_FILE', __DIR__ . '/data/sync.pause');
define('NP_GRACE_SECONDS', 600); // 10 minut

if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);

$action = $_GET['action'] ?? 'run_sync';
switch ($action) {
    case 'run_sync':      runSync();       break;
    case 'get_status':    getStatus();     break;
    case 'save_config':   saveConfig();    break;
    case 'get_config':    getConfig();     break;
    case 'clear_log':     clearLog();      break;
    case 'pause':         setPause(true);  break;
    case 'resume':        setPause(false); break;
    case 'pause_status':  getPauseStatus();break;
    default: jsonOut(['error' => 'Nieznana akcja']);
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function jsonOut($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function dbLog($side, $msg, $type = '') {
    try {
        getDB()->prepare('INSERT INTO logs (side, message, type) VALUES (?, ?, ?)')
               ->execute([$side, $msg, $type]);
    } catch (Exception $e) {}
}

function md5Sig($params, $secret) {
    $keys = array_filter(array_keys($params), fn($k) => $k !== 'format');
    sort($keys);
    $str = '';
    foreach ($keys as $k) $str .= $k . $params[$k];
    return md5($str . $secret);
}

function lastfmGet($params) {
    $params['format'] = 'json';
    $ch = curl_init('https://ws.audioscrobbler.com/2.0/?' . http_build_query($params));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'LFM-Panel/2.0',CURLOPT_SSL_VERIFYPEER=>true]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) throw new Exception('cURL: ' . $err);
    $data = json_decode($res, true);
    if (!$data) throw new Exception('Nieprawidłowa odpowiedź');
    if (isset($data['error'])) throw new Exception('[' . $data['error'] . '] ' . $data['message']);
    return $data;
}

function lastfmPost($params) {
    $params['format'] = 'json';
    // http_build_query koduje [] jako %5B%5D co psuje sygnaturę Last.fm
    // używamy ręcznego budowania stringa
    $body = [];
    foreach ($params as $k => $v) {
        $body[] = rawurlencode($k) . '=' . rawurlencode($v);
    }
    $postBody = implode('&', $body);

    $ch = curl_init('https://ws.audioscrobbler.com/2.0/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postBody,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'LFM-Panel/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) throw new Exception('cURL: ' . $err);
    $data = json_decode($res, true);
    if (!$data) throw new Exception('Nieprawidłowa odpowiedź: ' . substr($res, 0, 100));
    if (isset($data['error'])) throw new Exception('[' . $data['error'] . '] ' . $data['message']);
    return $data;
}

// Po ilu sekundach bez nowego scrobble uznajemy konto za nieaktywne
// mimo że Last.fm nadal pokazuje nowplaying (bufor ~2-3 min)
// Po ilu sekundach bez nowego scrobble uznajemy konto za nieaktywne

function getRecentTracks($user, $apiKey, $from = null) {
    $params = ['method'=>'user.getrecenttracks','user'=>$user,'api_key'=>$apiKey,'limit'=>50,'extended'=>0];
    if ($from) $params['from'] = $from;
    $data = lastfmGet($params);
    $raw  = $data['recenttracks']['track'] ?? [];
    if (isset($raw['name'])) $raw = [$raw];

    $nowplaying    = false;
    $tracks        = [];
    $lastScrobbleTs = 0;

    foreach ($raw as $t) {
        if (!empty($t['@attr']['nowplaying'])) {
            $nowplaying = true;
        } else {
            $tracks[] = $t;
            $ts = (int)($t['date']['uts'] ?? 0);
            if ($ts > $lastScrobbleTs) $lastScrobbleTs = $ts;
        }
    }

    // Jeśli nowplaying=true ale ostatni scrobble był dawniej niż NP_GRACE_SECONDS,
    // Last.fm jeszcze nie wygasił statusu — traktujemy jako nieaktywne
    if ($nowplaying && $lastScrobbleTs > 0) {
        if ((time() - $lastScrobbleTs) > NP_GRACE_SECONDS) {
            $nowplaying = false;
        }
    }

    return ['nowplaying' => $nowplaying, 'tracks' => $tracks, 'last_ts' => $lastScrobbleTs];
}

function scrobbleTracks($tracks, $apiKey, $secret, $sk) {
    if (empty($tracks)) return 0;
    $params = ['method'=>'track.scrobble','api_key'=>$apiKey,'sk'=>$sk];
    foreach ($tracks as $i => $t) {
        $artist = is_string($t['artist']) ? $t['artist'] : ($t['artist']['#text'] ?? '');
        $params["track[$i]"]     = $t['name'] ?? '';
        $params["artist[$i]"]    = $artist;
        $params["timestamp[$i]"] = $t['date']['uts'] ?? time();
        if (!empty($t['album']['#text'])) $params["album[$i]"] = $t['album']['#text'];
    }
    $params['api_sig'] = md5Sig($params, $secret);
    $data = lastfmPost($params);
    return (int)($data['scrobbles']['@attr']['accepted'] ?? count($tracks));
}

function filterNew($tracks, $since) {
    return array_values(array_filter($tracks, fn($t) => !empty($t['date']['uts']) && (int)$t['date']['uts'] > $since));
}

function maxTs($tracks, $current) {
    foreach ($tracks as $t) { $ts = (int)($t['date']['uts'] ?? 0); if ($ts > $current) $current = $ts; }
    return $current;
}

function saveScrobble($runId, $direction, $t) {
    try {
        $artist = is_string($t['artist']) ? $t['artist'] : ($t['artist']['#text'] ?? '');
        $ts = isset($t['date']['uts']) ? date('Y-m-d H:i:s', (int)$t['date']['uts']) : date('Y-m-d H:i:s');
        getDB()->prepare('INSERT INTO scrobbles (run_id, direction, artist, track, album, scrobbled_at) VALUES (?,?,?,?,?,?)')
               ->execute([$runId, $direction, $artist, $t['name'] ?? '', $t['album']['#text'] ?? null, $ts]);
    } catch (Exception $e) {}
}

// ─── MAIN SYNC ────────────────────────────────────────────────────────────────

function runSync() {
    // Sprawdź pauzę
    if (file_exists(PAUSE_FILE)) {
        $info = json_decode(file_get_contents(PAUSE_FILE), true) ?? [];
        dbLog('system', 'Sync wstrzymany przez '.$info['by'].' · '.($info['reason'] ? '"'.$info['reason'].'"' : 'bez powodu'), 'warn');
        jsonOut(['status' => 'paused', 'by' => $info['by'] ?? '', 'reason' => $info['reason'] ?? '']);
        return;
    }
    if (file_exists(LOCK_FILE) && (time() - filemtime(LOCK_FILE)) < 30) {
        jsonOut(['status'=>'locked']);
        return;
    }
    file_put_contents(LOCK_FILE, time());

    // Wczytaj config z bazy
    try {
        $cfg = getDB()->query('SELECT * FROM config ORDER BY id DESC LIMIT 1')->fetch();
    } catch (Exception $e) {
        unlink(LOCK_FILE);
        jsonOut(['status'=>'error','msg'=>'Błąd bazy: '.$e->getMessage()]);
        return;
    }

    if (!$cfg) { unlink(LOCK_FILE); jsonOut(['status'=>'error','msg'=>'Brak konfiguracji']); return; }

    // Stan (ts) trzymamy w pliku — szybszy odczyt niż baza
    $stateFile = __DIR__ . '/data/state.json';
    $state = file_exists($stateFile) ? (json_decode(file_get_contents($stateFile), true) ?? []) : [];
    $now = time();
    $tsA = $state['ts_a'] ?? ($now - 60);
    $tsB = $state['ts_b'] ?? ($now - 60);

    dbLog('system', '--- Cykl ' . date('Y-m-d H:i:s') . ' ---', 'info');

    $syncedA2B = 0; $syncedB2A = 0;
    $npA = false; $npB = false;
    $errorMsg = null;

    try {
        // Pobieramy oba konta BEZ filtra "from" — zawsze znamy najnowszy ts
        try { $dataA = getRecentTracks($cfg['a_user'], $cfg['api_key']); }
        catch (Exception $e) { dbLog('system','Błąd pobierania konta A: '.$e->getMessage(),'err'); throw $e; }
        try { $dataB = getRecentTracks($cfg['b_user'], $cfg['api_key']); }
        catch (Exception $e) { dbLog('system','Błąd pobierania konta B: '.$e->getMessage(),'err'); throw $e; }
        $npA = $dataA['nowplaying'];
        $npB = $dataB['nowplaying'];

        dbLog('system', 'nowplaying: '.$cfg['a_user'].'='.($npA?'TAK':'nie').' · '.$cfg['b_user'].'='.($npB?'TAK':'nie'), 'info');

        // Utwórz rekord cyklu
        $db = getDB();
        $db->prepare('INSERT INTO sync_runs (np_a, np_b, status) VALUES (?,?,?)')->execute([(int)$npA, (int)$npB, 'running']);
        $runId = $db->lastInsertId();

        if ($npA && !$npB) {
            dbLog('a', $cfg['a_user'].' słucha → kopiuję na '.$cfg['b_user'], 'info');
            $dataA_new = getRecentTracks($cfg['a_user'], $cfg['api_key'], $tsA);
            $new = filterNew($dataA_new['tracks'], $tsA);
            if (!empty($new)) {
                $syncedA2B = scrobbleTracks($new, $cfg['api_key'], $cfg['api_secret'], $cfg['b_sk']);
                if ($syncedA2B > 0) $tsA = maxTs($new, $tsA);
                foreach ($new as $t) { saveScrobble($runId, 'a2b', $t); $artist=is_string($t['artist'])?$t['artist']:($t['artist']['#text']??''); dbLog('a','✓ '.$artist.' — '.($t['name']??''),'ok'); }
            } else { dbLog('a', 'Brak nowych scrobbli do skopiowania'); }
            // KLUCZOWE: przesuń tsB do teraz żeby B nie cofało się gdy zacznie słuchać
            $tsB = $now - 30;

        } elseif ($npB && !$npA) {
            dbLog('b', $cfg['b_user'].' słucha → kopiuję na '.$cfg['a_user'], 'info');
            $dataB_new = getRecentTracks($cfg['b_user'], $cfg['api_key'], $tsB);
            $new = filterNew($dataB_new['tracks'], $tsB);
            if (!empty($new)) {
                $syncedB2A = scrobbleTracks($new, $cfg['api_key'], $cfg['api_secret'], $cfg['a_sk']);
                if ($syncedB2A > 0) $tsB = maxTs($new, $tsB);
                foreach ($new as $t) { saveScrobble($runId, 'b2a', $t); $artist=is_string($t['artist'])?$t['artist']:($t['artist']['#text']??''); dbLog('b','✓ '.$artist.' — '.($t['name']??''),'ok'); }
            } else { dbLog('b', 'Brak nowych scrobbli do skopiowania'); }
            // KLUCZOWE: przesuń tsA do teraz żeby A nie cofało się gdy zacznie słuchać
            $tsA = $now - 30;

        } elseif ($npA && $npB) {
            dbLog('system', 'Oboje słuchają jednocześnie → pomijam synchronizację', 'warn');
            // Aktualizuj oba ts do teraz
            $tsA = $now - 30;
            $tsB = $now - 30;

        } else {
            dbLog('system', 'Nikt nie słucha');
            // Aktualizuj oba ts do teraz — nikt nie słucha, reset okna
            $tsA = $now - 30;
            $tsB = $now - 30;
        }

        // Zaktualizuj rekord cyklu
        $db->prepare('UPDATE sync_runs SET synced_a2b=?, synced_b2a=?, status=? WHERE id=?')
           ->execute([$syncedA2B, $syncedB2A, 'ok', $runId]);

    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        dbLog('system', 'BŁĄD: '.$errorMsg, 'err');
        try { getDB()->prepare('UPDATE sync_runs SET status=?, error_msg=? WHERE id=?')->execute(['error', $errorMsg, $runId ?? 0]); } catch(Exception $ex){}
    }

    // Zapisz stan
    $state['ts_a'] = $tsA; $state['ts_b'] = $tsB; $state['last_run'] = $now;
    file_put_contents($stateFile, json_encode($state));

    unlink(LOCK_FILE);
    jsonOut(['status'=>$errorMsg?'error':'ok','a2b'=>$syncedA2B,'b2a'=>$syncedB2A,'np_a'=>$npA,'np_b'=>$npB,'error'=>$errorMsg]);
}

// ─── STATUS ──────────────────────────────────────────────────────────────────

function setPause(bool $paused) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $by     = $body['by']     ?? 'unknown';
    $reason = $body['reason'] ?? '';

    if ($paused) {
        file_put_contents(PAUSE_FILE, json_encode([
            'by'     => $by,
            'reason' => $reason,
            'since'  => date('Y-m-d H:i:s'),
        ]));
        dbLog('system', 'Sync wstrzymany przez '.$by.($reason?' · "'.$reason.'"':''), 'warn');
        jsonOut(['status' => 'paused']);
    } else {
        if (file_exists(PAUSE_FILE)) unlink(PAUSE_FILE);
        dbLog('system', 'Sync wznowiony przez '.$by, 'info');
        jsonOut(['status' => 'resumed']);
    }
}

function getPauseStatus() {
    if (file_exists(PAUSE_FILE)) {
        $info = json_decode(file_get_contents(PAUSE_FILE), true) ?? [];
        jsonOut(['paused' => true, 'info' => $info]);
    } else {
        jsonOut(['paused' => false]);
    }
}

function getStatus() {
    try {
        $db     = getDB();
        $totals = $db->query('SELECT SUM(synced_a2b) as a2b, SUM(synced_b2a) as b2a, COUNT(*) as runs FROM sync_runs')->fetch();
        $last   = $db->query('SELECT ran_at FROM sync_runs ORDER BY id DESC LIMIT 1')->fetchColumn();
        $logs   = $db->query('SELECT * FROM logs ORDER BY id DESC LIMIT 200')->fetchAll();
        $logs   = array_reverse($logs);
        $runs   = $db->query('SELECT * FROM sync_runs ORDER BY id DESC LIMIT 4')->fetchAll();
        $recent = $db->query('SELECT * FROM scrobbles ORDER BY id DESC LIMIT 5')->fetchAll();
        $paused = file_exists(PAUSE_FILE);
        $pauseInfo = $paused ? (json_decode(file_get_contents(PAUSE_FILE), true) ?? []) : null;
        jsonOut(['totals'=>$totals,'last_run'=>$last,'logs'=>$logs,'runs'=>$runs,'recent_scrobbles'=>$recent,'paused'=>$paused,'pause_info'=>$pauseInfo]);
    } catch (Exception $e) {
        jsonOut(['error'=>$e->getMessage()]);
    }
}

// ─── CONFIG ──────────────────────────────────────────────────────────────────

function saveConfig() {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    foreach (['api_key','api_secret','a_user','a_sk','b_user','b_sk'] as $f) {
        if (empty($data[$f])) { jsonOut(['error'=>'Brakuje: '.$f]); return; }
    }
    try {
        getDB()->prepare('INSERT INTO config (api_key,api_secret,a_user,a_sk,b_user,b_sk) VALUES (?,?,?,?,?,?)')
               ->execute([trim($data['api_key']),trim($data['api_secret']),trim($data['a_user']),trim($data['a_sk']),trim($data['b_user']),trim($data['b_sk'])]);

        // Reset stanu
        $stateFile = __DIR__ . '/data/state.json';
        file_put_contents($stateFile, json_encode(['ts_a'=>time()-60,'ts_b'=>time()-60]));

        dbLog('system','Konfiguracja zapisana: '.trim($data['a_user']).' <-> '.trim($data['b_user']),'info');
        jsonOut(['status'=>'ok']);
    } catch (Exception $e) {
        jsonOut(['error'=>$e->getMessage()]);
    }
}

function getConfig() {
    try {
        $cfg = getDB()->query('SELECT api_key, a_user, b_user, saved_at FROM config ORDER BY id DESC LIMIT 1')->fetch();
        jsonOut($cfg ?: []);
    } catch (Exception $e) { jsonOut([]); }
}

function clearLog() {
    try { getDB()->exec('DELETE FROM logs'); } catch(Exception $e){}
    jsonOut(['status'=>'ok']);
}
