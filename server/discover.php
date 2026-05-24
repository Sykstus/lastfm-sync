<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$db  = getDB();
$cfg = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
$aUser = $cfg['a_user'] ?? 'Konto A';
$bUser = $cfg['b_user'] ?? 'Konto B';

function q($db,$sql,$p=[]){$s=$db->prepare($sql);$s->execute($p);return $s->fetchAll();}
function q1($db,$sql,$p=[]){$s=$db->prepare($sql);$s->execute($p);return $s->fetchColumn();}

// ─── OKRESY ──────────────────────────────────────────────────────────────────
$thisWeekStart  = date('Y-m-d', strtotime('monday this week'));
$lastWeekStart  = date('Y-m-d', strtotime('monday last week'));
$lastWeekEnd    = date('Y-m-d', strtotime('sunday last week'));
$thisMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t', strtotime('first day of last month'));
$yearStart      = date('Y-01-01');
$lastYearStart  = (date('Y')-1).'-01-01';
$lastYearEnd    = (date('Y')-1).'-12-31';
$yearAgoWeekStart = date('Y-m-d', strtotime('monday this week - 1 year'));
$yearAgoWeekEnd   = date('Y-m-d', strtotime('sunday this week - 1 year'));

// ─── TEN TYDZIEŃ ─────────────────────────────────────────────────────────────
$tw_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at >= ?",[$thisWeekStart]);
$tw_a2b    = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ?",[$thisWeekStart]);
$tw_b2a    = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ?",[$thisWeekStart]);
$tw_artists= q1($db,"SELECT COUNT(DISTINCT artist) FROM scrobbles WHERE scrobbled_at >= ?",[$thisWeekStart]);
$tw_top_artist = q1($db,"SELECT artist FROM scrobbles WHERE scrobbled_at >= ? GROUP BY artist ORDER BY COUNT(*) DESC LIMIT 1",[$thisWeekStart]);
$tw_top_track  = q($db,"SELECT track, artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY track,artist ORDER BY cnt DESC LIMIT 1",[$thisWeekStart]);
$tw_top_album  = q($db,"SELECT album, artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? AND album IS NOT NULL AND album!='' GROUP BY album,artist ORDER BY cnt DESC LIMIT 1",[$thisWeekStart]);
$tw_peak_hour  = q1($db,"SELECT HOUR(scrobbled_at) FROM scrobbles WHERE scrobbled_at >= ? GROUP BY HOUR(scrobbled_at) ORDER BY COUNT(*) DESC LIMIT 1",[$thisWeekStart]);
$tw_days_active= q1($db,"SELECT COUNT(DISTINCT DATE(scrobbled_at)) FROM scrobbles WHERE scrobbled_at >= ?",[$thisWeekStart]);

// ─── POPRZEDNI TYDZIEŃ ───────────────────────────────────────────────────────
$lw_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ?",[$lastWeekStart,$lastWeekEnd.' 23:59:59']);
$lw_top_artist = q1($db,"SELECT artist FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ? GROUP BY artist ORDER BY COUNT(*) DESC LIMIT 1",[$lastWeekStart,$lastWeekEnd.' 23:59:59']);
$tw_vs_lw  = $lw_total > 0 ? round(($tw_total - $lw_total) / $lw_total * 100) : 0;

// ─── ROK TEMU (ten sam tydzień) ───────────────────────────────────────────────
$yaw_total = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ?",[$yearAgoWeekStart,$yearAgoWeekEnd.' 23:59:59']);
$yaw_top   = q1($db,"SELECT artist FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ? GROUP BY artist ORDER BY COUNT(*) DESC LIMIT 1",[$yearAgoWeekStart,$yearAgoWeekEnd.' 23:59:59']);
$yaw_top5  = q($db,"SELECT artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ? GROUP BY artist ORDER BY cnt DESC LIMIT 5",[$yearAgoWeekStart,$yearAgoWeekEnd.' 23:59:59']);

// ─── TEN MIESIĄC ─────────────────────────────────────────────────────────────
$tm_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at >= ?",[$thisMonthStart]);
$tm_top5_artists_a = q($db,"SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='a2b' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT 5",[$thisMonthStart]);
$tm_top5_artists_b = q($db,"SELECT artist, COUNT(*) cnt FROM scrobbles WHERE direction='b2a' AND scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT 5",[$thisMonthStart]);
$tm_top5_tracks = q($db,"SELECT track, artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY track,artist ORDER BY cnt DESC LIMIT 5",[$thisMonthStart]);

