<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user    = currentUser();
$db      = getDB();
$isAdmin = $user['role'] === 'admin';

$cfg   = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
$aUser = $cfg['a_user'] ?? 'Konto A';
$bUser = $cfg['b_user'] ?? 'Konto B';
$myLastfm    = $user['lastfm_user'];
$myDirection = null;
if ($myLastfm === $aUser) $myDirection = 'a2b';
elseif ($myLastfm === $bUser) $myDirection = 'b2a';

$totals = $db->query('SELECT SUM(synced_a2b) a2b, SUM(synced_b2a) b2a, COUNT(*) runs FROM sync_runs')->fetch();
$daily  = $db->query("SELECT DATE(ran_at) as day, SUM(synced_a2b) a2b, SUM(synced_b2a) b2a FROM sync_runs WHERE ran_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(ran_at) ORDER BY day")->fetchAll();

$topArtistsA = $db->query("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' GROUP BY artist ORDER BY cnt DESC LIMIT 8")->fetchAll();
$topArtistsB = $db->query("SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' GROUP BY artist ORDER BY cnt DESC LIMIT 8")->fetchAll();

$dirFilter = (!$isAdmin && $myDirection) ? "WHERE direction='$myDirection'" : '';
$recentScrobbles = $db->query("SELECT s.* FROM scrobbles s $dirFilter ORDER BY s.id DESC LIMIT 5")->fetchAll();
$recentRuns = $db->query("SELECT * FROM sync_runs ORDER BY id DESC LIMIT 4")->fetchAll();
$lastRun = $db->query("SELECT ran_at FROM sync_runs ORDER BY id DESC LIMIT 1")->fetchColumn();
$lastScrobbleRow = $db->query("SELECT artist, track, synced_at FROM scrobbles ORDER BY id DESC LIMIT 1")->fetch();

