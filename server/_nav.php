<!-- _nav.php — include na początku każdej strony -->
<?php
$navUser = currentUser();
$navIsAdmin = $navUser['role'] === 'admin';
$navPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Last.fm Panel · <?= htmlspecialchars($navPage === 'dashboard' ? 'Dashboard' : ($navPage === 'scrobbles' ? 'Scroble' : 'Ustawienia')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F7F5F0;
  --bg2:#FFFFFF;
  --bg3:#F2F0EB;
  --border:#E2DFD8;
  --border2:#D0CEC7;
  --text:#1a1a1a;
  --text2:#5a5a5a;
  --text3:#9a9a9a;
  --accent:#C8503C;
  --accent2:#E8694E;
  --a:#2B7A3B;
  --a-bg:#EBF5EE;
  --a-border:#B8DEC0;
  --b:#1A5FA8;
  --b-bg:#EBF2FB;
  --b-border:#B3CFF0;
  --red:#C8503C;
  --red-bg:#FEF2F0;
  --green:#2D7D4F;
  --green-bg:#EBF5EE;
  --amber:#A0660A;
  --amber-bg:#FEF7EC;
  --shadow-sm:0 1px 2px rgba(0,0,0,0.05);
  --shadow:0 1px 4px rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.04);
  --r:10px;
}
html{font-size:14px;}
body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;font-weight:300;min-height:100vh;}

/* NAV */
nav{background:var(--bg2);border-bottom:1px solid var(--border);padding:.875rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:var(--shadow-sm);}
.nav-brand{display:flex;align-items:center;gap:10px;}
.nav-mark{width:28px;height:28px;background:var(--accent);border-radius:7px;display:grid;place-items:center;flex-shrink:0;}
.nav-mark svg{width:14px;height:14px;}
.nav-title{font-family:'DM Serif Display',serif;font-size:1rem;color:var(--text);letter-spacing:-.01em;}
.nav-right{display:flex;align-items:center;gap:8px;}
.nav-links{display:flex;gap:2px;}
.nav-links a{font-size:.78rem;font-weight:400;padding:6px 12px;border-radius:7px;color:var(--text2);text-decoration:none;transition:all .15s;}
.nav-links a:hover{background:var(--bg3);color:var(--text);}
.nav-links a.active{background:var(--bg3);color:var(--text);font-weight:500;}
.nav-sep{width:1px;height:20px;background:var(--border);margin:0 4px;}
.nav-user{font-size:.75rem;color:var(--text3);display:flex;align-items:center;gap:6px;}
.nav-user a{color:var(--text3);text-decoration:none;transition:color .15s;}
.nav-user a:hover{color:var(--accent);}
.nav-avatar{width:26px;height:26px;border-radius:50%;background:var(--accent);display:grid;place-items:center;font-size:.65rem;font-weight:500;color:#fff;flex-shrink:0;}

/* LAYOUT */
main{max-width:1120px;margin:0 auto;padding:2rem 2rem 5rem;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem;}
.page-title{font-family:'DM Serif Display',serif;font-size:1.6rem;font-weight:400;letter-spacing:-.02em;color:var(--text);}

/* CARDS */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow-sm);}
.card-head{padding:.875rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--bg2);}
.card-title{font-family:'DM Mono',monospace;font-size:.65rem;font-weight:500;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);}
.card-body{padding:1.25rem;}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:1.5rem;}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.25rem;box-shadow:var(--shadow-sm);}
.stat-n{font-family:'DM Serif Display',serif;font-size:2.2rem;font-weight:400;line-height:1;margin-bottom:4px;font-variant-numeric:tabular-nums;}
.stat-n.ca{color:var(--a);}
.stat-n.cb{color:var(--b);}
.stat-n.cc{color:var(--accent);}
.stat-l{font-family:'DM Mono',monospace;font-size:.6rem;color:var(--text3);letter-spacing:.06em;text-transform:uppercase;}

/* TWO COL */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;}

