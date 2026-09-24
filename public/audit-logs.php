<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Audit Log Permission
|--------------------------------------------------------------------------
|
| Only Root and Admin can access audit logs.
|
*/

if (!isAdmin()) {

    header('Location: /dashboard.php?access_denied=audit_logs');

    exit;
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$actionFilter =
    isset($_GET['action'])
        ? trim((string) $_GET['action'])
        : '';

$userFilter =
    isset($_GET['user_id'])
        ? (int) $_GET['user_id']
        : 0;

$search =
    isset($_GET['search'])
        ? trim((string) $_GET['search'])
        : '';


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 50;

$page =
    isset($_GET['page'])
        ? max(1, (int) $_GET['page'])
        : 1;

$offset =
    ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Available Users
|--------------------------------------------------------------------------
*/

$userStmt = $pdo->query(
    "
    SELECT
        id,
        full_name,
        username,
        role
    FROM users
    ORDER BY full_name ASC
    "
);

$users =
    $userStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Available Actions
|--------------------------------------------------------------------------
*/

$actionStmt = $pdo->query(
    "
    SELECT DISTINCT action
    FROM activity_logs
    WHERE action IS NOT NULL
      AND action <> ''
    ORDER BY action ASC
    "
);

$actions =
    $actionStmt->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| Build WHERE Conditions
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


/*
|--------------------------------------------------------------------------
| Action Filter
|--------------------------------------------------------------------------
*/

if ($actionFilter !== '') {

    $where[] =
        'al.action = :action';

    $params[':action'] =
        $actionFilter;
}


/*
|--------------------------------------------------------------------------
| User Filter
|--------------------------------------------------------------------------
*/

if ($userFilter > 0) {

    $where[] =
        'al.user_id = :user_id';

    $params[':user_id'] =
        $userFilter;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
|
| Search:
|
| - User full name
| - Username
| - Action
| - Description
|
| Password itself is NEVER searched.
|
*/

if ($search !== '') {

    $where[] =
        "
        (
            u.full_name LIKE :search_full_name
            OR u.username LIKE :search_username
            OR al.action LIKE :search_action
            OR al.description LIKE :search_description
        )
        ";

    $searchValue =
        '%' . $search . '%';

    $params[':search_full_name'] =
        $searchValue;

    $params[':search_username'] =
        $searchValue;

    $params[':search_action'] =
        $searchValue;

    $params[':search_description'] =
        $searchValue;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| Count Logs
|--------------------------------------------------------------------------
*/

$countSql =
    "
    SELECT COUNT(*)

    FROM activity_logs al

    LEFT JOIN users u
        ON al.user_id = u.id

    {$whereSql}
    ";


$countStmt =
    $pdo->prepare($countSql);


$countStmt->execute($params);


$totalLogs =
    (int) $countStmt->fetchColumn();


$totalPages =
    max(
        1,
        (int) ceil(
            $totalLogs / $perPage
        )
    );


/*
|--------------------------------------------------------------------------
| Prevent Invalid Page
|--------------------------------------------------------------------------
*/

if ($page > $totalPages) {

    $page =
        $totalPages;

    $offset =
        ($page - 1) * $perPage;
}


/*
|--------------------------------------------------------------------------
| Fetch Logs
|--------------------------------------------------------------------------
*/

$sql =
    "
    SELECT

        al.id,

        al.user_id,

        al.action,

        al.description,

        al.created_at,

        u.full_name,

        u.username,

        u.role

    FROM activity_logs al

    LEFT JOIN users u
        ON al.user_id = u.id

    {$whereSql}

    ORDER BY al.created_at DESC, al.id DESC

    LIMIT :limit
    OFFSET :offset
    ";


$stmt =
    $pdo->prepare($sql);


/*
|--------------------------------------------------------------------------
| Bind Normal Parameters
|--------------------------------------------------------------------------
*/

foreach (
    $params
    as $key => $value
) {

    if ($key === ':user_id') {

        $stmt->bindValue(
            $key,
            (int) $value,
            PDO::PARAM_INT
        );

    } else {

        $stmt->bindValue(
            $key,
            (string) $value,
            PDO::PARAM_STR
        );

    }
}


/*
|--------------------------------------------------------------------------
| Bind Pagination
|--------------------------------------------------------------------------
*/

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);


$stmt->execute();


$logs =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Action Display
|--------------------------------------------------------------------------
*/

function actionClass(string $action): string
{
    $actionLower =
        strtolower($action);


    if (
        str_contains(
            $actionLower,
            'failed'
        )
        ||
        str_contains(
            $actionLower,
            'denied'
        )
    ) {

        return 'danger';

    }


    if (
        str_contains(
            $actionLower,
            'view'
        )
    ) {

        return 'view';

    }


    if (
        str_contains(
            $actionLower,
            'login'
        )
        ||
        str_contains(
            $actionLower,
            'logout'
        )
    ) {

        return 'auth';

    }


    if (
        str_contains(
            $actionLower,
            'delete'
        )
        ||
        str_contains(
            $actionLower,
            'reset'
        )
    ) {

        return 'warning';

    }


    if (
        str_contains(
            $actionLower,
            'created'
        )
        ||
        str_contains(
            $actionLower,
            'added'
        )
        ||
        str_contains(
            $actionLower,
            'enabled'
        )
    ) {

        return 'success';

    }


    return 'normal';
}


/*
|--------------------------------------------------------------------------
| Build Pagination URL
|--------------------------------------------------------------------------
*/

function pageUrl(
    int $pageNumber
): string {

    $query =
        $_GET;

    $query['page'] =
        $pageNumber;


    return
        '?' .
        http_build_query($query);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="referrer"
    content="no-referrer"
>

<title>
    Audit Logs - Credential Manager
</title>


<style>

/*
|--------------------------------------------------------------------------
| Base
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #333;

}


/*
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
*/

.navbar {

    background: #2b2b2b;

    color: #fff;

    padding: 16px 35px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

}


.navbar-title {

    font-size: 20px;

    font-weight: bold;

    white-space: nowrap;

}


.navbar-links {

    display: flex;

    align-items: center;

    gap: 20px;

    flex-wrap: wrap;

}


.navbar-links a {

    color: #fff;

    text-decoration: none;

    font-size: 14px;

}


.navbar-links a:hover {

    text-decoration: underline;

}


/*
|--------------------------------------------------------------------------
| Container
|--------------------------------------------------------------------------
*/

.container {

    max-width: 1500px;

    margin: 0 auto;

    padding: 35px;

}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;

    flex-wrap: wrap;

}


.page-header h1 {

    margin: 0 0 7px 0;

}


.page-header p {

    margin: 0;

    color: #666;

}


/*
|--------------------------------------------------------------------------
| Security Notice
|--------------------------------------------------------------------------
*/

.security-notice {

    background: #e8f1ff;

    border-left: 4px solid #2463eb;

    padding: 15px 18px;

    margin-bottom: 22px;

    border-radius: 5px;

    line-height: 1.5;

}


.security-notice strong {

    color: #174ea6;

}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.stats {

    display: flex;

    gap: 15px;

    margin-bottom: 22px;

    flex-wrap: wrap;

}


.stat-card {

    background: #fff;

    border: 1px solid #ddd;

    border-radius: 7px;

    padding: 17px 22px;

    min-width: 180px;

}


.stat-title {

    font-size: 13px;

    color: #777;

    margin-bottom: 6px;

}


.stat-value {

    font-size: 24px;

    font-weight: bold;

}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

.filter-box {

    background: #fff;

    border: 1px solid #ddd;

    border-radius: 8px;

    padding: 20px;

    margin-bottom: 22px;

}


.filter-grid {

    display: grid;

    grid-template-columns:
        1.5fr
        1fr
        1fr
        auto
        auto;

    gap: 12px;

    align-items: end;

}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 6px;

}


.form-group label {

    font-size: 13px;

    font-weight: bold;

}


.form-group input,
.form-group select {

    width: 100%;

    padding: 10px 12px;

    border: 1px solid #bbb;

    border-radius: 5px;

    background: #fff;

    font-size: 14px;

}


.form-group input:focus,
.form-group select:focus {

    outline: none;

    border-color: #555;

}


.button {

    display: inline-block;

    padding: 10px 16px;

    border: none;

    border-radius: 5px;

    background: #333;

    color: #fff;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

}


.button:hover {

    background: #555;

}


.clear-button {

    background: #777;

}


.clear-button:hover {

    background: #555;

}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-wrapper {

    background: #fff;

    border: 1px solid #ddd;

    border-radius: 8px;

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 1000px;

    border-collapse: collapse;

}


th,
td {

    padding: 13px 15px;

    border-bottom: 1px solid #ddd;

    text-align: left;

    vertical-align: top;

}


th {

    background: #eee;

    font-size: 13px;

    white-space: nowrap;

}


tbody tr:hover {

    background: #fafafa;

}


tbody tr:last-child td {

    border-bottom: none;

}


/*
|--------------------------------------------------------------------------
| User
|--------------------------------------------------------------------------
*/

.user-name {

    font-weight: bold;

}


.username {

    color: #777;

    font-size: 12px;

    margin-top: 3px;

}


/*
|--------------------------------------------------------------------------
| Role
|--------------------------------------------------------------------------
*/

.role {

    display: inline-block;

    padding: 4px 8px;

    border-radius: 12px;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;

}


.role-root {

    background: #eadcff;

    color: #5d249b;

}


.role-admin {

    background: #ffe4e4;

    color: #9b1c1c;

}


.role-editor {

    background: #e4f0ff;

    color: #1c579b;

}


.role-viewer {

    background: #e7f6e7;

    color: #286b28;

}


.role-unknown {

    background: #eee;

    color: #555;

}


/*
|--------------------------------------------------------------------------
| Action
|--------------------------------------------------------------------------
*/

.action {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 5px;

    font-size: 11px;

    font-weight: bold;

    text-transform: uppercase;

}


.action-view {

    background: #e7f0ff;

    color: #2457a6;

}


.action-danger {

    background: #ffe5e5;

    color: #a31b1b;

}


.action-warning {

    background: #fff1d6;

    color: #8a5700;

}


.action-auth {

    background: #e8e8e8;

    color: #555;

}


.action-success {

    background: #e1f5e4;

    color: #23652c;

}


.action-normal {

    background: #eee;

    color: #444;

}


/*
|--------------------------------------------------------------------------
| Description
|--------------------------------------------------------------------------
*/

.description {

    max-width: 500px;

    word-break: break-word;

    line-height: 1.45;

}


/*
|--------------------------------------------------------------------------
| Date
|--------------------------------------------------------------------------
*/

.date {

    white-space: nowrap;

    font-size: 13px;

    color: #555;

}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    background: #fff;

    padding: 45px;

    text-align: center;

    border: 1px solid #ddd;

    border-radius: 8px;

    color: #666;

}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