$chartDays = json_encode(array_column($daily,'day'));
$chartA2B  = json_encode(array_map('intval',array_column($daily,'a2b')));
$chartB2A  = json_encode(array_map('intval',array_column($daily,'b2a')));

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <div style="display:flex;align-items:center;gap:12px;">
      <span class="hint" id="last-update"><?= $lastRun ? 'Ostatni sync: '.date('H:i', strtotime($lastRun)) : 'Brak danych' ?></span>
      <span style="display:inline-flex;align-items:center;gap:7px;font-family:'DM Mono',monospace;font-size:.72rem;background:var(--bg3);border:1px solid var(--border);border-radius:7px;padding:5px 11px;color:var(--text2);">
        <svg width="11" height="11" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M7 4v3.5l2 1.2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        Następny cykl za <span id="countdown" style="color:var(--accent);font-weight:500;min-width:32px;display:inline-block;">—</span>
      </span>
      <?php if ($isAdmin): ?>
      <button class="btn primary" onclick="manualSync(this)">&#9654;&nbsp; Sync teraz</button>
      <?php endif; ?>
      <button class="btn" id="btn-pause" onclick="togglePause(this)" style="min-width:110px;">⏳ Ładowanie...</button>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
      <div class="stat-n ca"><?= number_format((int)$totals['a2b']) ?></div>
      <div class="stat-l"><?= htmlspecialchars($aUser) ?> → <?= htmlspecialchars($bUser) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-n cb"><?= number_format((int)$totals['b2a']) ?></div>
      <div class="stat-l"><?= htmlspecialchars($bUser) ?> → <?= htmlspecialchars($aUser) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-n cc"><?= number_format((int)$totals['a2b']+(int)$totals['b2a']) ?></div>
      <div class="stat-l">Scroble łącznie</div>
    </div>
    <div class="stat-card">
      <div class="stat-n" style="color:var(--text2);font-size:1.4rem;"><?= number_format((int)$totals['runs']) ?></div>
      <div class="stat-l">Cykli crona</div>
    </div>
    <div class="stat-card">
      <div class="stat-n" style="font-size:1.1rem;line-height:1.3;color:var(--text);">
        <?= $lastRun ? date('d.m.y', strtotime($lastRun)) : '—' ?>
      </div>
      <div class="stat-l" style="margin-top:2px;">Ostatni cykl</div>
      <?php if ($lastRun): ?>
        <div style="font-family:'DM Mono',monospace;font-size:.75rem;color:var(--accent);margin-top:3px;font-weight:500;">
          <?= date('H:i:s', strtotime($lastRun)) ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="stat-card">
      <div class="stat-n" style="font-size:1.1rem;line-height:1.3;color:var(--text);">
        <?= $lastScrobbleRow ? date('d.m.y', strtotime($lastScrobbleRow['synced_at'])) : '—' ?>
      </div>
      <div class="stat-l" style="margin-top:2px;">Ostatni scrobble</div>
      <?php if ($lastScrobbleRow): ?>
        <div style="font-family:'DM Mono',monospace;font-size:.75rem;color:var(--accent);margin-top:3px;font-weight:500;">
          <?= date('H:i:s', strtotime($lastScrobbleRow['synced_at'])) ?>
        </div>
        <div style="font-size:.72rem;color:var(--text3);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          <?= htmlspecialchars($lastScrobbleRow['artist'].' — '.$lastScrobbleRow['track']) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- WYKRES -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Aktywność — ostatnie 30 dni</span>
      <div style="display:flex;gap:14px;font-family:'DM Mono',monospace;font-size:.65rem;">
        <span style="color:var(--a);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--a);border-radius:2px;display:inline-block"></span><?= htmlspecialchars($aUser) ?></span>
        <span style="color:var(--b);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--b);border-radius:2px;display:inline-block"></span><?= htmlspecialchars($bUser) ?></span>
      </div>
    </div>
    <div class="card-body">
      <div style="position:relative;height:200px;"><canvas id="chart-activity"></canvas></div>
    </div>
  </div>

  <!-- TOP ARTYŚCI -->
  <div class="two-col">
    <div class="card">
      <div class="card-head"><span class="card-title">Top artyści · <?= htmlspecialchars($aUser) ?></span></div>
      <div class="card-body">
        <?php if (empty($topArtistsA)): ?>
          <p style="color:var(--text3);font-size:.8rem;">Brak danych</p>
        <?php else: $maxA=$topArtistsA[0]['cnt']??1; foreach($topArtistsA as $r): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px;">
            <div style="font-size:.8rem;min-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($r['artist']) ?></div>
            <div style="flex:1;background:var(--bg3);border-radius:3px;height:5px;overflow:hidden;"><div style="width:<?= round($r['cnt']/$maxA*100) ?>%;height:100%;background:var(--a);border-radius:3px;"></div></div>
            <div style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3);min-width:24px;text-align:right;"><?= $r['cnt'] ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Top artyści · <?= htmlspecialchars($bUser) ?></span></div>
      <div class="card-body">
        <?php if (empty($topArtistsB)): ?>
          <p style="color:var(--text3);font-size:.8rem;">Brak danych</p>
        <?php else: $maxB=$topArtistsB[0]['cnt']??1; foreach($topArtistsB as $r): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px;">
            <div style="font-size:.8rem;min-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($r['artist']) ?></div>
            <div style="flex:1;background:var(--bg3);border-radius:3px;height:5px;overflow:hidden;"><div style="width:<?= round($r['cnt']/$maxB*100) ?>%;height:100%;background:var(--b);border-radius:3px;"></div></div>
            <div style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3);min-width:24px;text-align:right;"><?= $r['cnt'] ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- OSTATNIE SCROBLE — dynamiczne, auto-odświeżanie -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Ostatnie zsynchronizowane scroble</span>
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">5 ostatnich · auto-odświeżanie</span>
        <a href="scrobbles.php" class="btn" style="font-size:.7rem;padding:5px 11px;">Wszystkie →</a>
      </div>
    </div>
    <div style="overflow-x:auto;">
      <table class="tbl">
        <thead><tr><th>Kierunek</th><th>Artysta</th><th>Utwór</th><th>Album</th><th>Data</th></tr></thead>
        <tbody id="scrobbles-tbody">
        <?php foreach ($recentScrobbles as $s): ?>
          <tr>
            <td><span class="badge <?= $s['direction']==='a2b'?'badge-a':'badge-b' ?>"><?= $s['direction']==='a2b'?htmlspecialchars($aUser).'→'.htmlspecialchars($bUser):htmlspecialchars($bUser).'→'.htmlspecialchars($aUser) ?></span></td>
            <td><?= htmlspecialchars($s['artist']) ?></td>
            <td><?= htmlspecialchars($s['track']) ?></td>
            <td style="color:var(--text3)"><?= htmlspecialchars($s['album']??'—') ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?= date('d.m H:i', strtotime($s['scrobbled_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recentScrobbles)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:2rem;">Brak zsynchronizowanych scrobbli</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- HISTORIA CYKLI (admin) - dynamiczna -->
  <?php if ($isAdmin): ?>
  <div class="card" style="margin-bottom:1.25rem;" id="runs-card">
    <div class="card-head">
      <span class="card-title">Ostatnie cykle crona</span>
      <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">4 ostatnie · auto-odświeżanie</span>
    </div>
    <div id="runs-table-wrap" style="overflow-x:auto;">
      <table class="tbl" id="runs-table">
        <thead><tr>
          <th>Czas</th>
          <th><?= htmlspecialchars($aUser) ?></th>
          <th><?= htmlspecialchars($bUser) ?></th>
          <th>A→B</th><th>B→A</th><th>Status</th>
        </tr></thead>
        <tbody id="runs-tbody">
        <?php foreach ($recentRuns as $r): ?>
          <tr>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;"><?= date('d.m H:i:s', strtotime($r['ran_at'])) ?></td>
            <td><?php if($r['np_a']): ?><span class="np-pill np-on"><span class="dot-s pulse"></span>słucha</span><?php else: ?><span class="np-pill np-off">—</span><?php endif;?></td>
            <td><?php if($r['np_b']): ?><span class="np-pill np-on"><span class="dot-s pulse"></span>słucha</span><?php else: ?><span class="np-pill np-off">—</span><?php endif;?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--a);font-weight:500;"><?= $r['synced_a2b']?:'' ?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--b);font-weight:500;"><?= $r['synced_b2a']?:'' ?></td>
            <td><span class="badge badge-<?= $r['status']==='ok'?'ok':($r['status']==='error'?'err':'run') ?>"><?= $r['status'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- LOG — dwie kolumny -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

    <!-- LEWA: status systemu (nowplaying, cykle) -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Status systemu</span>
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">cykle · nowplaying</span>
      </div>
      <div class="log-area" id="log-system"><div class="log-empty">Ładowanie...</div></div>
    </div>

    <!-- PRAWA: zsynchronizowane scroble -->
    <div class="card">
      <div class="card-head">
        <span class="card-title">Zsynchronizowane</span>
        <div style="display:flex;gap:8px;align-items:center;">
          <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">tylko ✓</span>
          <?php if ($isAdmin): ?><button class="btn" onclick="clearLog()" style="font-size:.68rem;padding:4px 10px;">Wyczyść</button><?php endif; ?>
        </div>
      </div>
      <div class="log-area" id="log-synced"><div class="log-empty">Ładowanie...</div></div>
    </div>

  </div>
</main>

<script>
const SYNC = 'sync.php';

const ctx = document.getElementById('chart-activity').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= $chartDays ?>,
    datasets: [
      { label: '<?= addslashes($aUser) ?>', data: <?= $chartA2B ?>, backgroundColor: 'rgba(43,122,59,0.65)', borderRadius: 4, borderSkipped: false },
      { label: '<?= addslashes($bUser) ?>', data: <?= $chartB2A ?>, backgroundColor: 'rgba(26,95,168,0.65)', borderRadius: 4, borderSkipped: false },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { bodyFont: { family: 'DM Mono' }, titleFont: { family: 'DM Mono' } } },
    scales: {
      x: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 } } },
      y: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 } } }
    }
  }
});