/* TABLE */
.tbl{width:100%;border-collapse:collapse;font-size:.82rem;}
.tbl th{font-family:'DM Mono',monospace;font-size:.6rem;letter-spacing:.07em;color:var(--text3);text-transform:uppercase;padding:.6rem 1rem;text-align:left;border-bottom:1px solid var(--border);font-weight:500;background:var(--bg3);}
.tbl td{padding:.55rem 1rem;border-bottom:1px solid var(--border);color:var(--text);}
.tbl tr:last-child td{border-bottom:none;}
.tbl tbody tr:hover td{background:#FAFAF8;}

/* BADGES */
.badge{font-family:'DM Mono',monospace;font-size:.6rem;padding:3px 8px;border-radius:5px;font-weight:500;letter-spacing:.03em;}
.badge-a{background:var(--a-bg);color:var(--a);border:1px solid var(--a-border);}
.badge-b{background:var(--b-bg);color:var(--b);border:1px solid var(--b-border);}
.badge-ok{background:var(--green-bg);color:var(--green);}
.badge-err{background:var(--red-bg);color:var(--red);}
.badge-run{background:var(--amber-bg);color:var(--amber);}
.badge-gray{background:var(--bg3);color:var(--text3);border:1px solid var(--border);}

/* BUTTONS */
.btn{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:500;padding:7px 16px;border-radius:7px;cursor:pointer;border:1.5px solid var(--border2);background:var(--bg2);color:var(--text2);transition:all .15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none;line-height:1;}
.btn:hover{border-color:var(--text3);color:var(--text);background:var(--bg3);}
.btn:active{transform:scale(.98);}
.btn.primary{background:var(--accent);color:#fff;border-color:var(--accent);}
.btn.primary:hover{background:var(--accent2);border-color:var(--accent2);}
.btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none;}

/* LOG */
.log-area{max-height:300px;overflow-y:auto;background:var(--bg3);}
.log-area::-webkit-scrollbar{width:4px;}
.log-area::-webkit-scrollbar-thumb{background:var(--border2);border-radius:2px;}
.log-line{display:grid;grid-template-columns:56px 64px 1fr;gap:10px;padding:5px 1.1rem;border-bottom:1px solid var(--border);font-family:'DM Mono',monospace;font-size:.66rem;line-height:1.5;}
.log-line:last-child{border-bottom:none;}
.lt{color:var(--text3);}
.ls{color:var(--text3);}
.lm{color:var(--text2);word-break:break-all;}
.lm.ok{color:var(--green);}
.lm.err{color:var(--red);}
.lm.info{color:var(--b);}
.lm.warn{color:var(--amber);}
.log-empty{padding:1.5rem;text-align:center;font-family:'DM Mono',monospace;font-size:.7rem;color:var(--text3);}

/* NP PILL */
.np-pill{display:inline-flex;align-items:center;gap:5px;font-family:'DM Mono',monospace;font-size:.6rem;padding:3px 8px;border-radius:5px;}
.np-on{background:var(--green-bg);color:var(--green);border:1px solid var(--a-border);}
.np-off{background:var(--bg3);color:var(--text3);border:1px solid var(--border);}
.dot-s{width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0;}
.dot-s.pulse{animation:glow 1.5s ease-in-out infinite;}
@keyframes glow{0%,100%{opacity:1}50%{opacity:.3}}

/* MISC */
.sep{height:1px;background:var(--border);margin:1.5rem 0;}
.hint{font-size:.72rem;color:var(--text3);}
.hint a{color:var(--b);text-decoration:none;}
.hint a:hover{text-decoration:underline;}
.banner{padding:.75rem 1.1rem;border-radius:8px;font-family:'DM Mono',monospace;font-size:.72rem;margin-bottom:1rem;}
.banner.ok{background:var(--green-bg);color:var(--green);border:1px solid var(--a-border);}
.banner.err{background:var(--red-bg);color:var(--red);border:1px solid rgba(200,80,60,.2);}
.field{margin-bottom:.875rem;}
.field label{display:block;font-family:'DM Mono',monospace;font-size:.63rem;color:var(--text3);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px;font-weight:500;}
.field input,.field select{width:100%;background:#FAFAF8;border:1.5px solid var(--border);color:var(--text);padding:8px 11px;font-family:'DM Mono',monospace;font-size:.75rem;border-radius:7px;outline:none;transition:border-color .2s,box-shadow .2s;}
.field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,80,60,0.08);}
.field input::placeholder{color:var(--text3);}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:700px){.stats-grid{grid-template-columns:1fr 1fr;}.two-col{grid-template-columns:1fr;}.nav-links{display:none;}}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">
    <div class="nav-mark"><svg viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="white" stroke-width="1.2"/><path d="M7 4v3.5l2 1.2" stroke="white" stroke-width="1.2" stroke-linecap="round"/></svg></div>
    <span class="nav-title">Last.fm Panel</span>
  </div>
  <div class="nav-right">
    <div class="nav-links">
      <a href="dashboard.php" <?= $navPage==='dashboard'?'class="active"':'' ?>>Dashboard</a>
      <a href="scrobbles.php" <?= $navPage==='scrobbles'?'class="active"':'' ?>>Scroble</a>
      <a href="stats.php" <?= $navPage==='stats'?'class="active"':'' ?>>Statystyki</a>
      <a href="discover.php" <?= $navPage==='discover'?'class="active"':'' ?>>Odkryj</a>
      <?php if ($navIsAdmin): ?><a href="settings.php" <?= $navPage==='settings'?'class="active"':'' ?>>Ustawienia</a><?php endif; ?>
    </div>
    <div class="nav-sep"></div>
    <div class="nav-user">
      <div class="nav-avatar"><?= strtoupper(substr($navUser['username'],0,1)) ?></div>
      <?= htmlspecialchars($navUser['username']) ?>
      <?php if ($navUser['lastfm_user']): ?><span style="color:var(--text3)">· <?= htmlspecialchars($navUser['lastfm_user']) ?></span><?php endif; ?>
      · <a href="logout.php">wyloguj</a>
    </div>
  </div>
</nav>
