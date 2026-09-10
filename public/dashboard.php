<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$userId = $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get User Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        full_name,
        username,
        email,
        role
    FROM users
    WHERE id = :id"
);

$stmt->execute([
    ':id' => $userId
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    session_destroy();

    header('Location: /login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$credentialCount = 0;
$departmentCount = 0;
$employeeCount = 0;

try {

    $credentialCount = $pdo
        ->query("SELECT COUNT(*) FROM credentials")
        ->fetchColumn();

    $departmentCount = $pdo
        ->query("SELECT COUNT(*) FROM departments")
        ->fetchColumn();

    $employeeCount = $pdo
        ->query("SELECT COUNT(*) FROM employees")
        ->fetchColumn();

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Keep dashboard working even if a table query fails
    |--------------------------------------------------------------------------
    */

}


/*
|--------------------------------------------------------------------------
| Recent Credentials
|--------------------------------------------------------------------------
*/

$recentCredentials = [];

try {

    /*
    |--------------------------------------------------------------------------
    | This query assumes credentials are connected to employees
    |--------------------------------------------------------------------------
    */

    $recentStmt = $pdo->query(
        "SELECT
            credentials.id,
            credentials.service_name,
            credentials.created_at,
            users.full_name AS created_by_name
        FROM credentials

        LEFT JOIN users
            ON credentials.created_by = users.id

        ORDER BY credentials.created_at DESC

        LIMIT 5"
    );

    $recentCredentials =
        $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $recentCredentials = [];

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Dashboard - Credential Manager</title>


<style>

/*
|--------------------------------------------------------------------------
| Global
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background: #f5f7fb;

    color: #1f2937;
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 260px;

    height: 100vh;

    background: #1e293b;

    color: white;

    display: flex;

    flex-direction: column;

    padding: 25px 18px;

    z-index: 1000;

}


.logo {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 10px 12px;

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 40px;

}


.logo-icon {

    width: 42px;
    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #2563eb;

    border-radius: 10px;

}


.nav-section {

    font-size: 11px;

    text-transform: uppercase;

    color: #94a3b8;

    margin:

        18px 12px
        8px;

    letter-spacing: 1px;

}


.nav-link {

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 13px 14px;

    margin-bottom: 6px;

    border-radius: 8px;

    color: #cbd5e1;

    text-decoration: none;

    transition: 0.2s;

}


.nav-link:hover {

    background: #334155;

    color: white;

}


.nav-link.active {

    background: #2563eb;

    color: white;

}


.nav-icon {

    width: 20px;

    text-align: center;

}


.sidebar-bottom {

    margin-top: auto;

}


.logout-link {

    background:
        rgba(
            239,
            68,
            68,
            0.1
        );

    color: #fca5a5;

}


.logout-link:hover {

    background: #dc2626;

    color: white;

}


/*
|--------------------------------------------------------------------------
| Main Layout
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 260px;

    min-height: 100vh;

}


.topbar {

    height: 80px;

    background: white;

    border-bottom:

        1px solid
        #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 25px;

    padding: 0 40px;

}


.page-title {

    font-size: 22px;

    font-weight: 700;

    white-space: nowrap;

}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

.search-form {

    width: 100%;

    max-width: 550px;

    position: relative;

}


.search-input {

    width: 100%;

    padding:

        13px 45px
        13px 18px;

    border:

        1px solid
        #dbe1ea;

    border-radius: 10px;

    outline: none;

    font-size: 14px;

    background: #f8fafc;

}


.search-input:focus {

    background: white;

    border-color: #2563eb;

}


.search-button {

    position: absolute;

    right: 5px;
    top: 5px;

    height: 38px;

    border: none;

    background: transparent;

    cursor: pointer;

    font-size: 18px;

}


.user-profile {

    display: flex;

    align-items: center;

    gap: 12px;

    white-space: nowrap;

}


.avatar {

    width: 42px;
    height: 42px;

    border-radius: 50%;

    background: #dbeafe;

    color: #2563eb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    font-size: 18px;

}


.user-name {

    font-weight: 600;

}


.user-role {

    font-size: 13px;

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| Content
|--------------------------------------------------------------------------
*/

.content {

    padding: 35px 40px;

    max-width: 1500px;

}


.welcome-banner {

    background:

        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    border-radius: 16px;

    padding: 35px;

    color: white;

    margin-bottom: 30px;

}


.welcome-banner h1 {

    margin: 0 0 10px;

    font-size: 30px;

}


.welcome-banner p {

    margin: 0;

    opacity: 0.9;

}


.role-badge {

    display: inline-block;

    margin-top: 18px;

    background:
        rgba(
            255,
            255,
            255,
            0.2
        );

    padding: 7px 14px;

    border-radius: 20px;

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| Alert
|--------------------------------------------------------------------------
*/

.alert {

    padding: 16px 20px;

    border-radius: 10px;

    margin-bottom: 25px;

}


.alert-warning {

    background: #fff7ed;

    color: #9a3412;

    border:

        1px solid
        #fed7aa;

}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.stats-grid {

    display: grid;

    grid-template-columns:

        repeat(
            auto-fit,
            minmax(220px, 1fr)
        );

    gap: 20px;

    margin-bottom: 30px;

}


.stat-card {

    background: white;

    border-radius: 14px;

    padding: 24px;

    border:

        1px solid
        #edf0f5;

    box-shadow:

        0 4px 15px
        rgba(
            0,
            0,
            0,
            0.04
        );

}


.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.stat-label {

    color: #64748b;

    font-size: 14px;

}


.stat-number {

    font-size: 32px;

    font-weight: 700;

    margin-top: 15px;

}


.stat-icon {

    width: 45px;
    height: 45px;

    border-radius: 12px;

    background: #eff6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

}


/*
|--------------------------------------------------------------------------
| Dashboard Grid
|--------------------------------------------------------------------------
*/

.dashboard-grid {

    display: grid;

    grid-template-columns:

        2fr 1fr;

    gap: 25px;

}


.panel {

    background: white;

    border-radius: 14px;

    border:

        1px solid
        #edf0f5;

    box-shadow:

        0 4px 15px
        rgba(
            0,
            0,
            0,
            0.04
        );

}


.panel-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 22px 25px;

    border-bottom:

        1px solid
        #eef1f5;

}


.panel-title {

    font-size: 17px;

    font-weight: 600;

}


.view-all {

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| Credential List
|--------------------------------------------------------------------------
*/

.credential-item {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 18px 25px;

    border-bottom:

        1px solid
        #f1f5f9;

}


.service-info {

    display: flex;

    align-items: center;

    gap: 14px;

}


.service-icon {

    width: 42px;
    height: 42px;

    border-radius: 10px;

    background: #f1f5f9;

    display: flex;

    align-items: center;

    justify-content: center;

}


.service-name {

    font-weight: 600;

}


.service-meta {

    font-size: 13px;

    color: #64748b;

    margin-top: 4px;

}


.empty-state {

    padding: 40px;

    text-align: center;

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| Quick Actions
|--------------------------------------------------------------------------
*/

.quick-actions {

    padding: 20px;

    display: flex;

    flex-direction: column;

    gap: 12px;

}


.action-button {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 15px;

    border-radius: 10px;

    text-decoration: none;

    color: #334155;

    background: #f8fafc;

    transition: 0.2s;

}


.action-button:hover {

    background: #eff6ff;

    color: #2563eb;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .dashboard-grid {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 850px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;

    }


    .logo-text,
    .nav-text,
    .nav-section {

        display: none;

    }


    .logo {

        justify-content: center;

        padding: 0;

    }


    .nav-link {

        justify-content: center;

    }


    .main {

        margin-left: 70px;

    }


    .topbar {

        padding: 0 20px;

    }

}


@media (max-width: 650px) {

    .page-title,
    .user-details {

        display: none;

    }


    .content {

        padding: 25px 15px;

    }


    .welcome-banner {

        padding: 25px;

    }

}

</style>

</head>


<body>


<!-- Sidebar -->

<aside class="sidebar">


<div class="logo">

    <div class="logo-icon">
        🔐
    </div>

    <div class="logo-text">
        Credential Manager
    </div>

</div>


<div class="nav-section">
    Main Menu
</div>


<a
    href="/dashboard.php"
    class="nav-link active"
>

    <span class="nav-icon">🏠</span>

    <span class="nav-text">
        Dashboard
    </span>

</a>


<a
    href="/departments.php"
    class="nav-link"
>

    <span class="nav-icon">🏢</span>

    <span class="nav-text">
        Departments
    </span>

</a>


<a
    href="/credentials.php"
    class="nav-link"
>

    <span class="nav-icon">🔑</span>

    <span class="nav-text">
        Credentials
    </span>

</a>


<?php if (canManageUsers() || canManageDepartments()): ?>

<div class="nav-section">
    Administration
</div>

<?php endif; ?>


<?php if (canManageUsers()): ?>

<a
    href="/manage-users.php"
    class="nav-link"
>

    <span class="nav-icon">
        👥
    </span>

    <span class="nav-text">
        Manage Users
    </span>

</a>

<?php endif; ?>


<?php if (canManageDepartments()): ?>

<a
    href="/manage-departments.php"
    class="nav-link"
>

    <span class="nav-icon">
        ⚙️
    </span>

    <span class="nav-text">
        Manage Departments
    </span>

</a>

<?php endif; ?>


<div class="sidebar-bottom">

<a
    href="/logout.php"
    class="nav-link logout-link"
>

    <span class="nav-icon">
        🚪
    </span>

    <span class="nav-text">
        Logout
    </span>

</a>

</div>


</aside>


<!-- Main -->

<main class="main">


<!-- Topbar -->

<div class="topbar">


<div class="page-title">
    Dashboard
</div>


<!-- Search Form -->

<form
    method="GET"
    action="/search.php"
    class="search-form"
>

    <input
        type="text"
        name="q"
        class="search-input"
        placeholder="Search services, employees, departments..."
        required
    >

    <button
        type="submit"
        class="search-button"
        title="Search"
    >
        🔍
    </button>

</form>


<!-- User Profile -->

<div class="user-profile">


<div class="avatar">

<?php

echo htmlspecialchars(
    strtoupper(
        substr(
            $user['full_name'],
            0,
            1
        )
    )
);

?>

</div>


<div class="user-details">

<div class="user-name">

<?php
echo htmlspecialchars($user['full_name']);
?>

</div>


<div class="user-role">

<?php
echo htmlspecialchars(
    ucfirst($user['role'])
);
?>

</div>

</div>


</div>


</div>


<div class="content">


<?php if (isset($_GET['access_denied'])): ?>

<div class="alert alert-warning">

    ⚠️ Access denied. You do not have permission to access that feature.

</div>

<?php endif; ?>


<!-- Welcome -->

<section class="welcome-banner">

<h1>

Welcome back,

<?php
echo htmlspecialchars($user['full_name']);
?>

👋

</h1>


<p>
Manage your organization credentials securely and efficiently.
</p>


<div class="role-badge">

Logged in as

<?php
echo htmlspecialchars(
    ucfirst($user['role'])
);
?>

</div>


</section>


<!-- Statistics -->

<section class="stats-grid">


<div class="stat-card">

<div class="stat-top">

<span class="stat-label">
Total Credentials
</span>

<div class="stat-icon">
🔑
</div>

</div>


<div class="stat-number">
<?php echo $credentialCount; ?>
</div>

</div>


<div class="stat-card">

<div class="stat-top">

<span class="stat-label">
Departments
</span>

<div class="stat-icon">
🏢
</div>

</div>


<div class="stat-number">
<?php echo $departmentCount; ?>
</div>

</div>


<div class="stat-card">

<div class="stat-top">

<span class="stat-label">
Employees
</span>

<div class="stat-icon">
👨‍💼
</div>

</div>


<div class="stat-number">
<?php echo $employeeCount; ?>
</div>

</div>


<div class="stat-card">

<div class="stat-top">

<span class="stat-label">
Your Role
</span>

<div class="stat-icon">
👤
</div>

</div>


<div class="stat-number">

<?php
echo htmlspecialchars(
    ucfirst($user['role'])
);
?>

</div>

</div>


</section>


<!-- Bottom Grid -->

<section class="dashboard-grid">


<!-- Recent Credentials -->

<div class="panel">


<div class="panel-header">

<div class="panel-title">
Recent Credentials
</div>


<a
    href="/credentials.php"
    class="view-all"
>
View All →
</a>


</div>


<?php if (empty($recentCredentials)): ?>


<div class="empty-state">

No credentials have been added yet.

</div>


<?php else: ?>


<?php foreach ($recentCredentials as $credential): ?>


<div class="credential-item">


<div class="service-info">


<div class="service-icon">
🔑
</div>


<div>


<div class="service-name">

<?php
echo htmlspecialchars(
    $credential['service_name']
);
?>

</div>


<div class="service-meta">

Added by

<?php
echo htmlspecialchars(
    $credential['created_by_name']
    ?? 'Unknown'
);
?>

</div>


</div>


</div>


<div class="service-meta">

<?php

echo htmlspecialchars(
    date(
        'd M Y',
        strtotime(
            $credential['created_at']
        )
    )
);

?>

</div>


</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


<!-- Quick Actions -->

<div class="panel">


<div class="panel-header">

<div class="panel-title">
Quick Actions
</div>

</div>


<div class="quick-actions">


<a
    href="/credentials.php"
    class="action-button"
>

🔑

View All Credentials

</a>


<a
    href="/departments.php"
    class="action-button"
>

🏢

Browse Departments

</a>


<?php if (canAddCredentials()): ?>


<a
    href="/departments.php"
    class="action-button"
>

➕

Add New Credential

</a>


<?php endif; ?>


<?php if (canManageUsers()): ?>


<a
    href="/manage-users.php"
    class="action-button"
>

👥

Manage Users

</a>


<?php endif; ?>


<?php if (canManageDepartments()): ?>


<a
    href="/manage-departments.php"
    class="action-button"
>

⚙️

Manage Departments

</a>


<?php endif; ?>


</div>


</div>


</section>


</div>


</main>


</body>

</html>