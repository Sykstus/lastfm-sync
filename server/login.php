<?php
require_once __DIR__ . '/auth.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (attemptLogin($u, $p)) { header('Location: dashboard.php'); exit; }
    $error = 'Nieprawidłowy login lub hasło';
}
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Last.fm Panel · Logowanie</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F7F5F0;--white:#FFFFFF;--text:#1a1a1a;--text2:#6b6b6b;--text3:#aaa;
  --border:#E2DFD8;--border2:#ccc;
  --accent:#C8503C;--accent2:#E8694E;
  --green:#2D7D4F;--red:#C8503C;
  --shadow:0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
}
html{font-size:15px;}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;font-weight:300;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
.wrap{width:100%;max-width:400px;}
.brand{text-align:center;margin-bottom:2.5rem;}
.brand-mark{width:48px;height:48px;background:var(--accent);border-radius:12px;display:inline-grid;place-items:center;margin-bottom:1rem;}
.brand-mark svg{width:22px;height:22px;}
.brand h1{font-family:'DM Serif Display',serif;font-size:1.75rem;font-weight:400;letter-spacing:-.02em;color:var(--text);margin-bottom:.3rem;}
.brand p{font-size:.82rem;color:var(--text2);}
.card{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:2rem;box-shadow:var(--shadow);}
.field{margin-bottom:1.1rem;}
.field label{display:block;font-family:'DM Mono',monospace;font-size:.63rem;color:var(--text3);letter-spacing:.09em;text-transform:uppercase;margin-bottom:5px;font-weight:500;}
.field input{width:100%;background:#FAFAF8;border:1.5px solid var(--border);color:var(--text);padding:10px 13px;font-family:'DM Sans',sans-serif;font-size:.9rem;border-radius:8px;outline:none;transition:border-color .2s,box-shadow .2s;}
.field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,80,60,0.1);}
.error{background:#FEF2F0;border:1.5px solid rgba(200,80,60,.25);color:var(--red);padding:.75rem 1rem;border-radius:8px;font-size:.8rem;font-family:'DM Mono',monospace;margin-bottom:1rem;}
button[type=submit]{width:100%;background:var(--accent);color:#fff;border:none;padding:12px;font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:500;border-radius:8px;cursor:pointer;transition:background .15s,transform .1s;letter-spacing:.01em;}
button[type=submit]:hover{background:var(--accent2);}
button[type=submit]:active{transform:scale(.99);}
.hint{margin-top:1.25rem;font-size:.72rem;color:var(--text3);text-align:center;font-family:'DM Mono',monospace;line-height:1.6;}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-mark"><svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="9" stroke="white" stroke-width="1.5"/><path d="M11 6v5.5l3 1.8" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg></div>
    <h1>Last.fm Panel</h1>
    <p>Synchronizacja scrobbli</p>
  </div>
  <div class="card">
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="field">
        <label>Login</label>
        <input type="text" name="username" placeholder="sykstus" autocomplete="username" required>
      </div>
      <div class="field">
        <label>Hasło</label>
        <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit">Zaloguj się →</button>
    </form>
  </div>
  <p class="hint">sykstus / music123 &nbsp;·&nbsp; surprice / music456<br>Zmień hasła po pierwszym logowaniu</p>
</div>
</body>
</html>
