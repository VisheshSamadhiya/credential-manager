<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

if (!canViewCredentials()) {
    header('Location: /dashboard.php?access_denied=view');
    exit;
}


/*
|--------------------------------------------------------------------------
| Employee Filter
|--------------------------------------------------------------------------
*/

$employeeId = isset($_GET['employee_id'])
    ? (int) $_GET['employee_id']
    : 0;

$employee = null;

if ($employeeId > 0) {

    $employeeStmt = $pdo->prepare(
        "SELECT
            e.id,
            e.employee_name,
            e.employee_id,
            e.department_id,
            d.department_name
         FROM employees e
         LEFT JOIN departments d
            ON e.department_id = d.id
         WHERE e.id = :employee_id
         LIMIT 1"
    );

    $employeeStmt->execute([
        ':employee_id' => $employeeId
    ]);

    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header('Location: /departments.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Credentials
|--------------------------------------------------------------------------
|
| IMPORTANT:
| credential_password is deliberately NOT selected here.
|
| Passwords are retrieved only when the user explicitly clicks
| the Show Password button.
|
*/

if ($employeeId > 0) {

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.employee_id,
            c.service_name,
            c.service_url,
            c.credential_username,
            c.notes,
            c.change_comment,
            c.is_new_version,
            c.created_at,
            c.updated_at,

            e.employee_name,
            e.employee_id AS employee_code,

            d.department_name,

            u.full_name AS created_by_name,
            u.role AS created_by_role

         FROM credentials c

         LEFT JOIN employees e
            ON c.employee_id = e.id

         LEFT JOIN departments d
            ON e.department_id = d.id

         LEFT JOIN users u
            ON c.created_by = u.id

         WHERE c.employee_id = :employee_id

         ORDER BY c.id DESC"
    );

    $stmt->execute([
        ':employee_id' => $employeeId
    ]);

} else {

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.employee_id,
            c.service_name,
            c.service_url,
            c.credential_username,
            c.notes,
            c.change_comment,
            c.is_new_version,
            c.created_at,
            c.updated_at,

            e.employee_name,
            e.employee_id AS employee_code,

            d.department_name,

            u.full_name AS created_by_name,
            u.role AS created_by_role

         FROM credentials c

         LEFT JOIN employees e
            ON c.employee_id = e.id

         LEFT JOIN departments d
            ON e.department_id = d.id

         LEFT JOIN users u
            ON c.created_by = u.id

         ORDER BY c.id DESC"
    );

    $stmt->execute();
}

