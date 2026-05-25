<?php
session_start();
require_once 'config.php';

// ── Setup: no password hash configured yet ────────────────────────────────────
if (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === '') {
    $generated = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['setup_pass'])) {
        $generated = password_hash($_POST['setup_pass'], PASSWORD_DEFAULT);
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin Setup — Kamel Ventures</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f9fafb;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
  .box{background:#fff;border:1.5px solid #e5e7eb;border-radius:16px;padding:40px;max-width:480px;width:100%}
  h2{font-size:18px;font-weight:600;margin-bottom:8px}
  p{font-size:13px;color:#6b7280;margin-bottom:20px;line-height:1.55}
  label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}
  input{width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;outline:none;margin-bottom:14px}
  input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(0,0,0,.06)}
  button{width:100%;padding:12px;background:#111;color:#fff;font-size:14px;font-weight:600;font-family:inherit;border:none;border-radius:8px;cursor:pointer}
  .hash-box{background:#f3f4f6;border-radius:8px;padding:14px;font-size:12px;font-family:monospace;word-break:break-all;margin:16px 0;color:#111}
  .step{background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:14px;font-size:13px;color:#854d0e;line-height:1.55}
  .step code{font-family:monospace;background:rgba(0,0,0,.06);padding:1px 5px;border-radius:3px}
</style>
</head>
<body>
<div class="box">
  <h2>Admin Setup</h2>
  <p>Generate a password hash, then paste it into <code>config.php</code> to activate the admin panel.</p>
  <form method="POST">
    <label for="sp">Choose a password</label>
    <input id="sp" name="setup_pass" type="password" placeholder="Enter your admin password" required autocomplete="new-password"/>
    <button type="submit">Generate Hash</button>
  </form>
  <?php if ($generated): ?>
  <div class="hash-box"><?= htmlspecialchars($generated) ?></div>
  <div class="step">
    Copy the hash above and open <code>config.php</code>.<br/>
    Set <code>define('ADMIN_PASSWORD_HASH', '<em>paste-hash-here</em>');</code><br/>
    Then refresh this page to access the admin panel.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
<?php
    exit;
}

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.html');
    exit;
}

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// ── Login ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['kv_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['kv_admin'] = true;
            header('Location: admin.php');
            exit;
        }
        header('Location: admin.html?error=1');
        exit;
    }
    header('Location: admin.html');
    exit;
}

// ── DB connection ─────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}

// ── Delete signup ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { die('Invalid CSRF token.'); }
    $pdo->prepare('DELETE FROM signups WHERE id = ?')->execute([(int) $_POST['delete_id']]);
    header('Location: admin.php');
    exit;
}

// ── CSV export ────────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $rows = $pdo->query('SELECT first_name, last_name, email, role, amount, created_at FROM signups ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kamel-signups-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['First Name', 'Last Name', 'Email', 'Role', 'Amount (USD)', 'Signed Up']);
    foreach ($rows as $r) { fputcsv($f, $r); }
    fclose($f);
    exit;
}

// ── Fetch signups + stats ─────────────────────────────────────────────────────
$signups  = $pdo->query('SELECT * FROM signups ORDER BY created_at DESC')->fetchAll();
$total    = count($signups);
$pledged  = array_sum(array_column($signups, 'amount'));
$by_role  = ['investor' => 0, 'founder' => 0, 'mentor' => 0, 'other' => 0, '' => 0];
foreach ($signups as $s) { $by_role[$s['role'] ?? '']++; }

$role_labels = [
    'investor' => 'Investor',
    'founder'  => 'Founder',
    'mentor'   => 'Mentor',
    'other'    => 'Other',
    ''         => 'Unspecified',
];
$role_colors = [
    'investor' => '#dbeafe:#1d4ed8',
    'founder'  => '#dcfce7:#15803d',
    'mentor'   => '#fef9c3:#a16207',
    'other'    => '#f3f4f6:#374151',
    ''         => '#f3f4f6:#6b7280',
];

