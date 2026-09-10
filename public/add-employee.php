<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin Permission
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {
    exit('Access denied. Only administrators can add employees.');
}


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
    "SELECT id, department_name
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
| Add Employee
|--------------------------------------------------------------------------
*/

$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeName = trim($_POST['employee_name'] ?? '');

    $employeeCode = trim($_POST['employee_id'] ?? '');


    if ($employeeName === '' || $employeeCode === '') {

        $error = 'Employee Name and Employee ID are required.';

    } else {

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO employees
                (
                    employee_name,
                    employee_id,
                    department_id
                )
                VALUES
                (
                    :employee_name,
                    :employee_id,
                    :department_id
                )"
            );


            $stmt->execute([
                ':employee_name' => $employeeName,

                ':employee_id' => $employeeCode,

                ':department_id' => $departmentId
            ]);


            header(
                'Location: /employees.php?department_id='
                . $departmentId
                . '&added=1'
            );

            exit;

        } catch (PDOException $e) {

            /*
            |------------------------------------------
            | Temporary detailed error for debugging
            |------------------------------------------
            */

            if ($e->getCode() === '23000') {

                $error = 'This Employee ID already exists. Please use a different Employee ID.';

            } else {

                $error = 'Database error: ' . $e->getMessage();

            }

        }

    }

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

    <title>Add Employee - Credential Manager</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
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
            padding: 18px 40px;
            color: white;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }


        .navbar-title {
            font-size: 18px;
        }


        /*
        | This creates proper spacing
        | between all navigation links
        */

        .navbar-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }


        .navbar a {
            color: white;
            text-decoration: none;
        }


        .navbar a:hover {
            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | Main Container
        |--------------------------------------------------------------------------
        */

        .container {
            max-width: 650px;
            margin: 50px auto;
            background: white;
            padding: 35px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }


        h1 {
            margin-top: 0;
        }


        .department-info {
            background: #f4f6f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }


        /*
        |--------------------------------------------------------------------------
        | Form
        |--------------------------------------------------------------------------
        */

        .form-group {
            margin-bottom: 20px;
        }


        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }


        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }


        input:focus {
            outline: none;
            border-color: #555;
        }


        button {
            background: #333;
            color: white;
            border: none;
            padding: 13px 22px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }


        button:hover {
            background: #555;
        }


        .error {
            background: #ffe5e5;
            color: #b00020;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }


        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: #333;
            text-decoration: none;
        }


        .back-link:hover {
            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }


            .navbar-links {
                flex-wrap: wrap;
                gap: 15px;
            }


            .container {
                margin: 25px 15px;
                padding: 25px;
            }

        }

    </style>

</head>


<body>


<!-- Navigation -->

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

            My Credentials

        </a>


        <a href="/logout.php">

            Logout

        </a>


    </div>


</div>


<!-- Main Content -->

<div class="container">


    <a
        class="back-link"
        href="/employees.php?department_id=<?php echo $departmentId; ?>"
    >

        ← Back to Employees

    </a>


    <h1>Add Employee</h1>


    <div class="department-info">

        Adding employee to:

        <strong>

            <?php
            echo htmlspecialchars(
                $department['department_name']
            );
            ?>

        </strong>

    </div>


    <?php if ($error): ?>


        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>


    <?php endif; ?>


    <form method="POST">


        <!-- Employee Name -->

        <div class="form-group">

            <label for="employee_name">

                Employee Name

            </label>


            <input
                type="text"
                id="employee_name"
                name="employee_name"
                placeholder="Enter employee name"
                required
            >

        </div>


        <!-- Employee ID -->

        <div class="form-group">

            <label for="employee_id">

                Employee ID

            </label>


            <input
                type="text"
                id="employee_id"
                name="employee_id"
                placeholder="Example: EMP-1001"
                required
            >

        </div>


        <button type="submit">

            + Add Employee

        </button>


    </form>


</div>


</body>

</html>
