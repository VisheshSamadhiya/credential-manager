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
| Only Admin Can Delete Employees
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {

    header(
        'Location: /dashboard.php?access_denied=1'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Only Allow POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: /departments.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| Get Employee and Department IDs
|--------------------------------------------------------------------------
*/

$employeeId = isset($_POST['employee_id'])
    ? (int) $_POST['employee_id']
    : 0;


$departmentId = isset($_POST['department_id'])
    ? (int) $_POST['department_id']
    : 0;


if ($employeeId <= 0 || $departmentId <= 0) {

    header(
        'Location: /departments.php?error=invalid_request'
    );

    exit;

}


try {


    /*
    |--------------------------------------------------------------------------
    | Verify Employee Belongs to Department
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT id
        FROM employees
        WHERE id = :employee_id
        AND department_id = :department_id"
    );


    $stmt->execute([
        ':employee_id' => $employeeId,
        ':department_id' => $departmentId
    ]);


    $employee = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$employee) {

        header(
            'Location: /employees.php?department_id='
            . $departmentId
            . '&error=employee_not_found'
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Delete Employee Credentials First
    |--------------------------------------------------------------------------
    |
    | This is necessary if credentials.employee_id
    | has a foreign key relationship.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "DELETE FROM credentials
        WHERE employee_id = :employee_id"
    );


    $stmt->execute([
        ':employee_id' => $employeeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Delete Employee
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "DELETE FROM employees
        WHERE id = :employee_id"
    );


    $stmt->execute([
        ':employee_id' => $employeeId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    header(
        'Location: /employees.php?department_id='
        . $departmentId
        . '&deleted=1'
    );

    exit;


} catch (PDOException $e) {


    /*
    |--------------------------------------------------------------------------
    | Rollback Transaction
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    header(
        'Location: /employees.php?department_id='
        . $departmentId
        . '&error=delete_failed'
    );

    exit;

}