// ─── POPRZEDNI MIESIĄC ───────────────────────────────────────────────────────
$lm_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ?",[$lastMonthStart,$lastMonthEnd.' 23:59:59']);
$lm_top_artist = q1($db,"SELECT artist FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ? GROUP BY artist ORDER BY COUNT(*) DESC LIMIT 1",[$lastMonthStart,$lastMonthEnd.' 23:59:59']);
$tm_vs_lm  = $lm_total > 0 ? round(($tm_total - $lm_total) / $lm_total * 100) : 0;

// ─── TEN ROK ─────────────────────────────────────────────────────────────────
$ty_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at >= ?",[$yearStart]);
$ty_top10_artists = q($db,"SELECT artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY artist ORDER BY cnt DESC LIMIT 10",[$yearStart]);
$ty_top10_albums  = q($db,"SELECT album, artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? AND album IS NOT NULL AND album!='' GROUP BY album,artist ORDER BY cnt DESC LIMIT 10",[$yearStart]);
$ty_top10_tracks  = q($db,"SELECT track, artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= ? GROUP BY track,artist ORDER BY cnt DESC LIMIT 10",[$yearStart]);
$ty_best_month    = q1($db,"SELECT DATE_FORMAT(scrobbled_at,'%Y-%m') mo FROM scrobbles WHERE scrobbled_at >= ? GROUP BY mo ORDER BY COUNT(*) DESC LIMIT 1",[$yearStart]);
$ty_best_month_cnt= q1($db,"SELECT COUNT(*) FROM scrobbles WHERE DATE_FORMAT(scrobbled_at,'%Y-%m')=? AND scrobbled_at >= ?",[$ty_best_month,$yearStart]);
$ty_days_active   = q1($db,"SELECT COUNT(DISTINCT DATE(scrobbled_at)) FROM scrobbles WHERE scrobbled_at >= ?",[$yearStart]);

// ─── POPRZEDNI ROK ───────────────────────────────────────────────────────────
$ly_total  = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ?",[$lastYearStart,$lastYearEnd.' 23:59:59']);
$ly_top5   = q($db,"SELECT artist, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at BETWEEN ? AND ? GROUP BY artist ORDER BY cnt DESC LIMIT 5",[$lastYearStart,$lastYearEnd.' 23:59:59']);
$ty_vs_ly  = $ly_total > 0 ? round(($ty_total - $ly_total) / $ly_total * 100) : 0;

// ─── STREAK ──────────────────────────────────────────────────────────────────
$allDays = q($db,"SELECT DISTINCT DATE(scrobbled_at) day FROM scrobbles ORDER BY day DESC");
$streak = 0; $check = date('Y-m-d');
foreach ($allDays as $r) {
    if ($r['day'] === $check || $r['day'] === date('Y-m-d', strtotime($check.' -1 day'))) {
        $streak++; $check = $r['day'];
    } else break;
}

