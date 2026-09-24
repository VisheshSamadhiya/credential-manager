<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$stmt = $pdo->query(
    "SELECT id, department_name
     FROM departments
     ORDER BY department_name ASC"
);

$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Departments | Credential Manager</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f5f3ff 0%,
                    #faf5ff 50%,
                    #fdf2f8 100%
                );

            color: #1f2937;
            min-height: 100vh;
        }

        /* =========================
           NAVBAR
           ========================= */

        .navbar {
            background:
                linear-gradient(
                    135deg,
                    #4c1d95,
                    #6d28d9,
                    #9d174d
                );

            min-height: 72px;
            padding: 0 40px;

            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            box-shadow:
                0 8px 30px rgba(76, 29, 149, 0.22);

            position: relative;
            z-index: 10;
        }

        .brand {
            font-size: 21px;
            font-weight: 750;
            letter-spacing: -0.3px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;

            padding: 10px 14px;
            border-radius: 10px;

            font-size: 14px;
            font-weight: 600;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .navbar a:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .navbar .logout {
            background: rgba(255, 255, 255, 0.12);
        }

        /* =========================
           MAIN CONTAINER
           ========================= */

        .container {
            max-width: 1150px;
            margin: 0 auto;
            padding: 55px 30px 70px;
        }

        .page-header {
            margin-bottom: 35px;
        }

        .page-label {
            display: inline-block;

            padding: 7px 12px;
            margin-bottom: 12px;

            border-radius: 999px;

            background: #ede9fe;
            color: #6d28d9;

            font-size: 12px;
            font-weight: 750;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .page-header h1 {
            margin: 0 0 10px;

            font-size: 34px;
            line-height: 1.2;

            color: #2e1065;

            letter-spacing: -0.8px;
        }

        .page-header p {
            margin: 0;

            color: #6b7280;
            font-size: 15px;
        }

        /* =========================
           DEPARTMENT GRID
           ========================= */

        .department-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(240px, 1fr)
                );

            gap: 22px;
        }

        /* =========================
           DEPARTMENT CARD
           ========================= */

        .department-card {
            position: relative;

            display: block;

            min-height: 165px;

            padding: 25px;

            border-radius: 18px;

            text-decoration: none;

            /* DARK DEFAULT STATE */
            background:
                linear-gradient(
                    135deg,
                    #4c1d95,
                    #6b21a8
                );

            color: white;

            border: 1px solid #7e22ce;

            box-shadow:
                0 8px 24px
                rgba(76, 29, 149, 0.18);

            overflow: hidden;

            transition:
                background 0.25s ease,
                color 0.25s ease,
                transform 0.25s ease,
                box-shadow 0.25s ease,
                border-color 0.25s ease;
        }

        /*
         * Decorative glow
         */
        .department-card::before {
            content: "";

            position: absolute;

            width: 120px;
            height: 120px;

            top: -55px;
            right: -45px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.08);

            transition:
                background 0.25s ease,
                transform 0.25s ease;
        }

        .department-card::after {
            content: "";

            position: absolute;

            width: 80px;
            height: 80px;

            bottom: -45px;
            left: -30px;

            border-radius: 50%;

            background: rgba(236, 72, 153, 0.18);

            transition:
                background 0.25s ease,
                transform 0.25s ease;
        }

        .department-card h2 {
            position: relative;
            z-index: 2;

            margin: 0 0 14px;

            font-size: 21px;
            line-height: 1.3;

            color: #ffffff;

            transition:
                color 0.25s ease;
        }

        .department-card p {
            position: relative;
            z-index: 2;

            margin: 0;

            color: rgba(255, 255, 255, 0.78);

            font-size: 13px;
            font-weight: 600;

            transition:
                color 0.25s ease;
        }

        /*
         * =========================
         * HOVER STATE
         * =========================
         *
         * Dark when cursor is away.
         * Light purple when cursor is over.
         */

        .department-card:hover {
            background:
                linear-gradient(
                    135deg,
                    #f3e8ff,
                    #fce7f3
                );

            color: #6d28d9;

            border-color: #d8b4fe;

            transform: translateY(-5px);

            box-shadow:
                0 16px 32px
                rgba(168, 85, 247, 0.20);
        }

        .department-card:hover h2 {
            color: #6d28d9;
        }

        .department-card:hover p {
            color: #9333ea;
        }

        .department-card:hover::before {
            background: rgba(168, 85, 247, 0.12);
            transform: scale(1.15);
        }

        .department-card:hover::after {
            background: rgba(236, 72, 153, 0.12);
            transform: scale(1.15);
        }

        /* =========================
           EMPTY STATE
           ========================= */

        .empty-state {
            background: #ffffff;

            border: 1px solid #e9d5ff;

            border-radius: 18px;

            padding: 45px 30px;

            text-align: center;

            box-shadow:
                0 8px 25px
                rgba(76, 29, 149, 0.08);
        }

        .empty-icon {
            width: 54px;
            height: 54px;

            margin: 0 auto 15px;

            border-radius: 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f3e8ff;
            color: #7e22ce;

            font-size: 24px;
            font-weight: 800;
        }

        .empty-state h2 {
            margin: 0 0 8px;

            color: #3b0764;
            font-size: 20px;
        }

        .empty-state p {
            margin: 0;

            color: #6b7280;
            font-size: 14px;
        }

        /* =========================
           MOBILE
           ========================= */

        @media (max-width: 800px) {

            .navbar {
                padding: 15px 20px;

                flex-direction: column;
                align-items: flex-start;

                gap: 14px;
            }

            .nav-links {
                width: 100%;

                flex-wrap: wrap;
            }

            .navbar a {
                font-size: 13px;
                padding: 8px 10px;
            }

            .container {
                padding: 35px 20px 50px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .department-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>

<body>

    <header class="navbar">

        <div class="brand">
            Credential Manager
        </div>

        <nav class="nav-links">

            <a href="/dashboard.php">
                Dashboard
            </a>

            <a href="/departments.php">
                Departments
            </a>

            <?php if (isAdmin()): ?>

                <a href="/manage-departments.php">
                    Manage Departments
                </a>

            <?php endif; ?>

            <a href="/logout.php" class="logout">
                Logout
            </a>

        </nav>

    </header>


    <main class="container">

        <div class="page-header">

            <span class="page-label">
                Organization
            </span>

            <h1>
                Select Department
            </h1>

            <p>
                Choose a department to view its employees and associated credentials.
            </p>

        </div>


        <?php if (empty($departments)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    !
                </div>

                <h2>
                    No Departments Available
                </h2>

                <p>
                    There are currently no departments configured in Credential Manager.
                </p>

            </div>

        <?php else: ?>

            <div class="department-grid">

                <?php foreach ($departments as $department): ?>

                    <a
                        class="department-card"
                        href="/employees.php?department_id=<?php echo (int) $department['id']; ?>"
                    >

                        <h2>
                            <?php
                            echo htmlspecialchars(
                                (string) $department['department_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </h2>

                        <p>
                            View Employees →
                        </p>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

    <script src="/assets/theme.js"></script>

</body>

</html>
