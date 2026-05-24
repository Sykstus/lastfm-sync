<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user    = currentUser();
$db      = getDB();
$isAdmin = $user['role'] === 'admin';

$cfg   = $db->query('SELECT a_user, b_user FROM config ORDER BY id DESC LIMIT 1')->fetch();
$aUser = $cfg['a_user'] ?? 'A';
$bUser = $cfg['b_user'] ?? 'B';
$myLastfm    = $user['lastfm_user'];
$myDirection = null;
if ($myLastfm === $aUser) $myDirection = 'a2b';
elseif ($myLastfm === $bUser) $myDirection = 'b2a';

$filterDir    = $_GET['dir']    ?? ($isAdmin ? 'all' : ($myDirection ?? 'all'));
$filterArtist = trim($_GET['artist'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

$where = []; $params = [];
if (!$isAdmin && $myDirection) { $where[] = 'direction = ?'; $params[] = $myDirection; }
elseif ($filterDir !== 'all') { $where[] = 'direction = ?'; $params[] = $filterDir; }
if ($filterArtist !== '') { $where[] = 'artist LIKE ?'; $params[] = '%'.$filterArtist.'%'; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = $db->prepare("SELECT COUNT(*) FROM scrobbles $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();
$pages = ceil($total / $perPage);

$rows = $db->prepare("SELECT * FROM scrobbles $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$rows->execute($params); $rows = $rows->fetchAll();

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Historia scrobbli</h1>
    <span style="font-family:'DM Mono',monospace;font-size:.7rem;color:var(--text3)"><?= number_format($total) ?> rekordów</span>
  </div>

  <form method="GET" style="margin-bottom:1.25rem;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <?php if ($isAdmin): ?>
      <div class="field" style="margin:0">
        <label>Kierunek</label>
        <select name="dir" style="width:auto;">
          <option value="all" <?=$filterDir==='all'?'selected':''?>>Wszystkie</option>
          <option value="a2b" <?=$filterDir==='a2b'?'selected':''?>><?=htmlspecialchars($aUser)?> → <?=htmlspecialchars($bUser)?></option>
          <option value="b2a" <?=$filterDir==='b2a'?'selected':''?>><?=htmlspecialchars($bUser)?> → <?=htmlspecialchars($aUser)?></option>
        </select>
      </div>
      <?php endif; ?>
      <div class="field" style="margin:0">
        <label>Artysta</label>
        <input type="text" name="artist" placeholder="Szukaj..." value="<?=htmlspecialchars($filterArtist)?>" style="width:200px;">
      </div>
      <button type="submit" class="btn primary">Filtruj</button>
      <a href="scrobbles.php" class="btn">Reset</a>
    </div>
  </form>

  <div class="card">
    <div style="overflow-x:auto;">
      <table class="tbl">
        <thead><tr><th>Kierunek</th><th>Artysta</th><th>Utwór</th><th>Album</th><th>Data scrobla</th><th>Zsynchronizowano</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
          <tr>
            <td><span class="badge <?=$s['direction']==='a2b'?'badge-a':'badge-b'?>"><?=$s['direction']==='a2b'?htmlspecialchars($aUser).'→'.htmlspecialchars($bUser):htmlspecialchars($bUser).'→'.htmlspecialchars($aUser)?></span></td>
            <td style="font-weight:400"><?=htmlspecialchars($s['artist'])?></td>
            <td><?=htmlspecialchars($s['track'])?></td>
            <td style="color:var(--text3)"><?=htmlspecialchars($s['album']??'—')?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text2)"><?=date('d.m.y H:i',strtotime($s['scrobbled_at']))?></td>
            <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=date('H:i',strtotime($s['synced_at']))?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:2.5rem;">Brak wyników</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pages > 1): ?>
  <div style="display:flex;gap:6px;margin-top:1.25rem;flex-wrap:wrap;">
    <?php $qs=http_build_query(['dir'=>$filterDir,'artist'=>$filterArtist]);
    for ($p=1;$p<=min($pages,20);$p++): ?>
      <?php if ($p===$page): ?>
        <span style="font-family:'DM Mono',monospace;font-size:.65rem;padding:5px 10px;border-radius:6px;border:1.5px solid var(--accent);color:var(--accent);"><?=$p?></span>
      <?php else: ?>
        <a href="?<?=$qs?>&page=<?=$p?>" style="font-family:'DM Mono',monospace;font-size:.65rem;padding:5px 10px;border-radius:6px;border:1px solid var(--border);color:var(--text3);text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='var(--border2)';this.style.color='var(--text)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text3)'"><?=$p?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</main>
</body></html>