// PAUZA
let isPaused = false;

async function loadPauseStatus() {
  try {
    const r = await fetch(SYNC + '?action=pause_status&_=' + Date.now());
    const d = await r.json();
    updatePauseBtn(d.data?.paused ?? d.paused, d.data?.info ?? d.info);
  } catch(e) {}
}

function updatePauseBtn(paused, info) {
  isPaused = !!paused;
  const btn = document.getElementById('btn-pause');
  if (!btn) return;
  if (paused) {
    btn.innerHTML = '▶ Wznów sync';
    btn.style.borderColor = 'var(--red)';
    btn.style.color = 'var(--red)';
    const by = info?.by || '';
    const since = info?.since ? info.since.substring(11,16) : '';
    btn.title = 'Wstrzymany przez ' + by + (since ? ' o ' + since : '') + (info?.reason ? ' · ' + info.reason : '');
  } else {
    btn.innerHTML = '⏸ Wstrzymaj sync';
    btn.style.borderColor = '';
    btn.style.color = '';
    btn.title = '';
  }
}

async function togglePause(btn) {
  btn.disabled = true;
  if (!isPaused) {
    const reason = prompt('Powód wstrzymania (opcjonalnie):') ?? '';
    if (reason === null) { btn.disabled = false; return; }
    try {
      await fetch(SYNC + '?action=pause', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ by: '<?= htmlspecialchars($user['username']) ?>', reason }),
      });
    } catch(e) {}
  } else {
    try {
      await fetch(SYNC + '?action=resume', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ by: '<?= htmlspecialchars($user['username']) ?>' }),
      });
    } catch(e) {}
  }
  await loadPauseStatus();
  await loadLog();
  btn.disabled = false;
}

