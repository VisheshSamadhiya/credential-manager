<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

/*
|--------------------------------------------------------------------------
| Get Department ID
|--------------------------------------------------------------------------
*/

$departmentId = isset($_GET['department_id'])
    ? (int) $_GET['department_id']
    : 0;

if ($departmentId <= 0) {
    header('Location: /departments.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Department
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        department_name
     FROM departments
     WHERE id = :id"
);

$stmt->execute([
    ':id' => $departmentId
]);

$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    header('Location: /departments.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Employees
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        id,
        employee_name,
        employee_id,
        created_at
     FROM employees
     WHERE department_id = :department_id
     ORDER BY employee_name ASC"
);

$stmt->execute([
    ':department_id' => $departmentId
]);

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| User Role
|--------------------------------------------------------------------------
*/

$userRole = $_SESSION['role'] ?? 'viewer';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php
        echo htmlspecialchars(
            (string) $department['department_name'],
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
        | Employees
    </title>

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

        /* =========================================
           NAVBAR
           ========================================= */

        .navbar {
            min-height: 72px;

            padding: 0 40px;

            background:
                linear-gradient(
                    135deg,
                    #4c1d95,
                    #6d28d9,
                    #9d174d
                );

            color: #ffffff;

            display: flex;
            align-items: center;
            justify-content: space-between;

            box-shadow:
                0 8px 30px rgba(76, 29, 149, 0.22);

            position: relative;
            z-index: 10;
        }

        .navbar-title {
            font-size: 21px;
            font-weight: 750;

            letter-spacing: -0.3px;
        }

        .navbar-links {
            display: flex;
            align-items: center;

            gap: 8px;

            flex-wrap: wrap;
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

        /* =========================================
           MAIN CONTAINER
           ========================================= */

        .container {
            max-width: 1150px;

            margin: 0 auto;

            padding: 45px 30px 70px;
        }

        /* =========================================
           BACK LINK
           ========================================= */

        .back {
            display: inline-flex;
            align-items: center;

            margin-bottom: 25px;

            color: #6d28d9;

            text-decoration: none;

            font-size: 14px;
            font-weight: 700;

            transition:
                color 0.2s ease,
                transform 0.2s ease;
        }

        .back:hover {
            color: #be185d;

            transform: translateX(-3px);
        }

        /* =========================================
           PAGE HEADER
           ========================================= */

        .page-header {
            display: flex;

            justify-content: space-between;
            align-items: flex-end;

            gap: 25px;

            margin-bottom: 38px;
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

        /* =========================================
           ADD EMPLOYEE BUTTON
           ========================================= */

        .add-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 12px 18px;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #7c3aed,
                    #db2777
                );

            color: #ffffff;

            text-decoration: none;

            font-size: 14px;
            font-weight: 700;

            border: none;

            box-shadow:
                0 7px 18px
                rgba(124, 58, 237, 0.22);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .add-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 12px 25px
                rgba(124, 58, 237, 0.28);
        }

        /* =========================================
           SECTION TITLE
           ========================================= */

        .section-header {
            display: flex;

            align-items: center;
            justify-content: space-between;

            margin-bottom: 18px;
        }

        .section-header h2 {
            margin: 0;

            font-size: 24px;

            color: #3b0764;
        }

        .employee-count {
            padding: 6px 11px;

            border-radius: 999px;

            background: #f3e8ff;

            color: #7e22ce;

            font-size: 12px;
            font-weight: 750;
        }

        /* =========================================
           EMPLOYEE GRID
           ========================================= */

        .employee-list {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(280px, 1fr)
                );

            gap: 22px;
        }

        /* =========================================
           EMPLOYEE CARD
           ========================================= */

        .employee-card {
            position: relative;

            padding: 25px;

            min-height: 190px;

            border-radius: 18px;

            /*
             * Same dark tone as department cards
             */
            background:
                linear-gradient(
                    135deg,
                    #4c1d95,
                    #6b21a8
                );

            border: 1px solid #7e22ce;

            color: #ffffff;

            box-shadow:
                0 8px 24px
                rgba(76, 29, 149, 0.18);

            overflow: hidden;

            transition:
                background 0.25s ease,
                border-color 0.25s ease,
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }

        /* Decorative circles */

        .employee-card::before {
            content: "";

            position: absolute;

            width: 125px;
            height: 125px;

            top: -65px;
            right: -45px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.08);

            transition:
                background 0.25s ease,
                transform 0.25s ease;
        }

        .employee-card::after {
            content: "";

            position: absolute;

            width: 90px;
            height: 90px;

            bottom: -50px;
            left: -30px;

            border-radius: 50%;

            background:
                rgba(236, 72, 153, 0.18);

            transition:
                background 0.25s ease,
                transform 0.25s ease;
        }

        /*
         * Hover:
         * Dark -> light purple/pink
         */

        .employee-card:hover {
            background:
                linear-gradient(
                    135deg,
                    #f3e8ff,
                    #fce7f3
                );

            border-color: #d8b4fe;

            transform: translateY(-5px);

            box-shadow:
                0 16px 32px
                rgba(168, 85, 247, 0.20);
        }

        .employee-card:hover::before {
            background:
                rgba(168, 85, 247, 0.12);

            transform: scale(1.15);
        }

        .employee-card:hover::after {
            background:
                rgba(236, 72, 153, 0.12);

            transform: scale(1.15);
        }

        /* =========================================
           EMPLOYEE NAME
           ========================================= */

        .employee-card h3 {
            position: relative;

            z-index: 2;

            margin: 0 0 12px;

            font-size: 21px;

            line-height: 1.3;

            color: #ffffff;

            transition:
                color 0.25s ease;
        }

        .employee-card:hover h3 {
            color: #6d28d9;
        }

        /* =========================================
           EMPLOYEE ID
           ========================================= */

        .employee-id {
            position: relative;

            z-index: 2;

            margin-bottom: 22px;

            color:
                rgba(255, 255, 255, 0.75);

            font-size: 14px;

            transition:
                color 0.25s ease;
        }

        .employee-id strong {
            color: #ffffff;

            font-weight: 750;

            transition:
                color 0.25s ease;
        }

        .employee-card:hover .employee-id {
            color: #7e22ce;
        }

        .employee-card:hover .employee-id strong {
            color: #6d28d9;
        }

        /* =========================================
           ACTIONS
           ========================================= */

        .employee-actions {
            position: relative;

            z-index: 3;

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }

        /* =========================================
           VIEW CREDENTIALS
           ========================================= */

        .button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 10px 16px;

            border-radius: 10px;

            background:
                rgba(255, 255, 255, 0.14);

            border: 1px solid
                rgba(255, 255, 255, 0.20);

            color: #ffffff;

            text-decoration: none;

            font-size: 13px;
            font-weight: 700;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .button:hover {
            background: #ffffff;

            color: #6d28d9;

            border-color: #ffffff;

            transform: translateY(-1px);
        }

        .employee-card:hover .button {
            background: #7c3aed;

            border-color: #7c3aed;

            color: #ffffff;
        }

        .employee-card:hover .button:hover {
            background: #6d28d9;

            border-color: #6d28d9;
        }

        /* =========================================
           DELETE BUTTON
           ========================================= */

        .delete-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 10px 16px;

            border-radius: 10px;

            border: 1px solid
                rgba(255, 255, 255, 0.20);

            background:
                rgba(190, 24, 93, 0.35);

            color: #ffffff;

            cursor: pointer;

            font-family: inherit;

            font-size: 13px;
            font-weight: 700;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .delete-button:hover {
            background: #be123c;

            border-color: #be123c;

            transform: translateY(-1px);
        }

        .employee-card:hover .delete-button {
            background: #be123c;

            border-color: #be123c;
        }

        .employee-card:hover .delete-button:hover {
            background: #9f1239;

            border-color: #9f1239;
        }

        /* =========================================
           EMPTY STATE
           ========================================= */

        .empty {
            padding: 50px 30px;

            text-align: center;

            background: #ffffff;

            border: 1px solid #e9d5ff;

            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(76, 29, 149, 0.08);
        }

        .empty-icon {
            width: 56px;
            height: 56px;

            margin: 0 auto 15px;

            border-radius: 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f3e8ff;

            color: #7e22ce;

            font-size: 24px;
            font-weight: 800;
        }

        .empty h3 {
            margin: 0 0 8px;

            color: #3b0764;

            font-size: 20px;
        }

        .empty p {
            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }

        /* =========================================
           ALERTS
           ========================================= */

        .success {
            margin-bottom: 25px;

            padding: 14px 17px;

            border-radius: 12px;

            background: #ecfdf5;

            border: 1px solid #a7f3d0;

            color: #047857;

            font-size: 14px;
            font-weight: 600;
        }

        .error {
            margin-bottom: 25px;

            padding: 14px 17px;

            border-radius: 12px;

            background: #fff1f2;

            border: 1px solid #fecdd3;

            color: #be123c;

            font-size: 14px;
            font-weight: 600;
        }

        /* =========================================
           MOBILE
           ========================================= */

        @media (max-width: 800px) {

            .navbar {
                padding: 15px 20px;

                flex-direction: column;

                align-items: flex-start;

                gap: 14px;
            }

            .navbar-links {
                width: 100%;
            }

            .navbar a {
                padding: 8px 10px;

                font-size: 13px;
            }

            .container {
                padding: 35px 20px 50px;
            }

            .page-header {
                flex-direction: column;

                align-items: flex-start;

                gap: 18px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .add-button {
                width: 100%;
            }

            .employee-list {
                grid-template-columns: 1fr;
            }

        }

    </style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>

<body>

    <!-- =========================================
         NAVIGATION
         ========================================= -->

    <header class="navbar">

        <div class="navbar-title">
            Credential Manager
        </div>

        <nav class="navbar-links">

            <a href="/dashboard.php">
                Dashboard
            </a>

            <a href="/departments.php">
                Departments
            </a>

            <a href="/credentials.php">
                Credentials
            </a>

            <?php if (isAdmin()): ?>

                <a href="/manage-users.php">
                    Manage Users
                </a>

            <?php endif; ?>

            <a href="/logout.php">
                Logout
            </a>

        </nav>

    </header>


    <!-- =========================================
         MAIN CONTENT
         ========================================= -->

    <main class="container">

        <a
            href="/departments.php"
            class="back"
        >
            ← Back to Departments
        </a>


        <?php if (isset($_GET['deleted'])): ?>

            <div class="success">
                Employee deleted successfully.
            </div>

        <?php endif; ?>


        <?php if (isset($_GET['error'])): ?>

            <div class="error">
                Unable to complete the requested action.
            </div>

        <?php endif; ?>


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <span class="page-label">
                    Department
                </span>

                <h1>

                    <?php
                    echo htmlspecialchars(
                        (string) $department['department_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </h1>

                <p>
                    Manage employees and their credentials.
                </p>

            </div>


            <?php if ($userRole === 'admin' || $userRole === 'root'): ?>

                <a
                    href="/add-employee.php?department_id=<?php echo $departmentId; ?>"
                    class="add-button"
                >
                    + Add Employee
                </a>

            <?php endif; ?>

        </div>


        <!-- SECTION HEADER -->

        <div class="section-header">

            <h2>
                Employees
            </h2>

            <?php if (!empty($employees)): ?>

                <span class="employee-count">
                    <?php echo count($employees); ?>
                    <?php echo count($employees) === 1 ? 'Employee' : 'Employees'; ?>
                </span>

            <?php endif; ?>

        </div>


        <?php if (empty($employees)): ?>

            <div class="empty">

                <div class="empty-icon">
                    !
                </div>

                <h3>
                    No employees found.
                </h3>

                <?php if ($userRole === 'admin' || $userRole === 'root'): ?>

                    <p>
                        Add the first employee to this department.
                    </p>

                <?php else: ?>

                    <p>
                        No employees are currently available in this department.
                    </p>

                <?php endif; ?>

            </div>


        <?php else: ?>


            <div class="employee-list">

                <?php foreach ($employees as $employee): ?>

                    <article class="employee-card">

                        <h3>

                            <?php
                            echo htmlspecialchars(
                                (string) $employee['employee_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </h3>


                        <?php if (!empty($employee['employee_id'])): ?>

                            <div class="employee-id">

                                Employee ID:

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        (string) $employee['employee_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </strong>

                            </div>

                        <?php endif; ?>


                        <div class="employee-actions">


                            <a
                                href="/credentials.php?employee_id=<?php echo (int) $employee['id']; ?>"
                                class="button"
                            >
                                View Credentials
                            </a>


                            <?php if ($userRole === 'admin' || $userRole === 'root'): ?>

                                <form
                                    method="POST"
                                    action="/delete-employee.php"
                                    onsubmit="return confirm('WARNING: Deleting this employee may also delete all credentials assigned to them. Are you sure you want to continue?');"
                                    style="margin: 0;"
                                >

                                    <input
                                        type="hidden"
                                        name="employee_id"
                                        value="<?php echo (int) $employee['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="department_id"
                                        value="<?php echo $departmentId; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-button"
                                    >
                                        Delete Employee
                                    </button>

                                </form>

                            <?php endif; ?>


                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php endif; ?>


    </main>

    <script src="/assets/theme.js"></script>

</body>

</html>