// ─── NOWI ARTYŚCI W TYM MIESIĄCU ────────────────────────────────────────────
$newArtists = q($db,"
    SELECT artist, MIN(scrobbled_at) first_heard, COUNT(*) cnt
    FROM scrobbles WHERE scrobbled_at >= ?
    AND artist NOT IN (SELECT DISTINCT artist FROM scrobbles WHERE scrobbled_at < ?)
    GROUP BY artist ORDER BY first_heard ASC LIMIT 6
",[$thisMonthStart,$thisMonthStart]);

// ─── DZIENNY WYKRES (ostatnie 30 dni) ────────────────────────────────────────
$daily30 = q($db,"SELECT DATE(scrobbled_at) day, COUNT(*) cnt FROM scrobbles WHERE scrobbled_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(scrobbled_at) ORDER BY day");
$dailyMap = [];
foreach ($daily30 as $r) $dailyMap[$r['day']] = (int)$r['cnt'];
// Wypełnij brakujące dni zerami
$chartLabels = []; $chartData = [];
for ($i=29; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d.m', strtotime($d));
    $chartData[]   = $dailyMap[$d] ?? 0;
}

$months_pl = ['01'=>'Styczeń','02'=>'Luty','03'=>'Marzec','04'=>'Kwiecień','05'=>'Maj','06'=>'Czerwiec','07'=>'Lipiec','08'=>'Sierpień','09'=>'Wrzesień','10'=>'Październik','11'=>'Listopad','12'=>'Grudzień'];
$best_month_name = $ty_best_month ? ($months_pl[substr($ty_best_month,5,2)] ?? $ty_best_month) : '—';

// ─── PIERWSZA WSPÓLNA SESJA ──────────────────────────────────────────────────
$firstSession = q($db,"
    SELECT DATE(scrobbled_at) day, COUNT(*) cnt, MIN(scrobbled_at) first_at
    FROM scrobbles
    GROUP BY DATE(scrobbled_at)
    ORDER BY day ASC LIMIT 1
");
$firstDay     = $firstSession[0] ?? null;
$firstTracks  = $firstDay ? q($db,"
    SELECT track, artist, album, scrobbled_at, direction
    FROM scrobbles
    WHERE DATE(scrobbled_at) = ?
    ORDER BY scrobbled_at ASC LIMIT 8
",[$firstDay['day']]) : [];

// ─── ZAPOMNIANI ARTYŚCI ───────────────────────────────────────────────────────
// Artyści których słuchaliście intensywnie (5+ scrobbli) ale ostatni scrobble >90 dni temu
$forgotten = q($db,"
    SELECT
        artist,
        COUNT(*) total,
        MAX(scrobbled_at) last_heard,
        MIN(scrobbled_at) first_heard,
        DATEDIFF(NOW(), MAX(scrobbled_at)) days_ago
    FROM scrobbles
    GROUP BY artist
    HAVING total >= 5
       AND days_ago > 90
       AND last_heard < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ORDER BY total DESC, days_ago DESC
    LIMIT 8
");

// ─── ARTYŚCI WG DNIA TYGODNIA ─────────────────────────────────────────────────
// Dla każdego dnia — artysta który dominuje (min 3 scroble, >50% jego scrobbli w tym dniu)
$dayArtists = [];
$days_full  = ['Poniedziałek','Wtorek','Środa','Czwartek','Piątek','Sobota','Niedziela'];
for ($d = 0; $d < 7; $d++) {
    // Top artyści tego dnia tygodnia
    $topForDay = q($db,"
        SELECT artist, COUNT(*) cnt,
               ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM scrobbles WHERE WEEKDAY(scrobbled_at)=?), 0) pct
        FROM scrobbles
        WHERE WEEKDAY(scrobbled_at) = ?
        GROUP BY artist
        HAVING cnt >= 3
        ORDER BY cnt DESC
        LIMIT 3
    ",[$d,$d]);

    // Sprawdź czy artysta jest charakterystyczny dla tego dnia
    // (tzn. >40% jego wszystkich scrobbli przypada na ten dzień)
    $unique = [];
    foreach ($topForDay as $r) {
        $totalArtist = q1($db,"SELECT COUNT(*) FROM scrobbles WHERE artist=?",[$r['artist']]);
        $ratio = $totalArtist > 0 ? round($r['cnt'] / $totalArtist * 100) : 0;
        if ($ratio >= 30) { // przynajmniej 30% scrobbli artysty w ten dzień
            $r['day_ratio'] = $ratio;
            $unique[] = $r;
        }
    }
    $dayArtists[$d] = ['label' => $days_full[$d], 'top' => $topForDay, 'unique' => $unique];
}

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Odkryj</h1>
    <span style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--text3)">Twoja muzyczna historia</span>
  </div>

  <!-- ── TEN TYDZIEŃ ──────────────────────────────────────────────────────── -->
  <div style="margin-bottom:2rem;">
    <div class="section-label">Ten tydzień <span style="color:var(--text3);font-weight:400;">· od <?=date('d.m', strtotime($thisWeekStart))?></span></div>

    <!-- Wielki licznik -->
    <div class="card" style="margin-bottom:1rem;background:linear-gradient(135deg,var(--bg2) 0%,var(--bg) 100%);">
      <div class="card-body" style="padding:2rem;text-align:center;">
        <div style="font-size:4.5rem;font-weight:700;color:var(--accent);line-height:1;font-variant-numeric:tabular-nums;"><?=number_format((int)$tw_total)?></div>
        <div style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--text3);text-transform:uppercase;letter-spacing:.1em;margin-top:.5rem;">scrobbli w tym tygodniu</div>
        <?php if($lw_total > 0): $sign=$tw_vs_lw>=0?'+':''; $col=$tw_vs_lw>=0?'var(--a)':'var(--red)'; ?>
        <div style="font-family:'DM Mono',monospace;font-size:.85rem;color:<?=$col?>;margin-top:.5rem;font-weight:500;"><?=$sign.$tw_vs_lw?>% vs poprzedni tydzień</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem;">
      <div class="stat-card"><div class="stat-n ca"><?=$tw_a2b?></div><div class="stat-l"><?=htmlspecialchars($aUser)?> → <?=htmlspecialchars($bUser)?></div></div>
      <div class="stat-card"><div class="stat-n cb"><?=$tw_b2a?></div><div class="stat-l"><?=htmlspecialchars($bUser)?> → <?=htmlspecialchars($aUser)?></div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--text2)"><?=$tw_artists?></div><div class="stat-l">Artystów</div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--accent)"><?=$tw_days_active?>/7</div><div class="stat-l">Dni aktywnych</div></div>
    </div>

    <div class="two-col" style="margin-bottom:1rem;">
      <!-- Highlights tego tygodnia -->
      <div class="card">
        <div class="card-head"><span class="card-title">Highlights tygodnia</span></div>
        <div class="card-body">
          <?php if($tw_top_artist): ?>
          <div style="margin-bottom:1rem;">
            <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;">🎤 Artysta tygodnia</div>
            <div style="font-size:1.3rem;font-weight:600;color:var(--text1);"><?=htmlspecialchars($tw_top_artist)?></div>
          </div>
          <?php endif; ?>
          <?php if(!empty($tw_top_track)): $t=$tw_top_track[0]; ?>
          <div style="margin-bottom:1rem;">
            <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;">🎵 Utwór tygodnia</div>
            <div style="font-size:1.1rem;font-weight:600;color:var(--text1);"><?=htmlspecialchars($t['track'])?></div>
            <div style="font-size:.85rem;color:var(--text2);"><?=htmlspecialchars($t['artist'])?> · <?=$t['cnt']?>×</div>
          </div>
          <?php endif; ?>
          <?php if(!empty($tw_top_album)): $a=$tw_top_album[0]; ?>
          <div style="margin-bottom:1rem;">
            <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;">💿 Album tygodnia</div>
            <div style="font-size:1.1rem;font-weight:600;color:var(--text1);"><?=htmlspecialchars($a['album'])?></div>
            <div style="font-size:.85rem;color:var(--text2);"><?=htmlspecialchars($a['artist'])?> · <?=$a['cnt']?>×</div>
          </div>
          <?php endif; ?>
          <?php if($tw_peak_hour !== false): ?>
          <div>
            <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;">⏰ Ulubiona pora</div>
            <div style="font-size:1.1rem;font-weight:600;color:var(--text1);"><?=$tw_peak_hour?>:00 – <?=$tw_peak_hour+1?>:00</div>
          </div>
          <?php endif; ?>
          <?php if(!$tw_top_artist): ?><p style="color:var(--text3);font-size:.85rem;">Brak danych z tego tygodnia</p><?php endif; ?>
        </div>
      </div>

      <!-- Rok temu ten sam tydzień -->
      <div class="card" style="border:1px dashed var(--border2);">
        <div class="card-head">
          <span class="card-title">📅 Rok temu</span>
          <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3)"><?=date('d.m.Y',strtotime($yearAgoWeekStart))?> – <?=date('d.m.Y',strtotime($yearAgoWeekEnd))?></span>
        </div>
        <div class="card-body">
          <?php if($yaw_total > 0): ?>
          <div style="font-size:2.5rem;font-weight:700;color:var(--text2);line-height:1;margin-bottom:.5rem;"><?=number_format($yaw_total)?></div>
          <div style="font-family:'DM Mono',monospace;font-size:.68rem;color:var(--text3);margin-bottom:1.25rem;">scrobbli</div>
          <?php if($yaw_top): ?>
          <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem;">Top artyści</div>
          <?php foreach($yaw_top5 as $i=>$r): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border);font-size:.82rem;">
              <span><?=$i+1?>. <?=htmlspecialchars($r['artist'])?></span>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3);"><?=$r['cnt']?></span>
            </div>
          <?php endforeach; ?>
          <?php endif; ?>
          <?php else: ?>
          <p style="color:var(--text3);font-size:.85rem;">Brak danych z tego okresu rok temu</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── WYKRES 30 DNI ─────────────────────────────────────────────────────── -->
  <div class="card" style="margin-bottom:2rem;">
    <div class="card-head"><span class="card-title">Aktywność — ostatnie 30 dni</span></div>
    <div class="card-body"><div style="position:relative;height:160px;"><canvas id="chart-30"></canvas></div></div>
  </div>

  <!-- ── TEN MIESIĄC ──────────────────────────────────────────────────────── -->
  <div style="margin-bottom:2rem;">
    <div class="section-label">Ten miesiąc <span style="color:var(--text3);font-weight:400;">· <?=$months_pl[date('m')]?> <?=date('Y')?></span></div>

    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem;">
      <div class="stat-card">
        <div class="stat-n" style="color:var(--accent)"><?=number_format((int)$tm_total)?></div>
        <div class="stat-l">Scrobbli łącznie</div>
        <?php if($lm_total>0): $sign=$tm_vs_lm>=0?'+':''; $col=$tm_vs_lm>=0?'var(--a)':'var(--red)'; ?>
        <div style="font-family:'DM Mono',monospace;font-size:.65rem;color:<?=$col?>;margin-top:4px;"><?=$sign.$tm_vs_lm?>% vs poprzedni miesiąc</div>
        <?php endif; ?>
      </div>
      <div class="stat-card"><div class="stat-n" style="color:var(--text2)"><?=count($newArtists)?></div><div class="stat-l">Nowych artystów</div></div>
      <div class="stat-card"><div class="stat-n" style="color:var(--accent)"><?=$streak?> 🔥</div><div class="stat-l">Dni z rzędu</div></div>
    </div>

    <div class="two-col" style="margin-bottom:1rem;">
      <!-- Top artyści miesiąca -->
      <div class="card">
        <div class="card-head"><span class="card-title">Top artyści miesiąca</span></div>
        <div class="card-body" style="padding-top:.75rem;">
          <div style="margin-bottom:.75rem;font-family:'DM Mono',monospace;font-size:.62rem;color:var(--a);text-transform:uppercase;"><?=htmlspecialchars($aUser)?></div>
          <?php if(empty($tm_top5_artists_a)): ?><p style="color:var(--text3);font-size:.78rem;">Brak</p>
          <?php else: $max=$tm_top5_artists_a[0]['cnt']; foreach($tm_top5_artists_a as $i=>$r): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">
              <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:14px;"><?=$i+1?></span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
                <div style="background:var(--bg3);height:3px;border-radius:2px;margin-top:3px;overflow:hidden;"><div style="width:<?=round($r['cnt']/$max*100)?>%;height:100%;background:var(--a);"></div></div>
              </div>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--a);"><?=$r['cnt']?></span>
            </div>
          <?php endforeach; endif; ?>
          <div style="margin:1rem 0 .75rem;font-family:'DM Mono',monospace;font-size:.62rem;color:var(--b);text-transform:uppercase;"><?=htmlspecialchars($bUser)?></div>
          <?php if(empty($tm_top5_artists_b)): ?><p style="color:var(--text3);font-size:.78rem;">Brak</p>
          <?php else: $max=$tm_top5_artists_b[0]['cnt']; foreach($tm_top5_artists_b as $i=>$r): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">
              <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:14px;"><?=$i+1?></span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
                <div style="background:var(--bg3);height:3px;border-radius:2px;margin-top:3px;overflow:hidden;"><div style="width:<?=round($r['cnt']/$max*100)?>%;height:100%;background:var(--b);"></div></div>
              </div>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--b);"><?=$r['cnt']?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Top utwory miesiąca + nowi artyści -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
          <div class="card-head"><span class="card-title">Top utwory miesiąca</span></div>
          <div style="overflow-x:auto;">
            <table class="tbl">
              <thead><tr><th>#</th><th>Utwór</th><th>Artysta</th><th></th></tr></thead>
              <tbody>
              <?php foreach($tm_top5_tracks as $i=>$r): ?>
                <tr>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
                  <td style="font-weight:500"><?=htmlspecialchars($r['track'])?></td>
                  <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent)"><?=$r['cnt']?>×</td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($tm_top5_tracks)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1rem">Brak</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if(!empty($newArtists)): ?>
        <div class="card">
          <div class="card-head"><span class="card-title">✨ Nowi artyści w tym miesiącu</span></div>
          <div class="card-body" style="padding-top:.5rem;">
            <?php foreach($newArtists as $r): ?>
              <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:.82rem;">
                <div>
                  <div style="font-weight:500;"><?=htmlspecialchars($r['artist'])?></div>
                  <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);">pierwsze: <?=date('d.m H:i',strtotime($r['first_heard']))?></div>
                </div>
                <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent);align-self:center;"><?=$r['cnt']?>×</span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── TEN ROK (Wrapped) ─────────────────────────────────────────────────── -->
  <div style="margin-bottom:2rem;">
    <div class="section-label">Wrapped <?=date('Y')?> <span style="color:var(--text3);font-weight:400;">· od 1 stycznia</span></div>

    <!-- Hero stats -->
    <div class="card" style="margin-bottom:1rem;overflow:hidden;">
      <div style="padding:2rem;background:var(--bg2);">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;text-align:center;">
          <?php
          $hero = [
            [number_format((int)$ty_total),  'Scrobbli w '.date('Y'), 'var(--accent)'],
            [$ty_days_active,                 'Dni z muzyką', 'var(--a)'],
            [$best_month_name,                'Najlepszy miesiąc', 'var(--b)'],
            [$streak.' 🔥',                   'Obecny streak', 'var(--accent)'],
          ];
          foreach($hero as $h): ?>
          <div>
            <div style="font-size:2rem;font-weight:700;color:<?=$h[2]?>;line-height:1.1;"><?=$h[0]?></div>
            <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;margin-top:.35rem;"><?=$h[1]?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Top 10 artyści roku -->
    <div class="two-col" style="margin-bottom:1rem;">
      <div class="card">
        <div class="card-head"><span class="card-title">Top 10 artystów <?=date('Y')?></span></div>
        <div class="card-body" style="padding-top:.75rem;">
          <?php if(empty($ty_top10_artists)): ?><p style="color:var(--text3);font-size:.78rem;">Brak danych</p>
          <?php else: $max=$ty_top10_artists[0]['cnt']; foreach($ty_top10_artists as $i=>$r): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
              <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:18px;text-align:right;"><?=$i+1?></span>
              <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($r['artist'])?></div>
                <div style="background:var(--bg3);border-radius:2px;height:4px;margin-top:3px;overflow:hidden;"><div style="width:<?=round($r['cnt']/$max*100)?>%;height:100%;background:var(--accent);border-radius:2px;"></div></div>
              </div>
              <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent);"><?=$r['cnt']?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;">
        <!-- Top albumy roku -->
        <div class="card">
          <div class="card-head"><span class="card-title">Top albumy <?=date('Y')?></span></div>
          <div style="overflow-x:auto;">
            <table class="tbl">
              <thead><tr><th>#</th><th>Album</th><th>Artysta</th><th>Scroble</th></tr></thead>
              <tbody>
              <?php foreach($ty_top10_albums as $i=>$r): ?>
                <tr>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
                  <td style="font-weight:500"><?=htmlspecialchars($r['album'])?></td>
                  <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent)"><?=$r['cnt']?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($ty_top10_albums)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1rem">Brak</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top utwory roku -->
        <div class="card">
          <div class="card-head"><span class="card-title">Top utwory <?=date('Y')?></span></div>
          <div style="overflow-x:auto;">
            <table class="tbl">
              <thead><tr><th>#</th><th>Utwór</th><th>Artysta</th><th>Scroble</th></tr></thead>
              <tbody>
              <?php foreach($ty_top10_tracks as $i=>$r): ?>
                <tr>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$i+1?></td>
                  <td style="font-weight:500"><?=htmlspecialchars($r['track'])?></td>
                  <td style="color:var(--text2)"><?=htmlspecialchars($r['artist'])?></td>
                  <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--accent)"><?=$r['cnt']?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($ty_top10_tracks)): ?><tr><td colspan="4" style="color:var(--text3);text-align:center;padding:1rem">Brak</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Poprzedni rok porównanie -->
    <?php if($ly_total > 0): ?>
    <div class="card" style="margin-bottom:1rem;">
      <div class="card-head">
        <span class="card-title">Rok <?=date('Y')-1?> — dla porównania</span>
        <?php if($ty_vs_ly != 0): $sign=$ty_vs_ly>0?'+':''; $col=$ty_vs_ly>0?'var(--a)':'var(--red)'; ?>
        <span style="font-family:'DM Mono',monospace;font-size:.72rem;color:<?=$col?>;font-weight:500;"><?=$sign.$ty_vs_ly?>% vs <?=date('Y')-1?></span>
        <?php endif; ?>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
        <div style="padding:1.25rem;border-right:1px solid var(--border);">
          <div style="font-size:2rem;font-weight:300;color:var(--text2)"><?=number_format($ly_total)?></div>
          <div style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);text-transform:uppercase;margin-top:3px;">scrobbli w <?=date('Y')-1?></div>
        </div>
        <div style="padding:1.25rem;">
          <div style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);text-transform:uppercase;margin-bottom:.5rem;">Top artyści <?=date('Y')-1?></div>
          <?php foreach($ly_top5 as $i=>$r): ?>
            <div style="font-size:.8rem;padding:3px 0;border-bottom:1px solid var(--border);">
              <?=$i+1?>. <?=htmlspecialchars($r['artist'])?> <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3);float:right"><?=$r['cnt']?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── PIERWSZA WSPÓLNA SESJA ──────────────────────────────────────────── -->
  <?php if($firstDay): ?>
  <div style="margin-bottom:2rem;">
    <div class="section-label">🎬 Pierwsza wspólna sesja</div>
    <div class="card">
      <div class="card-head">
        <div>
          <span class="card-title"><?=date('d.m.Y', strtotime($firstDay['day']))?></span>
          <span style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3);margin-left:10px;"><?=$firstDay['cnt']?> scrobbli tego dnia</span>
        </div>
        <span style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--accent);">
          <?=floor((time()-strtotime($firstDay['day']))/86400)?> dni temu
        </span>
      </div>
      <div class="card-body">
        <div style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.75rem;">Co leciało</div>
        <?php foreach($firstTracks as $i=>$t):
          $dir = $t['direction']==='a2b' ? $aUser.'→'.$bUser : $bUser.'→'.$aUser;
          $bc  = $t['direction']==='a2b' ? 'badge-a' : 'badge-b';
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:7px 0;border-bottom:1px solid var(--border);">
          <span style="font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);min-width:42px;"><?=date('H:i',strtotime($t['scrobbled_at']))?></span>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:500;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($t['track'])?></div>
            <div style="font-size:.75rem;color:var(--text2);"><?=htmlspecialchars($t['artist'])?><?= $t['album'] ? ' · '.htmlspecialchars($t['album']) : ''?></div>
          </div>
          <span class="badge <?=$bc?>" style="font-size:.6rem;flex-shrink:0;"><?=$dir?></span>
        </div>
        <?php endforeach; ?>
        <?php if($firstDay['cnt'] > 8): ?>
        <div style="font-family:'DM Mono',monospace;font-size:.62rem;color:var(--text3);padding-top:.75rem;">...i jeszcze <?=$firstDay['cnt']-8?> więcej</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── ZAPOMNIANI ARTYŚCI ────────────────────────────────────────────────── -->
  <?php if(!empty($forgotten)): ?>
  <div style="margin-bottom:2rem;">
    <div class="section-label">💤 Zapomniani artyści</div>
    <p style="font-size:.85rem;color:var(--text2);margin:0 0 .75rem;">Słuchaliście ich intensywnie, ale ostatni scrobble był ponad 90 dni temu.</p>
    <div class="card">
      <div style="overflow-x:auto;">
        <table class="tbl">
          <thead><tr><th>Artysta</th><th>Scrobbli</th><th>Słuchany od</th><th>Ostatni raz</th><th>Cisza</th></tr></thead>
          <tbody>
          <?php foreach($forgotten as $r): ?>
            <tr>
              <td style="font-weight:500"><?=htmlspecialchars($r['artist'])?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--accent)"><?=$r['total']?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=date('d.m.Y',strtotime($r['first_heard']))?></td>
              <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text2)"><?=date('d.m.Y',strtotime($r['last_heard']))?></td>
              <td>
                <span style="font-family:'DM Mono',monospace;font-size:.65rem;padding:2px 7px;border-radius:4px;background:var(--bg3);color:var(--text2);">
                  <?= $r['days_ago'] >= 365
                    ? floor($r['days_ago']/365).' '.($r['days_ago']>=730?'lata':'rok')
                    : $r['days_ago'].' dni' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── ARTYŚCI WG DNIA TYGODNIA ─────────────────────────────────────────── -->
  <div style="margin-bottom:2rem;">
    <div class="section-label">📅 Muzyka a dzień tygodnia</div>
    <p style="font-size:.85rem;color:var(--text2);margin:0 0 .75rem;">Artyści których słuchacie najczęściej w dany dzień — i ci charakterystyczni tylko dla tego dnia (≥30% scrobbli).</p>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;">
      <?php foreach($dayArtists as $d=>$day): ?>
      <div class="card" style="<?=$d>=5?'border-color:var(--accent);':''?>">
        <div style="padding:10px 10px 0;font-family:'DM Mono',monospace;font-size:.62rem;font-weight:600;color:<?=$d>=5?'var(--accent)':'var(--text2)'?>;text-transform:uppercase;letter-spacing:.05em;">
          <?=substr($day['label'],0,3)?>
          <?php if($d>=5): ?><span style="font-size:.5rem;color:var(--accent);">WKD</span><?php endif; ?>
        </div>
        <div style="padding:8px 10px 10px;">
          <?php if(empty($day['top'])): ?>
            <div style="font-size:.7rem;color:var(--text3);">brak danych</div>
          <?php else: foreach($day['top'] as $i=>$r): ?>
            <div style="font-size:.72rem;margin-bottom:4px;<?=$i===0?'font-weight:600;color:var(--text1);':'color:var(--text2);'?>">
              <?=htmlspecialchars(mb_strlen($r['artist'])>14?mb_substr($r['artist'],0,12).'…':$r['artist'])?>
              <?php if(!empty($day['unique']) && $i===0): ?>
                <span title="<?=$day['unique'][0]['day_ratio']?>% jego scrobbli to <?=substr($day['label'],0,3)?>" style="font-family:'DM Mono',monospace;font-size:.55rem;color:var(--accent);vertical-align:middle;">★</span>
              <?php endif; ?>
            </div>
            <div style="background:var(--bg3);height:3px;border-radius:1px;margin-bottom:5px;overflow:hidden;">
              <div style="width:<?=$r['pct']?>%;height:100%;background:<?=$i===0?'var(--accent)':'var(--border2)'?>;"></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:.72rem;color:var(--text3);margin-top:.5rem;font-family:'DM Mono',monospace;">★ = artysta charakterystyczny dla tego dnia (≥30% jego scrobbli)</p>
  </div>

  <div style="height:2rem;"></div>
</main>

<style>
.section-label {
  font-size:1.1rem;
  font-weight:600;
  color:var(--text1);
  margin-bottom:.75rem;
  padding-bottom:.5rem;
  border-bottom:2px solid var(--accent);
  display:inline-block;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chart-30').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?=json_encode($chartLabels)?>,
    datasets: [{
      data: <?=json_encode($chartData)?>,
      backgroundColor: 'rgba(200,80,60,0.55)',
      borderRadius: 3,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color:'rgba(0,0,0,0.04)' }, ticks: { color:'#aaa', font:{ family:'DM Mono', size:10 }, maxTicksLimit:15 } },
      y: { grid: { color:'rgba(0,0,0,0.04)' }, ticks: { color:'#aaa', font:{ family:'DM Mono', size:10 } }, beginAtZero:true }
    }
  }
});
</script>
</body></html>
