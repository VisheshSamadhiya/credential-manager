<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT id, full_name, username, email, role
     FROM users
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$credentialCount = 0;
$departmentCount = 0;
$employeeCount = 0;

try {
    $credentialCount = (int) $pdo->query("SELECT COUNT(*) FROM credentials")->fetchColumn();
    $departmentCount = (int) $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $employeeCount = (int) $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
} catch (PDOException $e) {
    // Keep dashboard available if statistics fail.
}

$recentCredentials = [];

try {
    $recentStmt = $pdo->query(
        "SELECT
            credentials.id,
            credentials.service_name,
            credentials.created_at,
            users.full_name AS created_by_name
         FROM credentials
         LEFT JOIN users ON credentials.created_by = users.id
         ORDER BY credentials.created_at DESC
         LIMIT 5"
    );
    $recentCredentials = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentCredentials = [];
}

$role = (string) $user['role'];
$roleLabel = ucfirst($role);
$fullName = (string) $user['full_name'];
$initial = strtoupper(substr($fullName, 0, 1));

function dashboardIcon(string $name, string $class = 'icon'): string
{
    $icons = [
        'dashboard' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'departments' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21V7l9-4 9 4v14"/><path d="M7 21V9h10v12M3 21h18M9 13h2m2 0h2m-6 4h2m2 0h2"/></svg>',
        'credentials' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15.5" r="4"/><path d="m11 12.5 8-8M16 5l3 3M14 9l2 2"/></svg>',
        'users' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c.5-3.5 2.5-5 6-5s5.5 1.5 6 5M17 11a3 3 0 1 0 0-6M16 15c2.8 0 4.5 1.3 5 4"/></svg>',
        'settings' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1a2 2 0 1 1-2.8 2.8l-.1-.1a2 2 0 0 0-3.4 1.4v.2a2 2 0 1 1-4 0v-.2A2 2 0 0 0 5.8 18l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A2 2 0 0 0 1.6 12a2 2 0 1 1 0-4h.2a2 2 0 0 0 1.4-3.4l-.1-.1A2 2 0 1 1 6 1.7l.1.1A2 2 0 0 0 9.5.4V.2a2 2 0 1 1 4 0v.2A2 2 0 0 0 17 1.8l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A2 2 0 0 0 21.2 8h.2a2 2 0 1 1 0 4h-.2a2 2 0 0 0-1.8 3Z"/></svg>',
        'audit' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18H7z"/><path d="M9.5 7h5M9.5 11h5M9.5 15h3M5 6H3v15h10"/></svg>',
        'root' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>',
        'logout' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M9 12h9"/></svg>',
        'search' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>',
        'key' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15.5" r="4"/><path d="m11 12.5 8-8M16 5l3 3M14 9l2 2"/></svg>',
        'employee' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 21c.7-4.2 3-6 7-6s6.3 1.8 7 6"/></svg>',
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 20 6v5c0 5-3.2 8.7-8 10-4.8-1.3-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>',
        'plus' => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    ];

    return $icons[$name] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>Dashboard · Credential Manager</title>
<link rel="stylesheet" href="/assets/theme.css">

<style>
:root {
    --purple-950:#240047;
    --purple-900:#3b0764;
    --purple-800:#581c87;
    --purple-700:#6d28d9;
    --purple-600:#7c3aed;
    --purple-500:#8b5cf6;
    --pink-600:#db2777;
    --pink-500:#ec4899;
    --bg:#faf7ff;
    --card:#fff;
    --text:#1f1533;
    --muted:#746b82;
    --border:#eee7f7;
    --gradient:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 48%,#ec4899 100%);
}

*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
    background:
        radial-gradient(circle at 85% 5%,rgba(236,72,153,.08),transparent 25%),
        radial-gradient(circle at 20% 10%,rgba(124,58,237,.07),transparent 28%),
        var(--bg);
    color:var(--text);
}

svg.icon{
    width:20px;
    height:20px;
    fill:none;
    stroke:currentColor;
    stroke-width:1.9;
    stroke-linecap:round;
    stroke-linejoin:round;
    flex:0 0 auto;
}