.pagination {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 6px;

    margin-top: 25px;

    flex-wrap: wrap;

}


.pagination a,
.pagination span {

    display: inline-block;

    padding: 8px 12px;

    border: 1px solid #ccc;

    border-radius: 5px;

    text-decoration: none;

    color: #333;

    background: #fff;

    font-size: 13px;

}


.pagination a:hover {

    background: #eee;

}


.pagination .current {

    background: #333;

    color: #fff;

    border-color: #333;

}


.pagination .disabled {

    color: #aaa;

    background: #f5f5f5;

}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .filter-grid {

        grid-template-columns: 1fr 1fr;

    }

}


@media (max-width: 700px) {

    .navbar {

        padding: 18px 20px;

        flex-direction: column;

        align-items: flex-start;

    }


    .navbar-links {

        gap: 14px;

    }


    .container {

        padding: 20px;

    }


    .filter-grid {

        grid-template-columns: 1fr;

    }

}

</style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>


<body>


<!--
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
-->

<div class="navbar">


    <div class="navbar-title">

        Credential Manager

    </div>


    <div class="navbar-links">


        <a href="/dashboard.php">
            Dashboard
        </a>


        <a href="/departments.php">
            Departments
        </a>


        <a href="/credentials.php">
            Credentials
        </a>


        <?php if (canManageUsers()): ?>

            <a href="/manage-users.php">
                Manage Users
            </a>

        <?php endif; ?>


        <a href="/audit-logs.php">
            Audit Logs
        </a>


        <?php if (
            function_exists('canManageRoot')
            &&
            canManageRoot()
        ): ?>

            <a href="/root-management.php">
                Root Management
            </a>

        <?php endif; ?>


        <a href="/logout.php">
            Logout
        </a>


    </div>