// ODLICZANIE
const CRON_INTERVAL = 300;
let lastRunTs = <?= $lastRun ? strtotime($lastRun) : 0 ?>;

function updateCountdown() {
  const el = document.getElementById('countdown');
  if (!el) return;
  const now = Math.floor(Date.now() / 1000);
  const remaining = Math.max(0, CRON_INTERVAL - ((now - lastRunTs) % CRON_INTERVAL));
  const m = Math.floor(remaining / 60);
  const s = remaining % 60;
  el.textContent = (m > 0 ? m + 'm ' : '') + s + 's';
  el.style.color = remaining < 10 ? 'var(--green)' : 'var(--accent)';
}

const AUSER = <?= json_encode($aUser) ?>;
const BUSER = <?= json_encode($bUser) ?>;

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderLogBox(elId, logs) {
  const el = document.getElementById(elId);
  if (!el) return;
  if (!logs.length) { el.innerHTML = '<div class="log-empty">brak wpisów</div>'; return; }
  el.innerHTML = logs.map(l =>
    '<div class="log-line">'
    + '<span class="lt">' + (l.logged_at||'').substring(11,19) + '</span>'
    + '<span class="ls">' + (l.side||'') + '</span>'
    + '<span class="lm ' + (l.type||'') + '">' + escHtml(l.message||'') + '</span>'
    + '</div>'
  ).join('');
  el.scrollTop = el.scrollHeight;
}