.sidebar{
    position:fixed;
    inset:0 auto 0 0;
    width:270px;
    height:100vh;
    padding:22px 16px;
    background:
        radial-gradient(circle at 100% 0%,rgba(236,72,153,.22),transparent 32%),
        linear-gradient(180deg,#25004a 0%,#32105f 48%,#1f0a3b 100%);
    color:#fff;
    display:flex;
    flex-direction:column;
    z-index:1000;
    box-shadow:8px 0 35px rgba(45,0,75,.18);
}

.logo{
    display:flex;
    align-items:center;
    gap:12px;
    padding:8px 10px 26px;
}
.logo-icon{
    width:46px;
    height:46px;
    display:grid;
    place-items:center;
    border-radius:14px;
    background:linear-gradient(135deg,#8b5cf6,#ec4899);
    box-shadow:0 8px 24px rgba(236,72,153,.28);
}
.logo-icon svg{width:25px;height:25px}
.logo-text{font-size:18px;font-weight:800;letter-spacing:-.3px}

.nav-section{
    margin:20px 12px 8px;
    color:#c4b5fd;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:1.5px;
}
.nav-link{
    position:relative;
    display:flex;
    align-items:center;
    gap:13px;
    padding:12px 13px;
    margin-bottom:5px;
    border-radius:12px;
    color:#ddd6fe;
    text-decoration:none;
    font-size:14px;
    font-weight:600;
    transition:background .2s ease,color .2s ease,transform .2s ease;
}
.nav-link:hover{background:rgba(255,255,255,.09);color:#fff;transform:translateX(2px)}
.nav-link.active{
    background:linear-gradient(90deg,rgba(139,92,246,.95),rgba(236,72,153,.78));
    color:#fff;
    box-shadow:0 8px 22px rgba(124,58,237,.28);
}
.nav-icon{
    width:22px;
    display:grid;
    place-items:center;
    color:inherit;
}
.nav-icon svg{width:19px;height:19px}
.sidebar-bottom{margin-top:auto}
.logout-link{background:rgba(244,63,94,.08);color:#fecdd3}
.logout-link:hover{background:rgba(244,63,94,.82)}

.main{margin-left:270px;min-height:100vh}

.topbar{
    position:sticky;
    top:0;
    height:78px;
    padding:0 32px;
    background:rgba(255,255,255,.9);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(238,231,247,.9);
    display:flex;
    align-items:center;
    gap:22px;
    z-index:900;
}
.page-title{min-width:105px;font-size:20px;font-weight:800;letter-spacing:-.5px}

.search-form{flex:1;max-width:560px;position:relative;margin:0 auto}
.search-input{
    width:100%;
    height:42px;
    padding:0 50px 0 17px;
    border:1px solid #e4d9ef;
    border-radius:11px;
    outline:none;
    font-family:inherit;
    font-size:13px;
    font-weight:500;
    color:#2d2340;
    background:#faf8fd;
    transition:border-color .2s ease,background-color .2s ease,box-shadow .2s ease;
}
.search-input::placeholder{color:#968ca5;font-weight:500}
.search-input:hover{border-color:#c8afe2}
.search-input:focus{background:#fff;border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.1)}
.search-button{
    position:absolute;
    right:4px;
    top:4px;
    width:34px;
    height:34px;
    padding:0;
    border:0;
    border-radius:8px;
    background:linear-gradient(135deg,#7c3aed,#db2777);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    opacity:.92;
    transition:transform .18s ease,opacity .18s ease,box-shadow .18s ease;
}
.search-button svg{width:17px;height:17px}
.search-button:hover{opacity:1;transform:translateY(-1px);box-shadow:0 5px 14px rgba(124,58,237,.24)}
.search-button:active{transform:translateY(0)}

.user-profile{display:flex;align-items:center;gap:10px;white-space:nowrap}
.avatar{
    width:42px;height:42px;border-radius:13px;display:grid;place-items:center;
    background:linear-gradient(135deg,#ede9fe,#fce7f3);color:#7c3aed;font-weight:900;
    box-shadow:inset 0 0 0 1px rgba(124,58,237,.08);
}
.user-name{font-size:13px;font-weight:800}
.user-role{margin-top:2px;color:var(--muted);font-size:11px;font-weight:600}

.content{padding:32px 34px 48px;max-width:1600px}
.alert{padding:14px 17px;border-radius:13px;margin-bottom:20px;font-size:13px;font-weight:600}
.alert-warning{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}

.welcome-banner{
    position:relative;
    overflow:hidden;
    padding:32px;
    margin-bottom:24px;
    border-radius:21px;
    color:#fff;
    background:var(--gradient);
    box-shadow:0 20px 50px rgba(124,58,237,.22);
}
.welcome-banner::before,.welcome-banner::after{
    content:"";position:absolute;border-radius:50%;pointer-events:none;
}
.welcome-banner::before{width:250px;height:250px;right:-70px;top:-110px;background:rgba(255,255,255,.12)}
.welcome-banner::after{width:170px;height:170px;right:180px;bottom:-115px;background:rgba(255,255,255,.08)}
.welcome-content{position:relative;z-index:1}
.welcome-banner h1{margin:0 0 8px;font-size:clamp(24px,3vw,32px);letter-spacing:-.9px}
.welcome-banner p{margin:0;max-width:620px;color:rgba(255,255,255,.88);line-height:1.6;font-size:14px}
.role-badge{
    display:inline-flex;align-items:center;gap:7px;margin-top:17px;padding:8px 13px;
    border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.14);
    font-size:11px;font-weight:800;
}
.role-badge svg{width:14px;height:14px}

.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:17px;margin-bottom:24px}
.stat-card{
    position:relative;overflow:hidden;min-height:135px;padding:20px;background:var(--card);
    border:1px solid var(--border);border-radius:17px;box-shadow:0 14px 40px rgba(91,33,182,.09);
    transition:transform .22s ease,box-shadow .22s ease;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 18px 45px rgba(91,33,182,.13)}
.stat-card::after{
    content:"";position:absolute;width:90px;height:90px;right:-38px;bottom:-42px;border-radius:50%;
    background:linear-gradient(135deg,rgba(124,58,237,.08),rgba(236,72,153,.12));
}
.stat-top{display:flex;align-items:center;justify-content:space-between}
.stat-label{color:var(--muted);font-size:12px;font-weight:700}
.stat-number{margin-top:12px;font-size:29px;font-weight:900;letter-spacing:-.7px}
.stat-icon{
    width:42px;height:42px;display:grid;place-items:center;border-radius:12px;
    background:linear-gradient(135deg,#f3e8ff,#fce7f3);color:#7c3aed;
}
.stat-icon svg{width:21px;height:21px}

.dashboard-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(290px,1fr);gap:21px}
.panel{overflow:hidden;background:var(--card);border:1px solid var(--border);border-radius:18px;box-shadow:0 14px 40px rgba(91,33,182,.09)}
.panel-header{display:flex;align-items:center;justify-content:space-between;padding:19px 22px;border-bottom:1px solid #f1ebf7}
.panel-title{font-size:15px;font-weight:850}
.view-all{color:#7c3aed;text-decoration:none;font-size:12px;font-weight:800}
.view-all:hover{color:#db2777}

.credential-item{
    display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 22px;
    border-bottom:1px solid #f4eff8;transition:background .18s ease;
}
.credential-item:last-child{border-bottom:0}
.credential-item:hover{background:linear-gradient(90deg,#fff,#fcf8ff)}
.service-info{display:flex;align-items:center;gap:12px;min-width:0}
.service-icon{
    width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:12px;
    background:linear-gradient(135deg,#f3e8ff,#fce7f3);color:#7c3aed;
}
.service-icon svg{width:20px;height:20px}
.service-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:800}
.service-meta{margin-top:3px;color:var(--muted);font-size:11px}
.empty-state{padding:42px 22px;text-align:center;color:var(--muted);font-size:13px}

.quick-actions{padding:17px;display:flex;flex-direction:column;gap:9px}
.action-button{
    display:flex;align-items:center;gap:10px;padding:12px 13px;border:1px solid #eee6f8;border-radius:12px;
    color:#41374e;background:#fcfaff;text-decoration:none;font-size:12px;font-weight:750;
    transition:transform .2s ease,background .2s ease,border-color .2s ease,color .2s ease;
}
.action-button svg{width:18px;height:18px}
.action-button:hover{color:#6d28d9;border-color:#ddd0f4;background:linear-gradient(90deg,#faf5ff,#fff1f8);transform:translateX(3px)}
.action-button:first-child{color:#fff;border-color:transparent;background:var(--gradient);box-shadow:0 8px 20px rgba(124,58,237,.16)}
.action-button:first-child:hover{color:#fff}

html.dark-theme body{
    background:
        radial-gradient(circle at 85% 5%,rgba(236,72,153,.08),transparent 25%),
        radial-gradient(circle at 20% 10%,rgba(124,58,237,.08),transparent 28%),
        #100a19;
    color:#f5f3ff;
}
html.dark-theme .topbar{background:rgba(20,13,30,.88)!important;border-bottom-color:rgba(76,29,98,.65)!important}
html.dark-theme .page-title{color:#f5f3ff!important}
html.dark-theme .search-input{background:#160f21!important;color:#f5f3ff!important;border-color:#4c3262!important}
html.dark-theme .search-input::placeholder{color:#91869e!important}
html.dark-theme .search-input:focus{background:#160f21!important;border-color:#a855f7!important;box-shadow:0 0 0 3px rgba(168,85,247,.14)!important}
html.dark-theme .user-name{color:#f5f3ff}
html.dark-theme .user-role{color:#a99bb5}
html.dark-theme .stat-card,html.dark-theme .panel{background:#1b1227;border-color:#3b2948;box-shadow:0 14px 40px rgba(0,0,0,.22)}
html.dark-theme .stat-label,html.dark-theme .service-meta{color:#a99bb5}
html.dark-theme .stat-number,html.dark-theme .service-name,html.dark-theme .panel-title{color:#f5f3ff}
html.dark-theme .panel-header,html.dark-theme .credential-item{border-bottom-color:#3b2948}
html.dark-theme .credential-item:hover{background:linear-gradient(90deg,#241731,#2b1939)}
html.dark-theme .view-all{color:#c084fc}
html.dark-theme .view-all:hover{color:#f472b6}
html.dark-theme .action-button{background:#211631;color:#e9d5ff;border-color:#4c3262}
html.dark-theme .action-button:hover{background:linear-gradient(90deg,#32194b,#3a1837);color:#d8b4fe;border-color:#7e4ca3}
html.dark-theme .action-button:first-child{background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;border-color:transparent}
html.dark-theme .stat-icon,html.dark-theme .service-icon{background:linear-gradient(135deg,#2d1c3d,#3a1d36);color:#d8b4fe}

@media(max-width:1200px){
    .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .topbar{gap:15px;padding:0 24px}
    .content{padding:28px 24px 40px}
}
@media(max-width:1000px){.dashboard-grid{grid-template-columns:1fr}}
@media(max-width:850px){
    .sidebar{width:76px;padding:18px 9px}
    .logo{justify-content:center;padding:6px 0 25px}
    .logo-text,.nav-text,.nav-section{display:none}
    .nav-link{justify-content:center;padding:13px 8px}
    .main{margin-left:76px}
}
@media(max-width:650px){
    .topbar{height:auto;min-height:74px;padding:12px 15px;flex-wrap:wrap}
    .page-title{display:none}
    .search-form{order:3;flex-basis:100%;max-width:none}
    .user-details{display:none}
    .content{padding:20px 14px 30px}
    .welcome-banner{padding:25px 21px;border-radius:18px}
    .stats-grid{grid-template-columns:1fr 1fr;gap:12px}
    .stat-card{min-height:120px;padding:16px}
    .stat-number{font-size:25px}
    .credential-item{padding:14px 15px}
}
@media(max-width:450px){.stats-grid{grid-template-columns:1fr}.service-meta{max-width:150px}}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon"><?= dashboardIcon('shield') ?></div>
        <div class="logo-text">Credential Manager</div>
    </div>

    <div class="nav-section">Main Menu</div>

    <a href="/dashboard.php" class="nav-link active">
        <span class="nav-icon"><?= dashboardIcon('dashboard') ?></span>
        <span class="nav-text">Dashboard</span>
    </a>

    <a href="/departments.php" class="nav-link">
        <span class="nav-icon"><?= dashboardIcon('departments') ?></span>
        <span class="nav-text">Departments</span>
    </a>

    <a href="/credentials.php" class="nav-link">
        <span class="nav-icon"><?= dashboardIcon('credentials') ?></span>
        <span class="nav-text">Credentials</span>
    </a>

    <?php if (canManageUsers() || canManageDepartments() || isAdmin()): ?>
        <div class="nav-section">Administration</div>
    <?php endif; ?>

    <?php if (canManageUsers()): ?>
        <a href="/manage-users.php" class="nav-link">
            <span class="nav-icon"><?= dashboardIcon('users') ?></span>
            <span class="nav-text">Manage Users</span>
        </a>
    <?php endif; ?>

    <?php if (canManageDepartments()): ?>
        <a href="/manage-departments.php" class="nav-link">
            <span class="nav-icon"><?= dashboardIcon('settings') ?></span>
            <span class="nav-text">Manage Departments</span>
        </a>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
        <a href="/audit-logs.php" class="nav-link">
            <span class="nav-icon"><?= dashboardIcon('audit') ?></span>
            <span class="nav-text">Audit Logs</span>
        </a>
    <?php endif; ?>

    <?php if (function_exists('canManageRoot') && canManageRoot()): ?>
        <a href="/root-management.php" class="nav-link">
            <span class="nav-icon"><?= dashboardIcon('root') ?></span>
            <span class="nav-text">Root Management</span>
        </a>
    <?php endif; ?>

    <div class="sidebar-bottom">
        <a href="/logout.php" class="nav-link logout-link">
            <span class="nav-icon"><?= dashboardIcon('logout') ?></span>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="page-title">Dashboard</div>

        <form method="GET" action="/search.php" class="search-form" role="search">
            <input
                type="search"
                name="q"
                class="search-input"
                placeholder="Search service, employee, or ID..."
                autocomplete="off"
                spellcheck="false"
                aria-label="Search credentials"
                required
            >
            <button type="submit" class="search-button" title="Search credentials" aria-label="Search">
                <?= dashboardIcon('search') ?>
            </button>
        </form>

        <div class="user-profile">
            <div class="avatar"><?= htmlspecialchars($initial) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="user-role"><?= htmlspecialchars($roleLabel) ?></div>
            </div>
        </div>
    </header>

    <div class="content">
        <?php if (isset($_GET['access_denied'])): ?>
            <div class="alert alert-warning">
                Access denied. You do not have permission to access that feature.
            </div>
        <?php endif; ?>

        <section class="welcome-banner">
            <div class="welcome-content">
                <h1>Welcome back, <?= htmlspecialchars($fullName) ?></h1>
                <p>Manage your organization credentials securely and efficiently from one central workspace.</p>
                <div class="role-badge">
                    <?= dashboardIcon('shield') ?>
                    Logged in as <?= htmlspecialchars($roleLabel) ?>
                </div>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total Credentials</span>
                    <div class="stat-icon"><?= dashboardIcon('key') ?></div>
                </div>
                <div class="stat-number"><?= $credentialCount ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Departments</span>
                    <div class="stat-icon"><?= dashboardIcon('departments') ?></div>
                </div>
                <div class="stat-number"><?= $departmentCount ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Employees</span>
                    <div class="stat-icon"><?= dashboardIcon('employee') ?></div>
                </div>
                <div class="stat-number"><?= $employeeCount ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Your Role</span>
                    <div class="stat-icon"><?= dashboardIcon('shield') ?></div>
                </div>
                <div class="stat-number"><?= htmlspecialchars($roleLabel) ?></div>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Recent Credentials</div>
                    <a href="/credentials.php" class="view-all">View All →</a>
                </div>

                <?php if (empty($recentCredentials)): ?>
                    <div class="empty-state">No credentials have been added yet.</div>
                <?php else: ?>
                    <?php foreach ($recentCredentials as $credential): ?>
                        <div class="credential-item">
                            <div class="service-info">
                                <div class="service-icon"><?= dashboardIcon('key') ?></div>
                                <div>
                                    <div class="service-name">
                                        <?= htmlspecialchars((string) $credential['service_name']) ?>
                                    </div>
                                    <div class="service-meta">
                                        Added by <?= htmlspecialchars((string) ($credential['created_by_name'] ?? 'Unknown')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="service-meta">
                                <?= htmlspecialchars(date('d M Y', strtotime((string) $credential['created_at']))) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Quick Actions</div>
                </div>

                <div class="quick-actions">
                    <a href="/credentials.php" class="action-button">
                        <?= dashboardIcon('credentials') ?>
                        <span>View All Credentials</span>
                    </a>

                    <a href="/departments.php" class="action-button">
                        <?= dashboardIcon('departments') ?>
                        <span>Browse Departments</span>
                    </a>

                    <?php if (canAddCredentials()): ?>
                        <a href="/departments.php" class="action-button">
                            <?= dashboardIcon('plus') ?>
                            <span>Add New Credential</span>
                        </a>
                    <?php endif; ?>

                    <?php if (canManageUsers()): ?>
                        <a href="/manage-users.php" class="action-button">
                            <?= dashboardIcon('users') ?>
                            <span>Manage Users</span>
                        </a>
                    <?php endif; ?>

                    <?php if (canManageDepartments()): ?>
                        <a href="/manage-departments.php" class="action-button">
                            <?= dashboardIcon('settings') ?>
                            <span>Manage Departments</span>
                        </a>
                    <?php endif; ?>

                    <?php if (isAdmin()): ?>
                        <a href="/audit-logs.php" class="action-button">
                            <?= dashboardIcon('audit') ?>
                            <span>Audit Logs</span>
                        </a>
                    <?php endif; ?>

                    <?php if (function_exists('canManageRoot') && canManageRoot()): ?>
                        <a href="/root-management.php" class="action-button">
                            <?= dashboardIcon('root') ?>
                            <span>Root Management</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<script src="/assets/theme.js"></script>
</body>
</html>
