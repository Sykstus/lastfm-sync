<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user    = currentUser();
$db      = getDB();
$isAdmin = $user['role'] === 'admin';

$cfg   = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
$aUser = $cfg['a_user'] ?? 'Konto A';
$bUser = $cfg['b_user'] ?? 'Konto B';

// Okres
$period = $_GET['period'] ?? '90';
$period = in_array($period, ['7','30','90','180','365']) ? (int)$period : 90;
$dateFrom = date('Y-m-d', strtotime("-{$period} days"));

// ─── TOP ARTYŚCI ─────────────────────────────────────────────────────────────
$topArtistsA = $db->prepare("
    SELECT artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='a2b' AND scrobbled_at >= ?
    GROUP BY artist ORDER BY cnt DESC LIMIT 15
"); $topArtistsA->execute([$dateFrom]); $topArtistsA = $topArtistsA->fetchAll();

$topArtistsB = $db->prepare("
    SELECT artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='b2a' AND scrobbled_at >= ?
    GROUP BY artist ORDER BY cnt DESC LIMIT 15
"); $topArtistsB->execute([$dateFrom]); $topArtistsB = $topArtistsB->fetchAll();

// ─── TOP ALBUMY ───────────────────────────────────────────────────────────────
$topAlbumsA = $db->prepare("
    SELECT album, artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='a2b' AND scrobbled_at >= ? AND album IS NOT NULL AND album != ''
    GROUP BY album, artist ORDER BY cnt DESC LIMIT 10
"); $topAlbumsA->execute([$dateFrom]); $topAlbumsA = $topAlbumsA->fetchAll();

$topAlbumsB = $db->prepare("
    SELECT album, artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='b2a' AND scrobbled_at >= ? AND album IS NOT NULL AND album != ''
    GROUP BY album, artist ORDER BY cnt DESC LIMIT 10
"); $topAlbumsB->execute([$dateFrom]); $topAlbumsB = $topAlbumsB->fetchAll();

// ─── TOP UTWORY ───────────────────────────────────────────────────────────────
$topTracksA = $db->prepare("
    SELECT track, artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='a2b' AND scrobbled_at >= ?
    GROUP BY track, artist ORDER BY cnt DESC LIMIT 10
"); $topTracksA->execute([$dateFrom]); $topTracksA = $topTracksA->fetchAll();

$topTracksB = $db->prepare("
    SELECT track, artist, COUNT(*) cnt FROM scrobbles
    WHERE direction='b2a' AND scrobbled_at >= ?
    GROUP BY track, artist ORDER BY cnt DESC LIMIT 10
"); $topTracksB->execute([$dateFrom]); $topTracksB = $topTracksB->fetchAll();

// ─── PORÓWNANIE GUSTÓW ────────────────────────────────────────────────────────
// Artyści słuchani przez obu
$artistsA = array_column($topArtistsA, 'cnt', 'artist');
$artistsB = array_column($topArtistsB, 'cnt', 'artist');
$common   = array_intersect_key($artistsA, $artistsB);
arsort($common);
$onlyA = array_diff_key($artistsA, $artistsB);
$onlyB = array_diff_key($artistsB, $artistsA);
arsort($onlyA); arsort($onlyB);

// Podobieństwo (Jaccard)
$totalUnique = count(array_unique(array_merge(array_keys($artistsA), array_keys($artistsB))));
$similarity  = $totalUnique > 0 ? round(count($common) / $totalUnique * 100) : 0;

// ─── HEATMAPA ────────────────────────────────────────────────────────────────
// Godzina (0-23) × Dzień tygodnia (0=Pon, 6=Niedz)
$heatRaw = $db->prepare("
    SELECT
        HOUR(scrobbled_at) as h,
        WEEKDAY(scrobbled_at) as d,
        direction,
        COUNT(*) cnt
    FROM scrobbles
    WHERE scrobbled_at >= ?
    GROUP BY h, d, direction
"); $heatRaw->execute([$dateFrom]); $heatRaw = $heatRaw->fetchAll();

// Buduj macierz [day][hour] => [a2b, b2a, total]
$heat = [];
for ($d=0;$d<7;$d++) for ($h=0;$h<24;$h++) $heat[$d][$h] = ['a2b'=>0,'b2a'=>0,'total'=>0];
foreach ($heatRaw as $r) {
    $heat[$r['d']][$r['h']][$r['direction']] += $r['cnt'];
    $heat[$r['d']][$r['h']]['total'] += $r['cnt'];
}
$maxHeat = 0;
foreach ($heat as $row) foreach ($row as $cell) if ($cell['total'] > $maxHeat) $maxHeat = $cell['total'];

// ─── AKTYWNOŚĆ DZIENNA ────────────────────────────────────────────────────────
$daily = $db->prepare("
    SELECT DATE(scrobbled_at) day, direction, COUNT(*) cnt
    FROM scrobbles WHERE scrobbled_at >= ?
    GROUP BY DATE(scrobbled_at), direction ORDER BY day
"); $daily->execute([$dateFrom]); $daily = $daily->fetchAll();

$dailyMap = [];
foreach ($daily as $r) {
    $dailyMap[$r['day']][$r['direction']] = (int)$r['cnt'];
}

$chartDays = json_encode(array_keys($dailyMap));
$chartA    = json_encode(array_map(fn($d) => $d['a2b'] ?? 0, $dailyMap));
$chartB    = json_encode(array_map(fn($d) => $d['b2a'] ?? 0, $dailyMap));

// ─── TOTALE ───────────────────────────────────────────────────────────────────
$totals = $db->prepare("
    SELECT
        SUM(CASE WHEN direction='a2b' THEN 1 ELSE 0 END) a2b,
        SUM(CASE WHEN direction='b2a' THEN 1 ELSE 0 END) b2a,
        COUNT(DISTINCT artist) artists,
        COUNT(DISTINCT track) tracks
    FROM scrobbles WHERE scrobbled_at >= ?
"); $totals->execute([$dateFrom]); $totals = $totals->fetch();

$days_labels = ['Pon','Wt','Śr','Czw','Pt','Sob','Niedz'];

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Statystyki</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <?php foreach (['7'=>'7 dni','30'=>'30 dni','90'=>'3 mies.','180'=>'6 mies.','365'=>'rok'] as $v=>$l): ?>
        <a href="?period=<?=$v?>" style="font-family:'DM Mono',monospace;font-size:.68rem;padding:5px 12px;border-radius:6px;border:1px solid <?=$period==(int)$v?'var(--accent)':'var(--border)'?>;color:<?=$period==(int)$v?'var(--accent)':'var(--text2)'?>;background:<?=$period==(int)$v?'rgba(200,80,60,.06)':'var(--bg2)'?>;text-decoration:none;transition:all .15s;"><?=$l?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TOTALE -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">
    <div class="stat-card">
      <div class="stat-n ca"><?= number_format((int)$totals['a2b']) ?></div>
      <div class="stat-l"><?= htmlspecialchars($aUser) ?> → <?= htmlspecialchars($bUser) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-n cb"><?= number_format((int)$totals['b2a']) ?></div>
      <div class="stat-l"><?= htmlspecialchars($bUser) ?> → <?= htmlspecialchars($aUser) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-n" style="color:var(--accent)"><?= number_format((int)$totals['artists']) ?></div>
      <div class="stat-l">Unikalnych artystów</div>
    </div>
    <div class="stat-card">
      <div class="stat-n" style="color:var(--text2)"><?= number_format((int)$totals['tracks']) ?></div>
      <div class="stat-l">Unikalnych utworów</div>
    </div>
  </div>

  <!-- WYKRES DZIENNY -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Aktywność dzienna</span>
      <div style="display:flex;gap:14px;font-family:'DM Mono',monospace;font-size:.62rem;">
        <span style="color:var(--a);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--a);border-radius:2px;display:inline-block"></span><?= htmlspecialchars($aUser) ?></span>
        <span style="color:var(--b);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--b);border-radius:2px;display:inline-block"></span><?= htmlspecialchars($bUser) ?></span>
      </div>
    </div>
    <div class="card-body"><div style="position:relative;height:180px;"><canvas id="chart-daily"></canvas></div></div>
  </div>

  <!-- HEATMAPA -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Heatmapa aktywności — godzina × dzień tygodnia</span>
      <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">im ciemniej tym więcej scrobbli</span>
    </div>
    <div class="card-body" style="overflow-x:auto;">
      <div style="display:grid;grid-template-columns:40px repeat(24,1fr);gap:3px;min-width:600px;">
        <!-- nagłówek godzin -->
        <div></div>
        <?php for($h=0;$h<24;$h++): ?>
          <div style="text-align:center;font-family:'DM Mono',monospace;font-size:.55rem;color:var(--text3);padding-bottom:3px;"><?=$h?></div>
        <?php endfor; ?>
        <!-- wiersze dni -->
        <?php foreach($days_labels as $di => $dl): ?>
          <div style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text2);display:flex;align-items:center;padding-right:6px;"><?=$dl?></div>
          <?php for($h=0;$h<24;$h++):
            $cell  = $heat[$di][$h];
            $total = $cell['total'];
            $ratio = $maxHeat > 0 ? $total / $maxHeat : 0;
            $alpha = round($ratio * 0.85 + ($ratio > 0 ? 0.1 : 0), 2);
            // Kolor mieszany jeśli oba
            if ($cell['a2b'] > 0 && $cell['b2a'] > 0) $color = "43,122,59"; // zielony (oba)
            elseif ($cell['a2b'] > 0) $color = "43,122,59"; // zielony A
            else $color = "26,95,168"; // niebieski B
            $bg = $total > 0 ? "rgba($color,$alpha)" : "var(--bg3)";
            $title = $total > 0 ? "$dl {$h}:00 — $total scrobbli" : '';
          ?>
            <div title="<?=$title?>" style="height:22px;border-radius:3px;background:<?=$bg?>;cursor:<?=$total>0?'pointer':'default'?>;transition:opacity .15s;" <?=$total>0?'onmouseover="this.style.opacity=\'.7\'" onmouseout="this.style.opacity=\'1\'"':''?>></div>
          <?php endfor; ?>
        <?php endforeach; ?>
      </div>
      <!-- legenda -->
      <div style="display:flex;gap:16px;margin-top:12px;font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);">
        <div style="display:flex;align-items:center;gap:5px;"><div style="width:12px;height:12px;border-radius:2px;background:rgba(43,122,59,0.7)"></div><?=htmlspecialchars($aUser)?> lub oboje</div>
        <div style="display:flex;align-items:center;gap:5px;"><div style="width:12px;height:12px;border-radius:2px;background:rgba(26,95,168,0.7)"></div><?=htmlspecialchars($bUser)?> tylko</div>
      </div>
    </div>
  </div>

  <!-- PORÓWNANIE GUSTÓW -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Porównanie gustów</span>
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">Podobieństwo:</span>
        <span style="font-family:'DM Mono',monospace;font-size:.8rem;font-weight:600;color:var(--accent)"><?=$similarity?>%</span>
      </div>
    </div>
    <div class="card-body">
      <!-- Pasek podobieństwa -->
      <div style="background:var(--bg3);border-radius:4px;height:8px;margin-bottom:1.5rem;overflow:hidden;">
        <div style="width:<?=$similarity?>%;height:100%;background:linear-gradient(90deg,var(--a),var(--accent));border-radius:4px;transition:width .6s ease;"></div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <!-- Tylko A -->
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--a);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Tylko <?=htmlspecialchars($aUser)?></div>
          <?php foreach(array_slice($onlyA,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--a);flex-shrink:0;"><?=$cnt?></span>
            </div>
          <?php endforeach; ?>
          <?php if(empty($onlyA)): ?><p style="font-size:.78rem;color:var(--text3);">Brak</p><?php endif; ?>
        </div>

        <!-- Wspólni -->
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--accent);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Wspólni ♥</div>
          <?php foreach(array_slice($common,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent);flex-shrink:0;"><?=$cnt?></span>
            </div>
          <?php endforeach; ?>
          <?php if(empty($common)): ?><p style="font-size:.78rem;color:var(--text3);">Brak wspólnych</p><?php endif; ?>
        </div>

        <!-- Tylko B -->
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--b);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Tylko <?=htmlspecialchars($bUser)?></div>
          <?php foreach(array_slice($onlyB,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--b);flex-shrink:0;"><?=$cnt?></span>
            </div>
          <?php endforeach; ?>
          <?php if(empty($onlyB)): ?><p style="font-size:.78rem;color:var(--text3);">Brak</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- TOP LISTY -->
  <div class="two-col" style="margin-bottom:1.25rem;">
    <!-- TOP ARTYŚCI -->
    <div class="card">
      <div class="card-head"><span class="card-title">Top artyści · <?=htmlspecialchars($aUser)?></span></div>
      <div class="card-body" style="padding-top:.75rem;">
        <?php if(empty($topArtistsA)): ?>
          <p style="color:var(--text3);font-size:.78rem;">Brak danych</p>
        <?php else: $maxA=$topArtistsA[0]['cnt']??1; foreach($topArtistsA as $i=>$r): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:16px;text-align:right;"><?=$i+1?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
              <div style="background:var(--bg3);border-radius:2px;height:4px;margin-top:3px;overflow:hidden;">
                <div style="width:<?=round($r['cnt']/$maxA*100)?>%;height:100%;background:var(--a);border-radius:2px;"></div>
              </div>
            </div>
            <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--a);flex-shrink:0;"><?=$r['cnt']?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Top artyści · <?=htmlspecialchars($bUser)?></span></div>
      <div class="card-body" style="padding-top:.75rem;">
        <?php if(empty($topArtistsB)): ?>
          <p style="color:var(--text3);font-size:.78rem;">Brak danych</p>
        <?php else: $maxB=$topArtistsB[0]['cnt']??1; foreach($topArtistsB as $i=>$r): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:16px;text-align:right;"><?=$i+1?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
              <div style="background:var(--bg3);border-radius:2px;height:4px;margin-top:3px;overflow:hidden;">
                <div style="width:<?=round($r['cnt']/$maxB*100)?>%;height:100%;background:var(--b);border-radius:2px;"></div>
              </div>
            </div>
            <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--b);flex-shrink:0;"><?=$r['cnt']?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <div class="two-col" style="margin-bottom:1.25rem;">
    <!-- TOP ALBUMY -->
    <div class="card">
      <div class="card-head"><span class="card-title">Top albumy · <?=htmlspecialchars($aUser)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Album</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($topAlbumsA as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['album'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--a);font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($topAlbumsA)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Top albumy · <?=htmlspecialchars($bUser)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Album</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($topAlbumsB as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['album'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--b);font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($topAlbumsB)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="two-col">
    <!-- TOP UTWORY -->
    <div class="card">
      <div class="card-head"><span class="card-title">Top utwory · <?=htmlspecialchars($aUser)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Utwór</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($topTracksA as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['track'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--a);font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($topTracksA)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Top utwory · <?=htmlspecialchars($bUser)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Utwór</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($topTracksB as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['track'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--b);font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($topTracksB)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="height:2rem;"></div>
</main>

<script>
const ctx = document.getElementById('chart-daily').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= $chartDays ?>,
    datasets: [
      { label: '<?= addslashes($aUser) ?>', data: <?= $chartA ?>, backgroundColor: 'rgba(43,122,59,0.65)', borderRadius: 3 },
      { label: '<?= addslashes($bUser) ?>', data: <?= $chartB ?>, backgroundColor: 'rgba(26,95,168,0.65)', borderRadius: 3 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 }, maxTicksLimit: 12 } },
      y: { stacked: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 } } }
    }
  }
});
</script>
</body></html>
