<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user    = currentUser();
$db      = getDB();
$isAdmin = $user['role'] === 'admin';

$cfg   = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
$aUser = $cfg['a_user'] ?? 'Konto A';
$bUser = $cfg['b_user'] ?? 'Konto B';

$period   = $_GET['period'] ?? '30';
$period   = in_array($period, ['7','30','90','180','365','all']) ? $period : '30';
$dateFrom = $period === 'all' ? '2000-01-01' : date('Y-m-d', strtotime("-{$period} days"));
$topLimit = (int)($_GET['limit'] ?? 10);
$topLimit = in_array($topLimit, [10,25,50]) ? $topLimit : 10;

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function q($db, $sql, $params=[]) {
    $s = $db->prepare($sql); $s->execute($params); return $s->fetchAll();
}
function q1($db, $sql, $params=[]) {
    $s = $db->prepare($sql); $s->execute($params); return $s->fetchColumn();
}

// ─── TOTALE ───────────────────────────────────────────────────────────────────
$totals = q($db, "
    SELECT
        SUM(CASE WHEN direction='a2b' THEN 1 ELSE 0 END) a2b,
        SUM(CASE WHEN direction='b2a' THEN 1 ELSE 0 END) b2a,
        COUNT(*) total,
        COUNT(DISTINCT artist) artists,
        COUNT(DISTINCT track) tracks,
        COUNT(DISTINCT album) albums,
        MIN(scrobbled_at) first_scrobble,
        MAX(scrobbled_at) last_scrobble
    FROM scrobbles WHERE scrobbled_at >= ?
", [$dateFrom])[0];

// Totale all-time (do avg)
$allTime = q($db, "SELECT COUNT(*) total, MIN(scrobbled_at) first FROM scrobbles")[0];
$daysSinceFirst = $allTime['first'] ? max(1, (int)((time() - strtotime($allTime['first'])) / 86400)) : 1;
$avgPerDay = round($allTime['total'] / $daysSinceFirst, 1);

// ─── TOP ARTYŚCI ─────────────────────────────────────────────────────────────
$topArtistsA = q($db, "SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);
$topArtistsB = q($db, "SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);

// ─── TOP ALBUMY ───────────────────────────────────────────────────────────────
$topAlbumsA = q($db, "SELECT album, artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ? AND album IS NOT NULL AND album != '' GROUP BY album, artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);
$topAlbumsB = q($db, "SELECT album, artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ? AND album IS NOT NULL AND album != '' GROUP BY album, artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);

// ─── TOP UTWORY ───────────────────────────────────────────────────────────────
$topTracksA = q($db, "SELECT track, artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ? GROUP BY track, artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);
$topTracksB = q($db, "SELECT track, artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ? GROUP BY track, artist ORDER BY cnt DESC LIMIT $topLimit", [$dateFrom]);

// ─── KALENDARZ AKTYWNOŚCI (ostatnie 365 dni) ──────────────────────────────────
$calData = q($db, "
    SELECT DATE(scrobbled_at) day, COUNT(*) cnt
    FROM scrobbles
    WHERE scrobbled_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
    GROUP BY DATE(scrobbled_at)
    ORDER BY day
");
$calMap = [];
foreach ($calData as $r) $calMap[$r['day']] = (int)$r['cnt'];
$calMax = $calMap ? max($calMap) : 1;

// ─── PORÓWNANIE TYGODNIOWE ────────────────────────────────────────────────────
$thisWeek = q($db, "SELECT COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")[0]['cnt'];
$lastWeek = q($db, "SELECT COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND scrobbled_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")[0]['cnt'];
$yearAgo  = q($db, "SELECT COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= DATE_SUB(NOW(), INTERVAL 372 DAY) AND scrobbled_at < DATE_SUB(NOW(), INTERVAL 365 DAY)")[0]['cnt'];
$weekDiff = $lastWeek > 0 ? round(($thisWeek - $lastWeek) / $lastWeek * 100) : 0;

// ─── AKTYWNOŚĆ WG GODZINY ─────────────────────────────────────────────────────
$byHour = q($db, "SELECT HOUR(scrobbled_at) h, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY h ORDER BY h", [$dateFrom]);
$hourMap = array_fill(0, 24, 0);
foreach ($byHour as $r) $hourMap[$r['h']] = (int)$r['cnt'];
$peakHour = array_search(max($hourMap), $hourMap);

// ─── AKTYWNOŚĆ WG DNIA TYGODNIA ───────────────────────────────────────────────
$byDay = q($db, "SELECT WEEKDAY(scrobbled_at) d, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY d ORDER BY d", [$dateFrom]);
$dayMap = array_fill(0, 7, 0);
foreach ($byDay as $r) $dayMap[$r['d']] = (int)$r['cnt'];
$days_labels = ['Pon','Wt','Śr','Czw','Pt','Sob','Niedz'];
$peakDay = $days_labels[array_search(max($dayMap), $dayMap)];

// ─── HEATMAPA ────────────────────────────────────────────────────────────────
$heatRaw = q($db, "SELECT HOUR(scrobbled_at) h, WEEKDAY(scrobbled_at) d, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY h, d", [$dateFrom]);
$heat = []; for($d=0;$d<7;$d++) for($h=0;$h<24;$h++) $heat[$d][$h]=0;
foreach ($heatRaw as $r) $heat[$r['d']][$r['h']] = (int)$r['cnt'];
$heatMax = max(array_merge(...array_map('array_values', $heat))) ?: 1;

// ─── PORÓWNANIE GUSTÓW ────────────────────────────────────────────────────────
$artistsA = array_column($topArtistsA, 'cnt', 'artist');
$artistsB = array_column($topArtistsB, 'cnt', 'artist');
$common   = array_intersect_key($artistsA, $artistsB);
arsort($common);
$onlyA = array_diff_key($artistsA, $artistsB); arsort($onlyA);
$onlyB = array_diff_key($artistsB, $artistsA); arsort($onlyB);
$totalUnique = count(array_unique(array_merge(array_keys($artistsA), array_keys($artistsB))));
$similarity  = $totalUnique > 0 ? round(count($common) / $totalUnique * 100) : 0;

// ─── AKTYWNOŚĆ DZIENNA ────────────────────────────────────────────────────────
$daily = q($db, "SELECT DATE(scrobbled_at) day, direction, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY DATE(scrobbled_at), direction ORDER BY day", [$dateFrom]);
$dailyMap = [];
foreach ($daily as $r) $dailyMap[$r['day']][$r['direction']] = (int)$r['cnt'];
$chartDays = json_encode(array_keys($dailyMap));
$chartA    = json_encode(array_map(fn($d) => $d['a2b'] ?? 0, $dailyMap));
$chartB    = json_encode(array_map(fn($d) => $d['b2a'] ?? 0, $dailyMap));

// ─── NOWI ARTYŚCI (pierwsze scroble w tym okresie) ───────────────────────────
$newArtists = q($db, "
    SELECT artist, MIN(scrobbled_at) first_heard, COUNT(*) cnt
    FROM scrobbles
    WHERE scrobbled_at >= ?
    AND artist NOT IN (SELECT DISTINCT artist FROM scrobbles WHERE scrobbled_at < ?)
    GROUP BY artist ORDER BY first_heard DESC LIMIT 8
", [$dateFrom, $dateFrom]);

// ─── STREAK ───────────────────────────────────────────────────────────────────
$allDays = q($db, "SELECT DISTINCT DATE(scrobbled_at) day FROM scrobbles ORDER BY day DESC");
$streak = 0; $today = date('Y-m-d'); $check = $today;
foreach ($allDays as $r) {
    if ($r['day'] === $check || $r['day'] === date('Y-m-d', strtotime($check.' -1 day'))) {
        $streak++;
        $check = $r['day'];
    } else break;
}

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Statystyki</h1>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
      <?php foreach(['7'=>'7 dni','30'=>'30 dni','90'=>'3 mies.','180'=>'6 mies.','365'=>'rok','all'=>'Wszystko'] as $v=>$l):
        $active = ($period === $v) || ($period == $v);
      ?>
        <a href="?period=<?=$v?>&limit=<?=$topLimit?>" style="font-family:'DM Mono',monospace;font-size:.68rem;padding:5px 12px;border-radius:6px;border:1.5px solid <?=$active?'var(--accent)':'var(--border)'?>;color:<?=$active?'var(--accent)':'var(--text2)'?>;background:<?=$active?'rgba(200,80,60,.06)':'var(--bg2)'?>;text-decoration:none;"><?=$l?></a>
      <?php endforeach; ?>
      <span style="color:var(--text3);font-size:.72rem;margin-left:4px;">Top:</span>
      <?php foreach([10=>10,25=>25,50=>50] as $v=>$l):
        $active = ($topLimit === $v);
      ?>
        <a href="?period=<?=$period?>&limit=<?=$v?>" style="font-family:'DM Mono',monospace;font-size:.68rem;padding:5px 10px;border-radius:6px;border:1.5px solid <?=$active?'var(--accent)':'var(--border)'?>;color:<?=$active?'var(--accent)':'var(--text2)'?>;background:<?=$active?'rgba(200,80,60,.06)':'var(--bg2)'?>;text-decoration:none;"><?=$l?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TOTALE -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.5rem;">
    <?php
    $cards = [
      ['val'=>number_format((int)$totals['total']),    'lbl'=>'Scroble łącznie',    'color'=>'var(--accent)'],
      ['val'=>number_format((int)$totals['artists']),  'lbl'=>'Unikalnych artystów','color'=>'var(--a)'],
      ['val'=>number_format((int)$totals['albums']),   'lbl'=>'Unikalnych albumów', 'color'=>'var(--b)'],
      ['val'=>number_format((int)$totals['tracks']),   'lbl'=>'Unikalnych utworów', 'color'=>'var(--text2)'],
      ['val'=>number_format((int)$totals['a2b']),      'lbl'=>htmlspecialchars($aUser).' → '.htmlspecialchars($bUser), 'color'=>'var(--a)'],
      ['val'=>number_format((int)$totals['b2a']),      'lbl'=>htmlspecialchars($bUser).' → '.htmlspecialchars($aUser), 'color'=>'var(--b)'],
      ['val'=>$avgPerDay,                              'lbl'=>'Śr. scrobbli / dzień','color'=>'var(--text2)'],
      ['val'=>$streak,                                 'lbl'=>'Dni z rzędu 🔥',     'color'=>'var(--accent)'],
    ];
    foreach ($cards as $c): ?>
    <div class="stat-card">
      <div class="stat-n" style="color:<?=$c['color']?>"><?=$c['val']?></div>
      <div class="stat-l"><?=$c['lbl']?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- PORÓWNANIE TYGODNIOWE -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head"><span class="card-title">Porównanie tygodniowe</span></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);">
      <?php
      $weeks = [
        ['val'=>$thisWeek, 'lbl'=>'Ten tydzień', 'color'=>'var(--accent)'],
        ['val'=>$lastWeek, 'lbl'=>'Poprzedni tydzień', 'color'=>'var(--text2)'],
        ['val'=>$yearAgo,  'lbl'=>'Rok temu', 'color'=>'var(--text3)'],
      ];
      foreach ($weeks as $i=>$w): ?>
      <div style="padding:1.25rem;<?=$i<2?'border-right:1px solid var(--border);':''?>">
        <div style="font-size:2rem;font-weight:300;color:<?=$w['color']?>;font-variant-numeric:tabular-nums;"><?=number_format($w['val'])?></div>
        <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;"><?=$w['lbl']?></div>
        <?php if($i===0 && $lastWeek>0): $sign=$weekDiff>=0?'+':''; $col=$weekDiff>=0?'var(--a)':'var(--red)'; ?>
        <div style="font-family:'DM Mono',monospace;font-size:.72rem;color:<?=$col?>;margin-top:4px;font-weight:500;"><?=$sign.$weekDiff?>% vs poprzedni</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- KALENDARZ AKTYWNOŚCI -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Kalendarz aktywności — ostatnie 365 dni</span>
      <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);">jak GitHub contributions</span>
    </div>
    <div class="card-body" style="overflow-x:auto;">
      <?php
      // Buduj siatke 52 tygodnie × 7 dni
      $calStart = date('Y-m-d', strtotime('-364 days'));
      // Przesuń do poniedziałku
      $startDow = date('N', strtotime($calStart)); // 1=pon
      $calStart = date('Y-m-d', strtotime($calStart . ' -' . ($startDow-1) . ' days'));
      $months = []; $lastMonth = '';
      ?>
      <div style="display:flex;gap:3px;align-items:flex-start;min-width:700px;">
        <!-- Etykiety dni -->
        <div style="display:flex;flex-direction:column;gap:3px;margin-top:18px;">
          <?php foreach(['','Wt','','Czw','','Sob',''] as $dl): ?>
            <div style="height:13px;font-family:'DM Mono',monospace;font-size:.58rem;color:var(--text3);line-height:13px;width:20px;"><?=$dl?></div>
          <?php endforeach; ?>
        </div>
        <!-- Kolumny tygodniowe -->
        <div style="flex:1;">
          <!-- Etykiety miesięcy -->
          <div style="display:flex;gap:3px;margin-bottom:3px;height:14px;position:relative;">
          <?php
          $d = new DateTime($calStart);
          for ($week=0; $week<53; $week++) {
              $mon = $d->format('M');
              if ($mon !== $lastMonth) {
                  echo '<div style="font-family:\'DM Mono\',monospace;font-size:.58rem;color:var(--text3);position:absolute;left:'.($week*16).'px;">'.$mon.'</div>';
                  $lastMonth = $mon;
              }
              $d->modify('+7 days');
          }
          ?>
          </div>
          <!-- Kwadraty -->
          <div style="display:flex;gap:3px;">
          <?php
          $d = new DateTime($calStart);
          $today = date('Y-m-d');
          for ($week=0; $week<53; $week++) {
              echo '<div style="display:flex;flex-direction:column;gap:3px;">';
              for ($dow=0; $dow<7; $dow++) {
                  $day = $d->format('Y-m-d');
                  $cnt = $calMap[$day] ?? 0;
                  $isFuture = $day > $today;
                  if ($isFuture) {
                      echo '<div style="width:13px;height:13px;border-radius:2px;background:transparent;"></div>';
                  } else {
                      $ratio = $cnt / $calMax;
                      if ($cnt === 0) $bg = 'var(--bg3)';
                      elseif ($ratio < 0.25) $bg = 'rgba(200,80,60,0.2)';
                      elseif ($ratio < 0.5)  $bg = 'rgba(200,80,60,0.45)';
                      elseif ($ratio < 0.75) $bg = 'rgba(200,80,60,0.7)';
                      else $bg = 'var(--accent)';
                      $title = $cnt > 0 ? "$day: $cnt scrobbli" : $day;
                      echo '<div title="'.$title.'" style="width:13px;height:13px;border-radius:2px;background:'.$bg.';cursor:'.($cnt>0?'pointer':'default').';" '.($cnt>0?'onmouseover="this.style.opacity=\'.7\'" onmouseout="this.style.opacity=\'1\'"':'').'></div>';
                  }
                  $d->modify('+1 day');
              }
              $d->modify('-7 days'); $d->modify('+7 days'); // reset
              echo '</div>';
              $d->modify('+7 days');
              $d = new DateTime($calStart); $d->modify('+'.(($week+1)*7).' days');
          }
          ?>
          </div>
        </div>
      </div>
      <!-- Legenda -->
      <div style="display:flex;align-items:center;gap:6px;margin-top:10px;font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);">
        Mniej
        <?php foreach(['var(--bg3)','rgba(200,80,60,0.2)','rgba(200,80,60,0.45)','rgba(200,80,60,0.7)','var(--accent)'] as $bg): ?>
          <div style="width:13px;height:13px;border-radius:2px;background:<?=$bg?>"></div>
        <?php endforeach; ?>
        Więcej
      </div>
    </div>
  </div>

  <!-- WYKRES DZIENNY -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Aktywność dzienna</span>
      <div style="display:flex;gap:14px;font-family:'DM Mono',monospace;font-size:.62rem;">
        <span style="color:var(--a);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--a);border-radius:2px;display:inline-block"></span><?=htmlspecialchars($aUser)?></span>
        <span style="color:var(--b);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:var(--b);border-radius:2px;display:inline-block"></span><?=htmlspecialchars($bUser)?></span>
      </div>
    </div>
    <div class="card-body"><div style="position:relative;height:180px;"><canvas id="chart-daily"></canvas></div></div>
  </div>

  <!-- AKTYWNOŚĆ WG GODZINY + DZIEŃ TYGODNIA -->
  <div class="two-col" style="margin-bottom:1.25rem;">
    <div class="card">
      <div class="card-head">
        <span class="card-title">Aktywność wg godziny</span>
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--accent);">peak: <?=$peakHour?>:00</span>
      </div>
      <div class="card-body"><div style="position:relative;height:140px;"><canvas id="chart-hours"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-head">
        <span class="card-title">Aktywność wg dnia tygodnia</span>
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--accent);">peak: <?=$peakDay?></span>
      </div>
      <div class="card-body"><div style="position:relative;height:140px;"><canvas id="chart-days"></canvas></div></div>
    </div>
  </div>

  <!-- HEATMAPA -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Heatmapa — godzina × dzień tygodnia</span>
      <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">im ciemniej tym więcej</span>
    </div>
    <div class="card-body" style="overflow-x:auto;">
      <div style="display:grid;grid-template-columns:36px repeat(24,1fr);gap:3px;min-width:560px;">
        <div></div>
        <?php for($h=0;$h<24;$h++): ?>
          <div style="text-align:center;font-family:'DM Mono',monospace;font-size:.55rem;color:var(--text3);padding-bottom:2px;"><?=$h?></div>
        <?php endfor; ?>
        <?php foreach($days_labels as $di=>$dl): ?>
          <div style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text2);display:flex;align-items:center;"><?=$dl?></div>
          <?php for($h=0;$h<24;$h++):
            $cnt=$heat[$di][$h]; $ratio=$cnt/$heatMax;
            $alpha=round($ratio*0.85+($ratio>0?0.1:0),2);
            $bg=$cnt>0?"rgba(200,80,60,$alpha)":'var(--bg3)';
            $title=$cnt>0?"$dl {$h}:00 — $cnt scrobbli":'';
          ?>
            <div title="<?=$title?>" style="height:20px;border-radius:3px;background:<?=$bg?>;<?=$cnt>0?'cursor:pointer;':''?>" <?=$cnt>0?'onmouseover="this.style.opacity=\'.7\'" onmouseout="this.style.opacity=\'1\'"':''?>></div>
          <?php endfor; ?>
        <?php endforeach; ?>
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
      <div style="background:var(--bg3);border-radius:4px;height:8px;margin-bottom:1.5rem;overflow:hidden;">
        <div style="width:<?=$similarity?>%;height:100%;background:linear-gradient(90deg,var(--a),var(--accent));border-radius:4px;"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--a);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Tylko <?=htmlspecialchars($aUser)?></div>
          <?php foreach(array_slice($onlyA,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--a);"><?=$cnt?></span>
            </div>
          <?php endforeach; if(empty($onlyA)): ?><p style="font-size:.78rem;color:var(--text3);">Brak</p><?php endif; ?>
        </div>
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--accent);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Wspólni ♥</div>
          <?php foreach(array_slice($common,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent);"><?=$cnt?></span>
            </div>
          <?php endforeach; if(empty($common)): ?><p style="font-size:.78rem;color:var(--text3);">Brak wspólnych</p><?php endif; ?>
        </div>
        <div>
          <div style="font-family:'DM Mono',monospace;font-size:.63rem;color:var(--b);letter-spacing:.07em;margin-bottom:.75rem;text-transform:uppercase;">Tylko <?=htmlspecialchars($bUser)?></div>
          <?php foreach(array_slice($onlyB,0,8,true) as $artist=>$cnt): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:.78rem;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px;"><?=htmlspecialchars($artist)?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--b);"><?=$cnt?></span>
            </div>
          <?php endforeach; if(empty($onlyB)): ?><p style="font-size:.78rem;color:var(--text3);">Brak</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- NOWI ARTYŚCI -->
  <?php if (!empty($newArtists)): ?>
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head">
      <span class="card-title">Nowi artyści w tym okresie</span>
      <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)">pierwsze scroble</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="tbl">
        <thead><tr><th>#</th><th>Artysta</th><th>Pierwsze słuchanie</th><th>Scroble</th></tr></thead>
        <tbody>
        <?php foreach ($newArtists as $i=>$r): ?>
          <tr>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
            <td style="font-weight:500"><?=htmlspecialchars($r['artist'])?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=date('d.m.y H:i',strtotime($r['first_heard']))?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--accent);font-weight:500"><?=$r['cnt']?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- TOP LISTY — ARTYŚCI -->
  <div class="two-col" style="margin-bottom:1.25rem;">
    <?php foreach ([['A',$aUser,$topArtistsA,'var(--a)'],['B',$bUser,$topArtistsB,'var(--b)']] as [$side,$uname,$list,$col]): ?>
    <div class="card">
      <div class="card-head"><span class="card-title">Top artyści · <?=htmlspecialchars($uname)?></span></div>
      <div class="card-body" style="padding-top:.75rem;">
        <?php if(empty($list)): ?><p style="color:var(--text3);font-size:.78rem;">Brak danych</p>
        <?php else: $max=$list[0]['cnt']??1; foreach($list as $i=>$r): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:18px;text-align:right;"><?=$i+1?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
              <div style="background:var(--bg3);border-radius:2px;height:4px;margin-top:3px;overflow:hidden;">
                <div style="width:<?=round($r['cnt']/$max*100)?>%;height:100%;background:<?=$col?>;border-radius:2px;"></div>
              </div>
            </div>
            <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:<?=$col?>;flex-shrink:0;"><?=$r['cnt']?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TOP LISTY — ALBUMY -->
  <div class="two-col" style="margin-bottom:1.25rem;">
    <?php foreach ([[$aUser,$topAlbumsA,'var(--a)'],[$bUser,$topAlbumsB,'var(--b)']] as [$uname,$list,$col]): ?>
    <div class="card">
      <div class="card-head"><span class="card-title">Top albumy · <?=htmlspecialchars($uname)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Album</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($list as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['album'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:<?=$col?>;font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; if(empty($list)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TOP LISTY — UTWORY -->
  <div class="two-col" style="margin-bottom:2rem;">
    <?php foreach ([[$aUser,$topTracksA,'var(--a)'],[$bUser,$topTracksB,'var(--b)']] as [$uname,$list,$col]): ?>
    <div class="card">
      <div class="card-head"><span class="card-title">Top utwory · <?=htmlspecialchars($uname)?></span></div>
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>#</th><th>Utwór</th><th>Artysta</th><th>Scroble</th></tr></thead>
          <tbody>
          <?php foreach($list as $i=>$r): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
              <td style="font-weight:500"><?=htmlspecialchars($r['track'])?></td>
              <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:<?=$col?>;font-weight:500"><?=$r['cnt']?></td>
            </tr>
          <?php endforeach; if(empty($list)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1.5rem">Brak danych</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const chartOpts = (stacked=false) => ({
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { stacked, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 }, maxTicksLimit: 10 } },
    y: { stacked, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#aaa', font: { family: 'DM Mono', size: 10 } } }
  }
});

new Chart(document.getElementById('chart-daily').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?=$chartDays?>,
    datasets: [
      { label: '<?=addslashes($aUser)?>', data: <?=$chartA?>, backgroundColor: 'rgba(43,122,59,0.65)', borderRadius: 3 },
      { label: '<?=addslashes($bUser)?>', data: <?=$chartB?>, backgroundColor: 'rgba(26,95,168,0.65)', borderRadius: 3 },
    ]
  },
  options: chartOpts(true)
});

new Chart(document.getElementById('chart-hours').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?=json_encode(array_keys($hourMap))?>,
    datasets: [{ data: <?=json_encode(array_values($hourMap))?>, backgroundColor: 'rgba(200,80,60,0.6)', borderRadius: 2 }]
  },
  options: chartOpts()
});

new Chart(document.getElementById('chart-days').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?=json_encode($days_labels)?>,
    datasets: [{ data: <?=json_encode(array_values($dayMap))?>, backgroundColor: 'rgba(200,80,60,0.6)', borderRadius: 2 }]
  },
  options: chartOpts()
});
</script>
</body></html>