</div>


<!--
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
-->

<div class="container">


    <div class="page-header">


        <div>

            <h1>
                Audit Logs
            </h1>

            <p>
                Monitor security and user activity.
            </p>

        </div>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | Security Notice
    |--------------------------------------------------------------------------
    -->

    <div class="security-notice">

        <strong>
            Security Audit:
        </strong>

        Password viewing events are recorded here,
        but actual passwords and TOTP codes are never
        stored in the audit log.

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">
                Matching Logs
            </div>

            <div class="stat-value">

                <?php
                echo number_format(
                    $totalLogs
                );
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Page
            </div>

            <div class="stat-value">

                <?php
                echo $page;
                ?>

                /
                <?php
                echo $totalPages;
                ?>

            </div>

        </div>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    -->

    <div class="filter-box">


        <form
            method="GET"
            action="/audit-logs.php"
        >


            <div class="filter-grid">


                <!-- Search -->

                <div class="form-group">


                    <label for="search">

                        Search

                    </label>


                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?php
                            echo e($search);
                        ?>"
                        placeholder="User, action, description..."
                        autocomplete="off"
                    >


                </div>


                <!-- User -->

                <div class="form-group">


                    <label for="user_id">

                        User

                    </label>


                    <select
                        id="user_id"
                        name="user_id"
                    >


                        <option value="0">

                            All Users

                        </option>


                        <?php foreach (
                            $users
                            as $user
                        ): ?>


                            <option
                                value="<?php
                                    echo (int)
                                        $user['id'];
                                ?>"
                                <?php
                                echo $userFilter ===
                                    (int) $user['id']
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo e(
                                    $user['full_name']
                                );
                                ?>

                                —

                                <?php
                                echo e(
                                    $user['username']
                                );
                                ?>


                            </option>


                        <?php endforeach; ?>


                    </select>


                </div>


                <!-- Action -->

                <div class="form-group">


                    <label for="action">

                        Action

                    </label>


                    <select
                        id="action"
                        name="action"
                    >


                        <option value="">

                            All Actions

                        </option>


                        <?php foreach (
                            $actions
                            as $action
                        ): ?>


                            <option
                                value="<?php
                                    echo e(
                                        (string)
                                        $action
                                    );
                                ?>"
                                <?php
                                echo $actionFilter ===
                                    (string) $action
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo e(
                                    strtoupper(
                                        (string)
                                        $action
                                    )
                                );
                                ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                </div>


                <!-- Apply -->

                <div class="form-group">


                    <label>
                        &nbsp;
                    </label>


                    <button
                        type="submit"
                        class="button"
                    >
                        Apply
                    </button>


                </div>


                <!-- Clear -->

                <div class="form-group">


                    <label>
                        &nbsp;
                    </label>


                    <a
                        href="/audit-logs.php"
                        class="button clear-button"
                    >
                        Clear
                    </a>


                </div>


            </div>


        </form>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    -->

    <?php if (
        empty($logs)
    ): ?>


        <div class="empty">

            <h3>
                No audit logs found.
            </h3>


            <?php if (
                $search !== ''
                ||
                $actionFilter !== ''
                ||
                $userFilter > 0
            ): ?>

                <p>

                    Try changing or clearing
                    the filters.

                </p>

            <?php else: ?>

                <p>

                    Activity will appear here
                    as users interact with
                    the Credential Manager.

                </p>

            <?php endif; ?>


        </div>


    <?php else: ?>


        <div class="table-wrapper">


            <table>


                <thead>


                    <tr>


                        <th>
                            Date & Time
                        </th>


                        <th>
                            User
                        </th>


                        <th>
                            Role
                        </th>


                        <th>
                            Action
                        </th>


                        <th>
                            Description
                        </th>


                    </tr>


                </thead>


                <tbody>


                    <?php foreach (
                        $logs
                        as $log
                    ): ?>


                        <?php

                        $role =
                            strtolower(
                                (string)
                                (
                                    $log['role']
                                    ?? ''
                                )
                            );


                        $roleClass =
                            in_array(
                                $role,
                                [
                                    'root',
                                    'admin',
                                    'editor',
                                    'viewer'
                                ],
                                true
                            )
                                ? 'role-' . $role
                                : 'role-unknown';


                        $action =
                            (string)
                            (
                                $log['action']
                                ?? ''
                            );


                        $actionType =
                            actionClass(
                                $action
                            );

                        ?>


                        <tr>


                            <!-- Date -->

                            <td class="date">


                                <?php
                                echo e(
                                    $log[
                                        'created_at'
                                    ]
                                    ?? ''
                                );
                                ?>


                            </td>


                            <!-- User -->

                            <td>


                                <?php if (
                                    !empty(
                                        $log['full_name']
                                    )
                                ): ?>


                                    <div
                                        class="user-name"
                                    >

                                        <?php
                                        echo e(
                                            $log[
                                                'full_name'
                                            ]
                                        );
                                        ?>

                                    </div>


                                    <div
                                        class="username"
                                    >

                                        @<?php
                                        echo e(
                                            $log[
                                                'username'
                                            ]
                                        );
                                        ?>

                                    </div>


                                <?php else: ?>


                                    <div
                                        class="user-name"
                                    >

                                        System

                                    </div>


                                <?php endif; ?>


                            </td>


                            <!-- Role -->

                            <td>


                                <?php if (
                                    !empty($log['role'])
                                ): ?>


                                    <span
                                        class="role <?php
                                            echo e(
                                                $roleClass
                                            );
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            $log['role']
                                        );
                                        ?>

                                    </span>


                                <?php else: ?>


                                    —

                                <?php endif; ?>


                            </td>


                            <!-- Action -->

                            <td>


                                <span
                                    class="action action-<?php
                                        echo e(
                                            $actionType
                                        );
                                    ?>"
                                >

                                    <?php
                                    echo e(
                                        $action
                                    );
                                    ?>

                                </span>


                            </td>


                            <!-- Description -->

                            <td
                                class="description"
                            >

                                <?php
                                echo e(
                                    $log[
                                        'description'
                                    ]
                                    ?? ''
                                );
                                ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    -->

    <?php if (
        $totalPages > 1
    ): ?>


        <div class="pagination">


            <?php if (
                $page > 1
            ): ?>


                <a
                    href="<?php
                        echo e(
                            pageUrl(
                                $page - 1
                            )
                        );
                    ?>"
                >
                    ← Previous
                </a>


            <?php else: ?>


                <span class="disabled">
                    ← Previous
                </span>


            <?php endif; ?>


            <?php

            $startPage =
                max(
                    1,
                    $page - 2
                );


            $endPage =
                min(
                    $totalPages,
                    $page + 2
                );

            ?>


            <?php if (
                $startPage > 1
            ): ?>


                <a
                    href="<?php
                        echo e(
                            pageUrl(1)
                        );
                    ?>"
                >
                    1
                </a>


                <?php if (
                    $startPage > 2
                ): ?>

                    <span>
                        ...
                    </span>

                <?php endif; ?>


            <?php endif; ?>


            <?php for (
                $i = $startPage;
                $i <= $endPage;
                $i++
            ): ?>


                <?php if (
                    $i === $page
                ): ?>


                    <span class="current">

                        <?php
                        echo $i;
                        ?>

                    </span>


                <?php else: ?>


                    <a
                        href="<?php
                            echo e(
                                pageUrl($i)
                            );
                        ?>"
                    >

                        <?php
                        echo $i;
                        ?>

                    </a>


                <?php endif; ?>


            <?php endfor; ?>


            <?php if (
                $endPage < $totalPages
            ): ?>


                <?php if (
                    $endPage < $totalPages - 1
                ): ?>

                    <span>
                        ...
                    </span>

                <?php endif; ?>


                <a
                    href="<?php
                        echo e(
                            pageUrl(
                                $totalPages
                            )
                        );
                    ?>"
                >

                    <?php
                    echo $totalPages;
                    ?>

                </a>


            <?php endif; ?>


            <?php if (
                $page < $totalPages
            ): ?>


                <a
                    href="<?php
                        echo e(
                            pageUrl(
                                $page + 1
                            )
                        );
                    ?>"
                >
                    Next →
                </a>


            <?php else: ?>


                <span class="disabled">
                    Next →
                </span>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


    <script src="/assets/theme.js"></script>

</body>

</html>
