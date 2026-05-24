<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$user = currentUser();
$db   = getDB();

$msg = ''; $msgType = '';

if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $uid = (int)$_POST['user_id']; $newPass = $_POST['new_password'] ?? '';
    if (strlen($newPass) < 6) { $msg = 'Hasło musi mieć min. 6 znaków'; $msgType='err'; }
    else { $db->prepare('UPDATE panel_users SET password=? WHERE id=?')->execute([password_hash($newPass,PASSWORD_BCRYPT),$uid]); $msg='Hasło zmienione';$msgType='ok'; }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_lfm') {
    $fields=['api_key','api_secret','a_user','a_sk','b_user','b_sk','security_password'];
    $vals=[]; foreach($fields as $f) $vals[$f]=trim($_POST[$f]??'');

    // Weryfikacja hasła bezpieczeństwa — musi zgadzać się z hasłem zalogowanego admina
    if (empty($vals['security_password'])) {
        $msg='Podaj hasło bezpieczeństwa aby zapisać konfigurację';$msgType='err';
    } else {
        $dbUser = $db->prepare('SELECT password FROM panel_users WHERE id = ?');
        $dbUser->execute([$user['id']]);
        $dbUser = $dbUser->fetch();
        if (!$dbUser || !password_verify($vals['security_password'], $dbUser['password'])) {
            $msg='Nieprawidłowe hasło bezpieczeństwa — konfiguracja nie została zapisana';$msgType='err';
        } else {
            unset($vals['security_password']);
            if (in_array('',$vals)){$msg='Uzupełnij wszystkie pola konfiguracji';$msgType='err';}
            else {
                $db->prepare('INSERT INTO config (api_key,api_secret,a_user,a_sk,b_user,b_sk) VALUES (?,?,?,?,?,?)')->execute(array_values($vals));
                file_put_contents(__DIR__.'/data/state.json',json_encode(['ts_a'=>time()-60,'ts_b'=>time()-60]));
                $msg='Konfiguracja zapisana pomyślnie ✓';$msgType='ok';
            }
        }
    }
}

$users      = $db->query('SELECT id,username,lastfm_user,role,last_login FROM panel_users ORDER BY id')->fetchAll();
$cfg        = $db->query('SELECT * FROM config ORDER BY id DESC LIMIT 1')->fetch();
$cfgHistory = $db->query('SELECT id,a_user,b_user,saved_at FROM config ORDER BY id DESC LIMIT 10')->fetchAll();

include __DIR__ . '/_nav.php';
?>
<main>
  <div class="page-header">
    <h1 class="page-title">Ustawienia</h1>
  </div>

  <?php if ($msg): ?><div class="banner <?=$msgType?>"><?=htmlspecialchars($msg)?></div><?php endif; ?>

  <!-- KONFIGURACJA LAST.FM -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head"><span class="card-title">Konfiguracja Last.fm API</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="save_lfm">
        <div class="grid2">
          <div class="field"><label>API Key</label><input type="text" name="api_key" value="<?=htmlspecialchars($cfg['api_key']??'')?>" required></div>
          <div class="field"><label>API Secret</label><input type="password" name="api_secret" placeholder="••••••••" required></div>
        </div>
        <div class="grid2">
          <div class="field"><label>Użytkownik A</label><input type="text" name="a_user" value="<?=htmlspecialchars($cfg['a_user']??'')?>" required></div>
          <div class="field"><label>Session Key A</label><input type="password" name="a_sk" placeholder="••••••••" required></div>
        </div>
        <div class="grid2">
          <div class="field"><label>Użytkownik B</label><input type="text" name="b_user" value="<?=htmlspecialchars($cfg['b_user']??'')?>" required></div>
          <div class="field"><label>Session Key B</label><input type="password" name="b_sk" placeholder="••••••••" required></div>
        </div>
        <div style="margin-top:.875rem;background:#FEF7EC;border:1.5px solid #F5C96A;border-radius:8px;padding:1rem 1.25rem;">
          <div style="font-family:'DM Mono',monospace;font-size:.65rem;color:#A0660A;letter-spacing:.06em;text-transform:uppercase;margin-bottom:.5rem;font-weight:600;">⚠ Zabezpieczenie — wymagane hasło</div>
          <p style="font-size:.78rem;color:#7a5010;margin-bottom:.75rem;">Aby zapobiec przypadkowemu nadpisaniu konfiguracji, potwierdź swoim hasłem do panelu.</p>
          <input type="password" name="security_password" placeholder="Twoje hasło do panelu..." style="width:100%;background:#fff;border:1.5px solid #F5C96A;color:var(--text);padding:8px 11px;font-family:'DM Mono',monospace;font-size:.75rem;border-radius:6px;outline:none;" required>
        </div>
        <div style="margin-top:.875rem;"><button type="submit" class="btn primary">Zapisz konfigurację</button></div>
      </form>
    </div>
  </div>

  <!-- HISTORIA KONFIGURACJI -->
  <div class="card" style="margin-bottom:1.25rem;">
    <div class="card-head"><span class="card-title">Historia konfiguracji</span></div>
    <table class="tbl">
      <thead><tr><th>#</th><th>Konto A</th><th>Konto B</th><th>Zapisano</th></tr></thead>
      <tbody>
      <?php foreach ($cfgHistory as $c): ?>
        <tr>
          <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$c['id']?></td>
          <td><?=htmlspecialchars($c['a_user'])?></td>
          <td><?=htmlspecialchars($c['b_user'])?></td>
          <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$c['saved_at']?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- UŻYTKOWNICY -->
  <div class="card">
    <div class="card-head"><span class="card-title">Użytkownicy panelu</span></div>
    <table class="tbl">
      <thead><tr><th>Login</th><th>Konto Last.fm</th><th>Rola</th><th>Ostatnie logowanie</th><th>Zmień hasło</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:500"><?=htmlspecialchars($u['username'])?></td>
          <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--a)"><?=htmlspecialchars($u['lastfm_user']??'—')?></td>
          <td><span class="badge <?=$u['role']==='admin'?'badge-a':'badge-gray'?>"><?=$u['role']?></span></td>
          <td style="font-family:'DM Mono',monospace;font-size:.65rem;color:var(--text3)"><?=$u['last_login']?date('d.m.y H:i',strtotime($u['last_login'])):'—'?></td>
          <td>
            <form method="POST" style="display:flex;gap:6px;align-items:center;">
              <input type="hidden" name="action" value="change_password">
              <input type="hidden" name="user_id" value="<?=$u['id']?>">
              <input type="password" name="new_password" placeholder="Nowe hasło" style="background:#FAFAF8;border:1.5px solid var(--border);color:var(--text);padding:5px 8px;font-family:'DM Mono',monospace;font-size:.68rem;border-radius:6px;outline:none;width:140px;transition:border-color .2s;" onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
              <button type="submit" class="btn" style="padding:5px 10px;font-size:.65rem;">Zmień</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body></html>