function role_badge(string $role): string {
    global $role_labels, $role_colors;
    [$bg, $fg] = explode(':', $role_colors[$role] ?? '#f3f4f6:#374151');
    $label = htmlspecialchars($role_labels[$role] ?? $role);
    return "<span style='background:{$bg};color:{$fg};padding:2px 9px;border-radius:100px;font-size:11px;font-weight:600;white-space:nowrap'>{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin — Kamel Ventures</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f9fafb;color:#111;min-height:100vh}

  /* Header */
  .topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
  .topbar-left{display:flex;align-items:center;gap:14px}
  .topbar img{height:36px;width:auto}
  .topbar-title{font-size:15px;font-weight:600}
  .topbar-sub{font-size:12px;color:#9ca3af}
  .logout{font-size:13px;color:#6b7280;text-decoration:none;padding:7px 14px;border:1px solid #e5e7eb;border-radius:8px;transition:background .15s}
  .logout:hover{background:#f3f4f6}

  /* Main */
  .main{max-width:1100px;margin:0 auto;padding:32px 24px}

  /* Stats */
  .stats{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:28px}
  .stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px}
  .stat-val{font-size:28px;font-weight:700;line-height:1;margin-bottom:4px}
  .stat-label{font-size:12px;color:#6b7280;font-weight:500}

  /* Toolbar */
  .toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px}
  .toolbar h2{font-size:15px;font-weight:600}
  .export-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#111;color:#fff;font-size:13px;font-weight:600;font-family:inherit;border:none;border-radius:8px;text-decoration:none;cursor:pointer;transition:opacity .15s}
  .export-btn:hover{opacity:.85}

  /* Table */
  .table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
  table{width:100%;border-collapse:collapse}
  thead th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:#6b7280;background:#f9fafb;border-bottom:1px solid #e5e7eb;white-space:nowrap}
  tbody tr{border-bottom:1px solid #f3f4f6;transition:background .1s}
  tbody tr:last-child{border-bottom:none}
  tbody tr:hover{background:#fafafa}
  td{padding:12px 16px;font-size:13px;color:#374151;vertical-align:middle}
  td.name{font-weight:500;color:#111}
  td.email a{color:#374151;text-decoration:none}
  td.email a:hover{text-decoration:underline}
  td.date{color:#9ca3af;font-size:12px;white-space:nowrap}

  /* Delete button */
  .del-btn{background:none;border:none;cursor:pointer;color:#d1d5db;padding:4px 6px;border-radius:6px;transition:color .15s,background .15s;display:flex;align-items:center}
  .del-btn:hover{color:#dc2626;background:#fef2f2}

  /* Empty state */
  .empty{padding:60px;text-align:center;color:#9ca3af;font-size:14px}

  @media(max-width:768px){
    .stats{grid-template-columns:repeat(2,1fr)}
    .main{padding:20px 16px}
    .topbar{padding:0 16px}
    table{font-size:12px}
    td,thead th{padding:10px 12px}
  }
</style>
</head>
<body>

<!-- HEADER -->
<div class="topbar">
  <div class="topbar-left">
    <img src="camel.png" alt="Kamel Ventures"/>
    <div>
      <div class="topbar-title">Kamel Ventures</div>
      <div class="topbar-sub">Signups Admin</div>
    </div>
  </div>
  <a class="logout" href="admin.php?logout=1">Sign out</a>
</div>

<!-- MAIN -->
<div class="main">

  <!-- STATS -->
  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= $total ?></div>
      <div class="stat-label">Total signups</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#1d4ed8"><?= $by_role['investor'] ?></div>
      <div class="stat-label">Investors</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#15803d"><?= $by_role['founder'] ?></div>
      <div class="stat-label">Founders</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#a16207"><?= $by_role['mentor'] ?></div>
      <div class="stat-label">Mentors</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#6b7280"><?= $by_role['other'] + $by_role[''] ?></div>
      <div class="stat-label">Other / unspecified</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#15803d;font-size:22px">$<?= number_format($pledged) ?></div>
      <div class="stat-label">Total pledged</div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="toolbar">
    <h2>All Signups</h2>
    <a class="export-btn" href="admin.php?export=1">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Export CSV
    </a>
  </div>

  <div class="table-wrap">
    <?php if (empty($signups)): ?>
      <div class="empty">No signups yet — share the link!</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Amount</th>
          <th>Signed Up</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($signups as $i => $row): ?>
        <tr>
          <td style="color:#d1d5db;font-size:12px"><?= $total - $i ?></td>
          <td class="name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
          <td class="email"><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></td>
          <td><?= role_badge($row['role'] ?? '') ?></td>
          <td style="font-weight:500;color:<?= $row['amount'] ? '#15803d' : '#d1d5db' ?>">
            <?= $row['amount'] ? '$' . number_format((int)$row['amount']) : '—' ?>
          </td>
          <td class="date"><?= date('M j, Y · g:ia', strtotime($row['created_at'])) ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($row['first_name'])) ?> from the list?')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"/>
              <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>"/>
              <button class="del-btn" type="submit" title="Remove">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