async function loadLog() {
  try {
    const r = await fetch(SYNC + '?action=get_status&_=' + Date.now());
    const d = await r.json();
    const logs = (d.logs || []).slice(-200);

    // LEWA: status systemu — cykle, nowplaying, kierunek kopiowania, błędy
    const systemLogs = logs.filter(l => {
      const m = l.message || '';
      return l.side === 'system'
        || l.type === 'err'
        || m.includes('słucha →')
        || m.includes('Nikt')
        || m.includes('Oboje')
        || m.includes('wstrzym')
        || m.includes('wznowi')
        || m.includes('Brak nowych');
    }).slice(-50);

    // PRAWA: tylko zsynchronizowane (✓)
    const syncedLogs = logs.filter(l => (l.message||'').startsWith('✓')).slice(-50);

    renderLogBox('log-system', systemLogs);
    renderLogBox('log-synced', syncedLogs);

    // CZAS
    if (d.last_run) {
      document.getElementById('last-update').textContent = 'Ostatni sync: ' + d.last_run.substring(11,16);
      const newTs = Math.floor(new Date(d.last_run.replace(' ','T')).getTime() / 1000);
      if (newTs > lastRunTs) lastRunTs = newTs;
    }

    // PAUZA
    if (typeof d.paused !== 'undefined') updatePauseBtn(d.paused, d.pause_info);

    // TABELA CYKLI
    const tbody = document.getElementById('runs-tbody');
    if (tbody && d.runs && d.runs.length) {
      const pillOn  = '<span class="np-pill np-on"><span class="dot-s pulse"></span>słucha</span>';
      const pillOff = '<span class="np-pill np-off">\u2014</span>';
      tbody.innerHTML = d.runs.map(run => {
        const dt = (run.ran_at || '').substring(5, 16);
        const st = run.status === 'ok' ? 'ok' : run.status === 'error' ? 'err' : 'run';
        return '<tr>'
          + '<td style="font-family:monospace;font-size:.65rem;">' + dt + '</td>'
          + '<td>' + (run.np_a == 1 ? pillOn : pillOff) + '</td>'
          + '<td>' + (run.np_b == 1 ? pillOn : pillOff) + '</td>'
          + '<td style="font-family:monospace;font-size:.72rem;color:var(--a);font-weight:500;">' + (run.synced_a2b || '') + '</td>'
          + '<td style="font-family:monospace;font-size:.72rem;color:var(--b);font-weight:500;">' + (run.synced_b2a || '') + '</td>'
          + '<td><span class="badge badge-' + st + '">' + run.status + '</span></td>'
          + '</tr>';
      }).join('');
    }

    // OSTATNIE SCROBLE — odśwież 5 najnowszych z bazy
    if (d.recent_scrobbles) {
      const stbody = document.getElementById('scrobbles-tbody');
      if (stbody) {
        if (!d.recent_scrobbles.length) {
          stbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:1.5rem;">Brak scrobbli</td></tr>';
        } else {
          stbody.innerHTML = d.recent_scrobbles.map(s => {
            const isA2B = s.direction === 'a2b';
            const bc = isA2B ? 'badge-a' : 'badge-b';
            const lb = isA2B ? AUSER+'→'+BUSER : BUSER+'→'+AUSER;
            const dt = (s.scrobbled_at||'').substring(5,16);
            return '<tr>'
              + '<td><span class="badge '+bc+'">'+lb+'</span></td>'
              + '<td>'+escHtml(s.artist)+'</td>'
              + '<td>'+escHtml(s.track)+'</td>'
              + '<td style="color:var(--text3)">'+escHtml(s.album||'—')+'</td>'
              + '<td style="font-family:monospace;font-size:.65rem;color:var(--text3)">'+dt+'</td>'
              + '</tr>';
          }).join('');
        }
      }
    }

  } catch(e) { console.error('loadLog error:', e); }
}

async function manualSync(btn) {
  btn.disabled = true;
  btn.innerHTML = '⟳&nbsp; Synchronizuję...';
  btn.style.background = '';
  try {
    const r = await fetch(SYNC + '?action=run_sync&_=' + Date.now(), { method: 'GET', cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (d.status === 'ok') {
      btn.innerHTML = '✓&nbsp; Gotowe (A→B:' + (d.a2b||0) + ' B→A:' + (d.b2a||0) + ')';
      btn.style.background = 'var(--green)';
    } else if (d.status === 'locked') {
      btn.innerHTML = '⏳&nbsp; Cron właśnie działa';
      btn.style.background = '#888';
    } else {
      btn.innerHTML = '✕&nbsp; ' + (d.msg || d.error || 'Błąd');
      btn.style.background = 'var(--red)';
    }
    await loadLog();
  } catch(e) {
    btn.innerHTML = '✕&nbsp; ' + e.message;
    btn.style.background = 'var(--red)';
  }
  setTimeout(() => {
    btn.disabled = false;
    btn.innerHTML = '&#9654;&nbsp; Sync teraz';
    btn.style.background = '';
  }, 4000);
}

async function clearLog() {
  await fetch(SYNC + '?action=clear_log');
  loadLog();
}

updateCountdown();
setInterval(updateCountdown, 1000);
loadPauseStatus();
loadLog();
setInterval(loadLog, 30000);
</script>
</body></html>