$credentials = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$accessDenied = isset($_GET['access_denied']);
$updated      = isset($_GET['updated']);
$deleted      = isset($_GET['deleted']);
$added        = isset($_GET['added']);

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
        Credentials - Credential Manager
    </title>


    <style>

        :root {

            --purple-50: #faf5ff;
            --purple-100: #f3e8ff;
            --purple-200: #e9d5ff;
            --purple-300: #d8b4fe;
            --purple-400: #c084fc;
            --purple-500: #a855f7;
            --purple-600: #9333ea;
            --purple-700: #7e22ce;
            --purple-800: #6b21a8;
            --purple-900: #581c87;

            --pink-400: #f472b6;
            --pink-500: #ec4899;
            --pink-600: #db2777;

            --indigo-900: #1e1b4b;

            --text-main: #241b35;
            --text-muted: #766b84;

            --background: #f8f6fc;
            --card: #ffffff;

            --border: #eee7f5;

            --success-bg: #ecfdf5;
            --success-text: #047857;

            --warning-bg: #fffbeb;
            --warning-text: #b45309;

            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;

            --shadow-sm:
                0 2px 8px rgba(88, 28, 135, 0.05);

            --shadow-md:
                0 10px 30px rgba(88, 28, 135, 0.08);

            --gradient:
                linear-gradient(
                    135deg,
                    #7e22ce 0%,
                    #a855f7 48%,
                    #ec4899 100%
                );

            --sidebar-width: 260px;
        }


        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            background:
                radial-gradient(
                    circle at top right,
                    rgba(236, 72, 153, 0.08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at top left,
                    rgba(168, 85, 247, 0.08),
                    transparent 30%
                ),
                var(--background);

            color: var(--text-main);

            min-height: 100vh;
        }


        a {
            color: inherit;
        }


        button,
        input {
            font-family: inherit;
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
            bottom: 0;

            width: var(--sidebar-width);

            background:
                linear-gradient(
                    180deg,
                    #24113f 0%,
                    #32145d 45%,
                    #4c176f 100%
                );

            color: #fff;

            padding: 24px 16px;

            z-index: 100;

            overflow-y: auto;

            box-shadow:
                8px 0 30px rgba(48, 16, 73, 0.15);
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                4px
                12px
                28px;

            border-bottom:
                1px solid
                rgba(255, 255, 255, 0.10);

            margin-bottom: 24px;
        }


        .brand-icon {

            width: 42px;
            height: 42px;

            border-radius: 13px;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 21px;

            background:
                linear-gradient(
                    135deg,
                    #a855f7,
                    #ec4899
                );

            box-shadow:
                0 8px 20px
                rgba(236, 72, 153, 0.25);
        }


        .brand-text strong {

            display: block;

            font-size: 16px;

            letter-spacing: 0.1px;
        }


        .brand-text span {

            display: block;

            margin-top: 2px;

            font-size: 11px;

            color:
                rgba(255, 255, 255, 0.58);
        }


        .nav-section {

            margin-bottom: 26px;
        }


        .nav-title {

            padding:
                0
                12px
                9px;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1.2px;

            text-transform: uppercase;

            color:
                rgba(255, 255, 255, 0.40);
        }


        .nav-link {

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                11px
                12px;

            margin-bottom: 5px;

            border-radius: 11px;

            color:
                rgba(255, 255, 255, 0.72);

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;

            transition:
                0.2s ease;
        }


        .nav-link:hover {

            color: #fff;

            background:
                rgba(255, 255, 255, 0.08);

            transform:
                translateX(2px);
        }


        .nav-link.active {

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    rgba(168, 85, 247, 0.75),
                    rgba(236, 72, 153, 0.65)
                );

            box-shadow:
                0 7px 20px
                rgba(168, 85, 247, 0.22);
        }


        .nav-icon {

            width: 19px;

            text-align: center;

            font-size: 16px;
        }


        /*
        |--------------------------------------------------------------------------
        | Main
        |--------------------------------------------------------------------------
        */

        .main {

            margin-left: var(--sidebar-width);

            min-height: 100vh;
        }


        /*
        |--------------------------------------------------------------------------
        | Topbar
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 76px;

            padding:
                0 34px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            background:
                rgba(255, 255, 255, 0.88);

            backdrop-filter:
                blur(14px);

            border-bottom:
                1px solid
                var(--border);

            position: sticky;

            top: 0;

            z-index: 50;
        }


        .topbar-title {

            min-width: 0;
        }


        .topbar-title h1 {

            margin: 0;

            font-size: 21px;

            font-weight: 750;

            color:
                var(--text-main);
        }


        .topbar-title p {

            margin:
                4px
                0
                0;

            font-size: 12px;

            color:
                var(--text-muted);
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .top-search {

            position: relative;
        }


        .top-search input {

            width: 260px;

            height: 40px;

            border:
                1px solid
                var(--border);

            border-radius: 11px;

            padding:
                0
                13px;

            outline: none;

            background: #fff;

            color: var(--text-main);

            transition:
                0.2s ease;
        }


        .top-search input:focus {

            border-color:
                var(--purple-400);

            box-shadow:
                0 0 0 4px
                rgba(168, 85, 247, 0.10);
        }


        .user-pill {

            display: flex;

            align-items: center;

            gap: 9px;

            padding:
                5px
                10px
                5px
                5px;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            background: #fff;
        }


        .avatar {

            width: 31px;
            height: 31px;

            border-radius: 9px;

            display: flex;

            align-items: center;
            justify-content: center;

            color: #fff;

            font-size: 12px;

            font-weight: 700;

            background:
                var(--gradient);
        }


        .user-info {

            line-height: 1.2;
        }


        .user-name {

            font-size: 12px;

            font-weight: 700;
        }


        .user-role {

            margin-top: 2px;

            font-size: 10px;

            color:
                var(--text-muted);

            text-transform: capitalize;
        }


        /*
        |--------------------------------------------------------------------------
        | Content
        |--------------------------------------------------------------------------
        */

        .content {

            padding: 32px;
        }


        .page-heading {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 20px;

            margin-bottom: 25px;
        }


        .heading-left h2 {

            margin: 0;

            font-size: 27px;

            line-height: 1.2;

            font-weight: 800;

            background:
                linear-gradient(
                    90deg,
                    var(--purple-700),
                    var(--pink-600)
                );

            -webkit-background-clip: text;

            background-clip: text;

            color: transparent;
        }


        .heading-left p {

            margin:
                8px
                0
                0;

            color:
                var(--text-muted);

            font-size: 13px;
        }


        .header-actions {

            display: flex;

            align-items: center;

            gap: 9px;

            flex-wrap: wrap;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            min-height: 40px;

            padding:
                0
                14px;

            border: none;

            border-radius: 10px;

            text-decoration: none;

            cursor: pointer;

            font-size: 12px;

            font-weight: 650;

            color:
                var(--purple-700);

            background:
                var(--purple-50);

            border:
                1px solid
                var(--purple-200);

            transition:
                0.2s ease;
        }


        .button:hover {

            transform:
                translateY(-1px);

            border-color:
                var(--purple-300);

            box-shadow:
                var(--shadow-sm);
        }


        .add-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            min-height: 40px;

            padding:
                0
                16px;

            border-radius: 10px;

            text-decoration: none;

            color: #fff;

            background:
                var(--gradient);

            box-shadow:
                0 7px 18px
                rgba(168, 85, 247, 0.23);

            font-size: 12px;

            font-weight: 700;

            transition:
                0.2s ease;
        }


        .add-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 24px
                rgba(236, 72, 153, 0.25);
        }


        .delete-button {

            min-height: 36px;

            padding:
                0
                11px;

            border:
                1px solid
                #fecaca;

            border-radius: 8px;

            background:
                var(--danger-bg);

            color:
                var(--danger-text);

            cursor: pointer;

            font-size: 11px;

            font-weight: 650;

            transition:
                0.2s ease;
        }


        .delete-button:hover {

            background:
                #fee2e2;

            transform:
                translateY(-1px);
        }


        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */

        .message {

            display: flex;

            align-items: center;

            gap: 10px;

            padding:
                13px
                15px;

            margin-bottom: 18px;

            border-radius: 11px;

            font-size: 12px;

            font-weight: 600;

            border: 1px solid transparent;
        }


        .message.success {

            background:
                var(--success-bg);

            color:
                var(--success-text);

            border-color:
                #a7f3d0;
        }


        .message.warning {

            background:
                var(--warning-bg);

            color:
                var(--warning-text);

            border-color:
                #fde68a;
        }


        /*
        |--------------------------------------------------------------------------
        | Employee Card
        |--------------------------------------------------------------------------
        */

        .employee-card {

            position: relative;

            overflow: hidden;

            margin-bottom: 20px;

            padding: 20px;

            border-radius: 16px;

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    #581c87 0%,
                    #7e22ce 42%,
                    #db2777 100%
                );

            box-shadow:
                0 12px 30px
                rgba(126, 34, 206, 0.18);
        }


        .employee-card::after {

            content: "";

            position: absolute;

            width: 190px;
            height: 190px;

            right: -55px;
            top: -90px;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.10);
        }


        .employee-card h3 {

            position: relative;

            z-index: 1;

            margin:
                0
                0
                12px;

            font-size: 17px;
        }


        .employee-details {

            position: relative;

            z-index: 1;

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }


        .employee-detail {

            padding:
                7px
                11px;

            border-radius: 8px;

            background:
                rgba(255,255,255,0.11);

            font-size: 11px;

            color:
                rgba(255,255,255,0.88);
        }


        .employee-detail strong {

            color: #fff;
        }


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        .search-card {

            margin-bottom: 20px;

            padding: 18px;

            border:
                1px solid
                var(--border);

            border-radius: 15px;

            background:
                rgba(255,255,255,0.95);

            box-shadow:
                var(--shadow-sm);
        }


        .search-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            margin-bottom: 11px;
        }


        .search-label {

            font-size: 12px;

            font-weight: 750;

            color:
                var(--text-main);
        }


        .search-hint {

            font-size: 10px;

            color:
                var(--text-muted);
        }


        .search-row {

            display: flex;

            align-items: center;

            gap: 9px;
        }


        .search-input {

            flex: 1;

            min-width: 0;

            height: 43px;

            padding:
                0
                14px;

            border:
                1px solid
                var(--border);

            border-radius: 10px;

            outline: none;

            background: #fff;

            color:
                var(--text-main);

            font-size: 13px;

            transition:
                0.2s ease;
        }


        .search-input:focus {

            border-color:
                var(--purple-400);

            box-shadow:
                0 0 0 4px
                rgba(168, 85, 247, 0.09);
        }


        .clear-search {

            height: 43px;

            padding:
                0
                14px;

            border:
                1px solid
                var(--border);

            border-radius: 10px;

            background:
                #fff;

            color:
                var(--text-muted);

            cursor: pointer;

            font-size: 11px;

            font-weight: 650;

            transition:
                0.2s ease;
        }


        .clear-search:hover {

            border-color:
                var(--purple-300);

            color:
                var(--purple-700);
        }


        .search-result-count {

            margin-top: 9px;

            font-size: 10px;

            color:
                var(--text-muted);
        }


        /*
        |--------------------------------------------------------------------------
        | Table Card
        |--------------------------------------------------------------------------
        */

        .table-card {

            overflow: hidden;

            border:
                1px solid
                var(--border);

            border-radius: 16px;

            background:
                #fff;

            box-shadow:
                var(--shadow-md);
        }


        .table-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding:
                18px
                20px;

            border-bottom:
                1px solid
                var(--border);
        }


        .table-title {

            font-size: 14px;

            font-weight: 750;
        }


        .table-subtitle {

            margin-top: 3px;

            font-size: 10px;

            color:
                var(--text-muted);
        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            min-width: 1180px;

            border-collapse: collapse;
        }


        th,
        td {

            padding:
                14px
                15px;

            text-align: left;

            vertical-align: top;

            border-bottom:
                1px solid
                #f1edf5;
        }


        th {

            background:
                #fcfaff;

            color:
                #756a80;

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 0.65px;

            font-weight: 750;

            white-space: nowrap;
        }


        td {

            font-size: 12px;

            color:
                #43384d;
        }


        tbody tr {

            transition:
                0.15s ease;
        }


        tbody tr:hover {

            background:
                #fdfaff;
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        tbody tr.search-hidden {

            display: none;
        }


        /*
        |--------------------------------------------------------------------------
        | Service
        |--------------------------------------------------------------------------
        */

        .service-name {

            display: flex;

            align-items: center;

            gap: 9px;

            font-weight: 750;

            color:
                #35273e;
        }


        .service-icon {

            width: 31px;
            height: 31px;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 9px;

            color:
                var(--purple-700);

            background:
                var(--purple-100);

            font-size: 13px;
        }


        .version-comment {

            display: inline-block;

            margin-top: 7px;

            padding:
                3px
                7px;

            border-radius: 5px;

            color:
                var(--pink-600);

            background:
                #fce7f3;

            font-size: 9px;

            font-weight: 700;
        }


        .change-comment {

            max-width: 220px;

            margin-top: 6px;

            color:
                var(--text-muted);

            font-size: 10px;

            line-height: 1.4;

            word-break: break-word;
        }


        /*
        |--------------------------------------------------------------------------
        | Employee / Department
        |--------------------------------------------------------------------------
        */

        .employee-name {

            font-weight: 650;

            color:
                #3e3047;
        }


        .employee-id {

            display: inline-block;

            margin-top: 4px;

            color:
                var(--purple-600);

            font-size: 10px;

            font-weight: 650;
        }


        .department-badge {

            display: inline-flex;

            padding:
                5px
                8px;

            border-radius: 7px;

            color:
                #6b21a8;

            background:
                #f3e8ff;

            font-size: 10px;

            font-weight: 650;
        }


        /*
        |--------------------------------------------------------------------------
        | Username
        |--------------------------------------------------------------------------
        */

        .username {

            padding:
                6px
                8px;

            border-radius: 7px;

            background:
                #f8f6fa;

            color:
                #51445b;

            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                monospace;

            font-size: 10px;

            word-break: break-all;
        }


        /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */

        .password-field {

            min-width: 210px;
        }


        .password-actions {

            display: flex;

            align-items: center;

            gap: 8px;

        }


        .password {

            display: inline-flex;

            align-items: center;

            min-width: 105px;

            max-width: 210px;

            padding:
                7px
                9px;

            border-radius: 8px;

            background:
                #f7f3fb;

            color:
                #51445b;

            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                monospace;

            font-size: 11px;

            letter-spacing: 1px;

            word-break: break-all;

            user-select: none;

            -webkit-user-select: none;

            cursor: default;
        }


        .password.visible {

            background:
                #fff1f8;

            color:
                #9d174d;

            letter-spacing: 0;

        }


        .toggle-button {

            min-height: 32px;

            padding:
                0
                10px;

            border:
                1px solid
                var(--purple-200);

            border-radius: 8px;

            background:
                var(--purple-50);

            color:
                var(--purple-700);

            cursor: pointer;

            font-size: 10px;

            font-weight: 700;

            transition:
                0.2s ease;
        }


        .toggle-button:hover {

            background:
                var(--purple-100);

            border-color:
                var(--purple-300);
        }


        .toggle-button:disabled {

            opacity: 0.55;

            cursor: wait;
        }


        /*
        |--------------------------------------------------------------------------
        | URL
        |--------------------------------------------------------------------------
        */

        .url-link {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            color:
                var(--purple-600);

            text-decoration: none;

            font-size: 11px;

            font-weight: 650;
        }


        .url-link:hover {

            color:
                var(--pink-600);

            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | Notes
        |--------------------------------------------------------------------------
        */

        .notes-cell {

            max-width: 250px;

            color:
                var(--text-muted);

            line-height: 1.45;

            white-space: pre-wrap;

            word-break: break-word;

            font-size: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Created
        |--------------------------------------------------------------------------
        */

        .created-by {

            font-weight: 650;

            color:
                #4a3a54;
        }


        .created-role {

            display: inline-block;

            margin-top: 4px;

            padding:
                3px
                6px;

            border-radius: 5px;

            color:
                var(--purple-700);

            background:
                var(--purple-100);

            font-size: 9px;

            text-transform: capitalize;

            font-weight: 700;
        }


        .created-date {

            color:
                var(--text-muted);

            font-size: 10px;

            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | Actions
        |--------------------------------------------------------------------------
        */

        .actions {

            display: flex;

            align-items: center;

            gap: 6px;

            flex-wrap: wrap;
        }


        .viewer-badge {

            display: inline-flex;

            align-items: center;

            padding:
                6px
                8px;

            border-radius: 7px;

            color:
                #7e22ce;

            background:
                #faf5ff;

            font-size: 9px;

            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .empty {

            padding:
                65px
                30px;

            text-align: center;

            border:
                1px solid
                var(--border);

            border-radius: 16px;

            background:
                #fff;

            box-shadow:
                var(--shadow-md);
        }


        .empty-icon {

            width: 58px;
            height: 58px;

            margin:
                0
                auto
                15px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 17px;

            background:
                var(--purple-100);

            color:
                var(--purple-700);

            font-size: 24px;
        }


        .empty h3 {

            margin:
                0
                0
                7px;

            font-size: 17px;
        }


        .empty p {

            margin:
                0
                0
                18px;

            color:
                var(--text-muted);

            font-size: 11px;
        }


        .no-search-results {

            display: none;

            padding:
                28px;

            text-align: center;

            color:
                var(--text-muted);

            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            :root {
                --sidebar-width: 78px;
            }


            .sidebar {
                padding:
                    20px
                    10px;
            }


            .brand {
                justify-content: center;
                padding:
                    4px
                    0
                    22px;
            }


            .brand-text,
            .nav-title {
                display: none;
            }


            .nav-link {
                justify-content: center;
                padding: 12px 8px;
            }


            .nav-link span:not(.nav-icon) {
                display: none;
            }


            .main {
                margin-left: 78px;
            }


            .topbar {
                padding:
                    0
                    20px;
            }


            .top-search input {
                width: 210px;
            }


            .content {
                padding: 24px 20px;
            }
        }


        @media (max-width: 700px) {

            .topbar {
                height: auto;

                min-height: 70px;

                padding:
                    14px
                    16px;

                align-items: flex-start;

                flex-direction: column;
            }


            .topbar-right {
                width: 100%;
            }


            .top-search {
                flex: 1;
            }


            .top-search input {
                width: 100%;
            }


            .user-pill {
                flex-shrink: 0;
            }


            .page-heading {
                flex-direction: column;
            }


            .header-actions {
                width: 100%;
            }


            .header-actions > * {
                flex: 1;
            }


            .search-row {
                flex-direction: column;
            }


            .search-input,
            .clear-search {
                width: 100%;
            }


            .employee-details {
                flex-direction: column;
            }


            .employee-detail {
                width: fit-content;
            }
        }


        @media (max-width: 480px) {

            :root {
                --sidebar-width: 64px;
            }


            .sidebar {
                padding:
                    16px
                    7px;
            }


            .brand-icon {
                width: 38px;
                height: 38px;
            }


            .main {
                margin-left: 64px;
            }


            .content {
                padding:
                    18px
                    13px;
            }


            .heading-left h2 {
                font-size: 23px;
            }


            .topbar-title h1 {
                font-size: 18px;
            }
        }

    </style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>


<body>


<!--
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
-->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-icon">
            🔐
        </div>

        <div class="brand-text">

            <strong>
                Credential Manager
            </strong>

            <span>
                Secure Access Platform
            </span>

        </div>

    </div>


    <div class="nav-section">

        <div class="nav-title">
            Main Menu
        </div>


        <a
            href="/dashboard.php"
            class="nav-link"
        >
            <span class="nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>


        <a
            href="/departments.php"
            class="nav-link"
        >
            <span class="nav-icon">▦</span>
            <span>Departments</span>
        </a>


        <a
            href="/credentials.php"
            class="nav-link active"
        >
            <span class="nav-icon">🔑</span>
            <span>Credentials</span>
        </a>

    </div>


    <div class="nav-section">

        <div class="nav-title">
            Administration
        </div>


        <?php if (canManageUsers()): ?>

            <a
                href="/manage-users.php"
                class="nav-link"
            >
                <span class="nav-icon">👥</span>
                <span>Manage Users</span>
            </a>

        <?php endif; ?>


        <?php if (canManageDepartments()): ?>

            <a
                href="/manage-departments.php"
                class="nav-link"
            >
                <span class="nav-icon">⚙</span>
                <span>Manage Departments</span>
            </a>

        <?php endif; ?>


        <?php if (isAdmin()): ?>

            <a
                href="/audit-logs.php"
                class="nav-link"
            >
                <span class="nav-icon">◷</span>
                <span>Audit Logs</span>
            </a>

        <?php endif; ?>


        <?php if (
            function_exists('canManageRoot')
            &&
            canManageRoot()
        ): ?>

            <a
                href="/root-management.php"
                class="nav-link"
            >
                <span class="nav-icon">♛</span>
                <span>Root Management</span>
            </a>

        <?php endif; ?>

    </div>


    <div class="nav-section">

        <a
            href="/logout.php"
            class="nav-link"
        >
            <span class="nav-icon">↪</span>
            <span>Logout</span>
        </a>

    </div>

</aside>


<!--
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
-->

<main class="main">


    <!-- Topbar -->

    <header class="topbar">


        <div class="topbar-title">

            <h1>
                Credentials
            </h1>

            <p>
                Securely manage and access employee credentials
            </p>

        </div>


        <div class="topbar-right">


            <form
                class="top-search"
                method="GET"
                action="/search.php"
            >

                <input
                    type="search"
                    name="q"
                    placeholder="Search credentials..."
                    autocomplete="off"
                >

            </form>


            <div class="user-pill">

                <div class="avatar">

                    <?php
                    echo strtoupper(
                        substr(
                            (string) (
                                $_SESSION['full_name']
                                ??
                                $_SESSION['username']
                                ??
                                'U'
                            ),
                            0,
                            1
                        )
                    );
                    ?>

                </div>


                <div class="user-info">

                    <div class="user-name">

                        <?php
                        echo htmlspecialchars(
                            (string) (
                                $_SESSION['full_name']
                                ??
                                $_SESSION['username']
                                ??
                                'User'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                    <div class="user-role">

                        <?php
                        echo htmlspecialchars(
                            (string) (
                                $_SESSION['role']
                                ?? 'user'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>

    </header>


    <section class="content">


        <!-- Page heading -->

        <div class="page-heading">


            <div class="heading-left">

                <h2>

                    <?php if ($employee): ?>

                        Employee Credentials

                    <?php else: ?>

                        All Credentials

                    <?php endif; ?>

                </h2>


                <p>

                    View and securely manage stored credentials.

                </p>

            </div>


            <div class="header-actions">


                <?php if ($employee): ?>

                    <a
                        href="/employees.php?department_id=<?php echo (int) $employee['department_id']; ?>"
                        class="button"
                    >
                        ← Back to Employees
                    </a>

                <?php endif; ?>


                <?php if (
                    canAddCredentials()
                    &&
                    $employeeId > 0
                ): ?>

                    <a
                        href="/add-credential.php?employee_id=<?php echo (int) $employeeId; ?>"
                        class="add-button"
                    >
                        + Add Credential
                    </a>

                <?php endif; ?>

            </div>

        </div>


        <!-- Messages -->


        <?php if ($accessDenied): ?>

            <div class="message warning">
                ⚠️
                <span>
                    Access denied. You do not have permission to perform that action.
                </span>
            </div>

        <?php endif; ?>


        <?php if ($added): ?>

            <div class="message success">
                ✓
                <span>
                    Credential added successfully.
                </span>
            </div>

        <?php endif; ?>


        <?php if ($updated): ?>

            <div class="message success">
                ✓
                <span>
                    Credential updated successfully.
                </span>
            </div>

        <?php endif; ?>


        <?php if ($deleted): ?>

            <div class="message success">
                ✓
                <span>
                    Credential deleted successfully.
                </span>
            </div>

        <?php endif; ?>


        <!-- Employee information -->


        <?php if ($employee): ?>

            <div class="employee-card">

                <h3>

                    <?php
                    echo htmlspecialchars(
                        (string) $employee['employee_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </h3>


                <div class="employee-details">


                    <?php if (!empty($employee['employee_id'])): ?>

                        <div class="employee-detail">

                            <strong>
                                Employee ID:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                (string) $employee['employee_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                    <div class="employee-detail">

                        <strong>
                            Department:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            (string) (
                                $employee['department_name']
                                ??
                                'Unknown'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>


                    <div class="employee-detail">

                        <strong>
                            Credentials:
                        </strong>

                        <?php echo count($credentials); ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- Search -->


        <?php if (!empty($credentials)): ?>

            <div class="search-card">


                <div class="search-top">

                    <div class="search-label">
                        Search Credentials
                    </div>

                    <div class="search-hint">
                        Service name • Employee name • Employee ID
                    </div>

                </div>


                <div class="search-row">

                    <input
                        type="text"
                        id="credentialSearch"
                        class="search-input"
                        placeholder="Search service, employee, or employee ID..."
                        autocomplete="off"
                        spellcheck="false"
                    >


                    <button
                        type="button"
                        id="clearSearch"
                        class="clear-search"
                    >
                        Clear
                    </button>

                </div>


                <div
                    id="searchResultCount"
                    class="search-result-count"
                >
                    Showing
                    <?php echo count($credentials); ?>
                    credential<?php
                        echo count($credentials) === 1
                            ? ''
                            : 's';
                    ?>.
                </div>

            </div>

        <?php endif; ?>


        <!-- Credentials -->


        <?php if (empty($credentials)): ?>


            <div class="empty">


                <div class="empty-icon">
                    🔑
                </div>


                <h3>
                    No credentials found
                </h3>


                <p>
                    There are currently no credentials available for this employee.
                </p>


                <?php if (
                    canAddCredentials()
                    &&
                    $employeeId > 0
                ): ?>

                    <a
                        href="/add-credential.php?employee_id=<?php echo (int) $employeeId; ?>"
                        class="add-button"
                    >
                        + Add First Credential
                    </a>

                <?php endif; ?>


            </div>


        <?php else: ?>


            <div class="table-card">


                <div class="table-header">

                    <div>

                        <div class="table-title">
                            Stored Credentials
                        </div>

                        <div class="table-subtitle">
                            Passwords remain hidden until explicitly revealed.
                        </div>

                    </div>

                </div>


                <div class="table-wrapper">


                    <table id="credentialsTable">


                        <thead>

                            <tr>

                                <th>
                                    Service
                                </th>


                                <?php if ($employeeId === 0): ?>

                                    <th>
                                        Employee
                                    </th>

                                    <th>
                                        Department
                                    </th>

                                <?php endif; ?>


                                <th>
                                    Username
                                </th>


                                <th>
                                    Password
                                </th>


                                <th>
                                    URL
                                </th>


                                <th>
                                    Notes
                                </th>


                                <th>
                                    Created By
                                </th>


                                <th>
                                    Created
                                </th>


                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($credentials as $credential): ?>


                                <?php

                                /*
                                |--------------------------------------------------------------------------
                                | Searchable Text
                                |--------------------------------------------------------------------------
                                |
                                | ONLY:
                                |
                                | Service Name
                                | Employee Name
                                | Employee ID
                                |
                                */

                                $searchText = implode(
                                    ' ',
                                    [
                                        $credential['service_name'] ?? '',
                                        $credential['employee_name'] ?? '',
                                        $credential['employee_code'] ?? ''
                                    ]
                                );

                                ?>


                                <tr
                                    data-search="<?php
                                        echo htmlspecialchars(
                                            strtolower($searchText),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >


                                    <!-- Service -->

                                    <td>

                                        <div class="service-name">


                                            <div class="service-icon">
                                                🔑
                                            </div>


                                            <div>

                                                <?php
                                                echo htmlspecialchars(
                                                    (string)
                                                    $credential['service_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>


                                                <?php if (
                                                    !empty(
                                                        $credential['is_new_version']
                                                    )
                                                ): ?>

                                                    <div class="version-comment">
                                                        New Password Version
                                                    </div>

                                                <?php endif; ?>


                                                <?php if (
                                                    !empty(
                                                        $credential['change_comment']
                                                    )
                                                ): ?>

                                                    <div class="change-comment">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            (string)
                                                            $credential['change_comment'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>

                                                    </div>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Employee -->


                                    <?php if ($employeeId === 0): ?>


                                        <td>

                                            <div class="employee-name">

                                                <?php
                                                echo htmlspecialchars(
                                                    (string) (
                                                        $credential['employee_name']
                                                        ??
                                                        'Unknown'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </div>


                                            <?php if (
                                                !empty(
                                                    $credential['employee_code']
                                                )
                                            ): ?>

                                                <div class="employee-id">

                                                    ID:

                                                    <?php
                                                    echo htmlspecialchars(
                                                        (string)
                                                        $credential['employee_code'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Department -->


                                        <td>

                                            <span class="department-badge">

                                                <?php
                                                echo htmlspecialchars(
                                                    (string) (
                                                        $credential['department_name']
                                                        ??
                                                        'Unknown'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </span>

                                        </td>


                                    <?php endif; ?>


                                    <!-- Username -->


                                    <td>

                                        <span class="username">

                                            <?php
                                            echo htmlspecialchars(
                                                (string) (
                                                    $credential['credential_username']
                                                    ??
                                                    ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- Password -->


                                    <td class="password-field">


                                        <div class="password-actions">


                                            <span
                                                class="password"
                                                data-credential-id="<?php
                                                    echo (int)
                                                        $credential['id'];
                                                ?>"
                                                data-visible="0"
                                                aria-label="Password hidden"
                                            >
                                                ••••••••
                                            </span>


                                            <button
                                                type="button"
                                                class="toggle-button"
                                                onclick="togglePassword(this)"
                                            >
                                                Show
                                            </button>


                                        </div>


                                    </td>


                                    <!-- URL -->


                                    <td>


                                        <?php if (
                                            !empty(
                                                $credential['service_url']
                                            )
                                        ): ?>


                                            <a
                                                class="url-link"
                                                href="<?php
                                                    echo htmlspecialchars(
                                                        (string)
                                                        $credential['service_url'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                ↗ Open
                                            </a>


                                        <?php else: ?>

                                            —

                                        <?php endif; ?>


                                    </td>


                                    <!-- Notes -->


                                    <td class="notes-cell">

                                        <?php
                                        echo htmlspecialchars(
                                            (string) (
                                                $credential['notes']
                                                ??
                                                ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </td>


                                    <!-- Created By -->


                                    <td>

                                        <div class="created-by">

                                            <?php
                                            echo htmlspecialchars(
                                                (string) (
                                                    $credential['created_by_name']
                                                    ??
                                                    'Unknown'
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $credential['created_by_role']
                                            )
                                        ): ?>

                                            <span class="created-role">

                                                <?php
                                                echo htmlspecialchars(
                                                    (string)
                                                    $credential['created_by_role'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Created -->


                                    <td>

                                        <span class="created-date">

                                            <?php
                                            echo htmlspecialchars(
                                                (string)
                                                $credential['created_at'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <!-- Actions -->


                                    <td>

                                        <div class="actions">


                                            <?php if (
                                                canEditCredentials()
                                            ): ?>


                                                <a
                                                    class="button"
                                                    href="/edit-credential.php?id=<?php
                                                        echo (int)
                                                            $credential['id'];
                                                    ?>"
                                                >
                                                    Edit
                                                </a>


                                                <a
                                                    class="button"
                                                    href="/add-password-version.php?id=<?php
                                                        echo (int)
                                                            $credential['id'];
                                                    ?>"
                                                >
                                                    + New Password
                                                </a>


                                            <?php endif; ?>


                                            <?php if (
                                                canDeleteCredentials()
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    action="/delete-credential.php"
                                                    onsubmit="return confirm('Are you sure you want to delete this credential?');"
                                                >


                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?php
                                                            echo (int)
                                                                $credential['id'];
                                                        ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php
                                                            echo htmlspecialchars(
                                                                csrfToken(),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            );
                                                        ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="delete-button"
                                                    >
                                                        Delete
                                                    </button>


                                                </form>


                                            <?php endif; ?>


                                            <?php if (
                                                hasRole('viewer')
                                            ): ?>

                                                <span class="viewer-badge">
                                                    View Only
                                                </span>

                                            <?php endif; ?>


                                        </div>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


                <div
                    id="noSearchResults"
                    class="no-search-results"
                >

                    <strong>
                        No credentials match your search.
                    </strong>

                    <br><br>

                    Search using
                    <strong>Service Name</strong>,
                    <strong>Employee Name</strong>,
                    or
                    <strong>Employee ID</strong>.

                </div>


            </div>


        <?php endif; ?>


    </section>


</main>


<script>


/*
|--------------------------------------------------------------------------
| Password Retrieval
|--------------------------------------------------------------------------
|
| Password is NOT loaded when credentials.php loads.
|
| It is requested only after the user explicitly clicks Show.
|
| There is NO clipboard functionality.
|
*/

async function fetchCredentialPassword(id) {

    const response = await fetch(
        '/show-password.php?id=' +
        encodeURIComponent(id),
        {
            method: 'GET',

            credentials: 'same-origin',

            cache: 'no-store',

            headers: {
                'Accept': 'application/json'
            }
        }
    );


    let data;


    try {

        data = await response.json();

    } catch (error) {

        throw new Error(
            'Unable to access password.'
        );

    }


    if (
        !response.ok ||
        !data.success
    ) {

        throw new Error(
            data.message ||
            'Unable to access password.'
        );

    }


    return data.password;
}


/*
|--------------------------------------------------------------------------
| Hide Password
|--------------------------------------------------------------------------
*/

function hidePassword(
    passwordSpan,
    button
) {

    passwordSpan.textContent =
        '••••••••';

    passwordSpan.dataset.visible =
        '0';

    passwordSpan.classList.remove(
        'visible'
    );

    passwordSpan.setAttribute(
        'aria-label',
        'Password hidden'
    );


    if (button) {

        button.textContent =
            'Show';

    }


    if (
        passwordSpan._hideTimer
    ) {

        clearTimeout(
            passwordSpan._hideTimer
        );

        passwordSpan._hideTimer =
            null;
    }
}


/*
|--------------------------------------------------------------------------
| Show / Hide Password
|--------------------------------------------------------------------------
*/

function togglePassword(button) {

    const passwordField =
        button.closest(
            '.password-field'
        );


    if (!passwordField) {
        return;
    }


    const passwordSpan =
        passwordField.querySelector(
            '.password'
        );


    if (!passwordSpan) {
        return;
    }


    const id =
        passwordSpan.dataset.credentialId;


    if (!id) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Hide Existing Password
    |--------------------------------------------------------------------------
    */

    if (
        passwordSpan.dataset.visible === '1'
    ) {

        hidePassword(
            passwordSpan,
            button
        );

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Retrieve Password
    |--------------------------------------------------------------------------
    */

    button.disabled =
        true;

    button.textContent =
        'Loading...';


    fetchCredentialPassword(id)

        .then(function(password) {


            passwordSpan.textContent =
                password;

            passwordSpan.dataset.visible =
                '1';

            passwordSpan.classList.add(
                'visible'
            );

            passwordSpan.setAttribute(
                'aria-label',
                'Password visible'
            );


            button.textContent =
                'Hide';


            /*
            |--------------------------------------------------------------------------
            | Automatically Hide After 30 Seconds
            |--------------------------------------------------------------------------
            */

            if (
                passwordSpan._hideTimer
            ) {

                clearTimeout(
                    passwordSpan._hideTimer
                );

            }


            passwordSpan._hideTimer =
                setTimeout(
                    function() {

                        hidePassword(
                            passwordSpan,
                            button
                        );

                    },
                    30000
                );

        })


        .catch(function(error) {

            alert(
                error.message ||
                'Unable to access password.'
            );


            button.textContent =
                'Show';

        })


        .finally(function() {

            button.disabled =
                false;

        });

}


/*
|--------------------------------------------------------------------------
| Credential Search
|--------------------------------------------------------------------------
|
| ONLY:
|
| - Service Name
| - Employee Name
| - Employee ID
|
*/

(function() {


    const searchInput =
        document.getElementById(
            'credentialSearch'
        );


    const clearButton =
        document.getElementById(
            'clearSearch'
        );


    const table =
        document.getElementById(
            'credentialsTable'
        );


    const resultCount =
        document.getElementById(
            'searchResultCount'
        );


    const noResults =
        document.getElementById(
            'noSearchResults'
        );


    if (
        !searchInput ||
        !table
    ) {

        return;
    }


    const rows =
        Array.from(
            table.querySelectorAll(
                'tbody tr'
            )
        );


    const total =
        rows.length;


    function performSearch() {


        const query =
            searchInput.value
                .trim()
                .toLowerCase();


        let visibleCount =
            0;


        rows.forEach(
            function(row) {


                const searchableText =
                    (
                        row.dataset.search ||
                        ''
                    ).toLowerCase();


                const matches =
                    query === '' ||
                    searchableText.includes(
                        query
                    );


                if (matches) {

                    row.classList.remove(
                        'search-hidden'
                    );

                    visibleCount++;

                } else {

                    row.classList.add(
                        'search-hidden'
                    );

                }

            }
        );


        if (
            resultCount
        ) {

            if (
                query === ''
            ) {

                resultCount.textContent =
                    'Showing ' +
                    total +
                    ' credential' +
                    (
                        total === 1
                            ? ''
                            : 's'
                    ) +
                    '.';

            } else {

                resultCount.textContent =
                    'Showing ' +
                    visibleCount +
                    ' of ' +
                    total +
                    ' credential' +
                    (
                        total === 1
                            ? ''
                            : 's'
                    ) +
                    '.';
            }

        }


        if (
            noResults
        ) {

            noResults.style.display =
                (
                    query !== '' &&
                    visibleCount === 0
                )
                    ? 'block'
                    : 'none';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Search While Typing
    |--------------------------------------------------------------------------
    */

    searchInput.addEventListener(
        'input',
        performSearch
    );


    /*
    |--------------------------------------------------------------------------
    | Clear Search
    |--------------------------------------------------------------------------
    */

    if (
        clearButton
    ) {

        clearButton.addEventListener(
            'click',
            function() {

                searchInput.value =
                    '';

                performSearch();

                searchInput.focus();

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Escape = Clear
    |--------------------------------------------------------------------------
    */

    searchInput.addEventListener(
        'keydown',
        function(event) {

            if (
                event.key === 'Escape'
            ) {

                searchInput.value =
                    '';

                performSearch();

            }

        }
    );


    performSearch();

})();


/*
|--------------------------------------------------------------------------
| Prevent accidental password selection
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'dblclick',
    function(event) {

        if (
            event.target.classList.contains(
                'password'
            )
        ) {

            event.preventDefault();

        }

    }
);


</script>


    <script src="/assets/theme.js"></script>

</body>

</html>
