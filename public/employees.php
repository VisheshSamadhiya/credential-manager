<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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
    content="width=device-width, initial-scale=1"
>

<title>
    <?php
    echo htmlspecialchars(
        $department['department_name']
    );
    ?>
    - Employees
</title>


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


/* Navbar */

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
    font-weight: bold;

}


.navbar-links {

    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;

}


.navbar a {

    color: white;
    text-decoration: none;

}


.navbar a:hover {

    text-decoration: underline;

}


/* Main Container */

.container {

    max-width: 1100px;
    margin: 40px auto;
    padding: 30px;

}


/* Back Button */

.back {

    display: inline-block;
    margin-bottom: 20px;

    color: #333;

    text-decoration: none;
    font-weight: bold;

}


.back:hover {

    text-decoration: underline;

}


/* Header */

.page-header {

    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;

}


/* Employee Grid */

.employee-list {

    display: grid;

    grid-template-columns:
        repeat(auto-fit, minmax(260px, 1fr));

    gap: 20px;

}


/* Employee Card */

.employee-card {

    background: white;

    padding: 22px;

    border-radius: 10px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);

    transition: 0.2s;

}


.employee-card:hover {

    transform: translateY(-3px);

    box-shadow:
        0 6px 15px rgba(0,0,0,0.12);

}


.employee-card h3 {

    margin-top: 0;
    margin-bottom: 10px;

}


.employee-id {

    color: #666;

    font-size: 14px;

    margin-bottom: 20px;

}


/* Buttons */

.button {

    display: inline-block;

    background: #333;

    color: white;

    padding: 10px 16px;

    text-decoration: none;

    border-radius: 5px;

    border: none;

    cursor: pointer;

    font-size: 14px;

}


.button:hover {

    background: #555;

}


/* Admin Button */

.add-button {

    background: #333;

}


/* Delete Button */

.delete-button {

    background: #c0392b;

    color: white;

    padding: 10px 16px;

    border: none;

    border-radius: 5px;

    cursor: pointer;

    font-size: 14px;

    margin-top: 10px;

}


.delete-button:hover {

    background: #a93226;

}


/* Employee Actions */

.employee-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}


/* Empty State */

.empty {

    background: white;

    padding: 40px;

    text-align: center;

    border-radius: 10px;

}


/* Success Message */

.success {

    background: #d4edda;

    color: #155724;

    border: 1px solid #c3e6cb;

    padding: 15px;

    border-radius: 6px;

    margin-bottom: 20px;

}


/* Error Message */

.error {

    background: #f8d7da;

    color: #721c24;

    border: 1px solid #f5c6cb;

    padding: 15px;

    border-radius: 6px;

    margin-bottom: 20px;

}


/* Mobile */

@media (max-width: 700px) {

    .navbar {

        padding: 18px 20px;

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }


    .container {

        padding: 20px;

    }


    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

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
            Credentials
        </a>


        <?php if ($userRole === 'admin'): ?>

            <a href="/manage-users.php">
                Manage Users
            </a>

        <?php endif; ?>


        <a href="/logout.php">
            Logout
        </a>

    </div>

</div>


<!-- Main Content -->

<div class="container">


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


<div class="page-header">


<div>

    <h1>

        <?php
        echo htmlspecialchars(
            $department['department_name']
        );
        ?>

    </h1>


    <p>
        Manage employees and their credentials.
    </p>

</div>


<!-- Only Admin Can Add Employees -->

<?php if ($userRole === 'admin'): ?>

    <a
        href="/add-employee.php?department_id=<?php echo $departmentId; ?>"
        class="button add-button"
    >
        + Add Employee
    </a>

<?php endif; ?>


</div>


<h2>Employees</h2>


<?php if (empty($employees)): ?>


    <div class="empty">

        <h3>
            No employees found.
        </h3>


        <?php if ($userRole === 'admin'): ?>

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


<div class="employee-card">


<h3>

    <?php
    echo htmlspecialchars(
        $employee['employee_name']
    );
    ?>

</h3>


<?php if (!empty($employee['employee_id'])): ?>

    <div class="employee-id">

        Employee ID:
        <strong>

            <?php
            echo htmlspecialchars(
                $employee['employee_id']
            );
            ?>

        </strong>

    </div>

<?php endif; ?>


<div class="employee-actions">


<a
    href="/credentials.php?employee_id=<?php echo $employee['id']; ?>"
    class="button"
>
    View Credentials
</a>


<!-- Only Admin Can Delete Employees -->

<?php if ($userRole === 'admin'): ?>


<form
    method="POST"
    action="/delete-employee.php"
    onsubmit="return confirm('WARNING: Deleting this employee may also delete all credentials assigned to them. Are you sure you want to continue?');"
>


<input
    type="hidden"
    name="employee_id"
    value="<?php echo $employee['id']; ?>"
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


</div>


<?php endforeach; ?>


</div>


<?php endif; ?>


</div>


</body>

</html>
