<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Admin Permission Check
|--------------------------------------------------------------------------
*/

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Only administrators can manage departments.');
}


/*
|--------------------------------------------------------------------------
| Add Department
|--------------------------------------------------------------------------
*/

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $departmentName = trim($_POST['department_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($departmentName === '') {

        $message = 'Department name is required.';

    } else {

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO departments
                (department_name, description)
                VALUES (:department_name, :description)"
            );

            $stmt->execute([
                ':department_name' => $departmentName,
                ':description' => $description
            ]);

            header('Location: /manage-departments.php?success=1');
            exit;

        } catch (PDOException $e) {

            $message = 'Department already exists or could not be created.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Departments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT *
    FROM departments
    ORDER BY department_name ASC"
);

$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html>

<head>

    <title>Manage Departments</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .navbar {
            background: #2b2b2b;
            padding: 18px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        button {
            background: #333;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eeeeee;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

    </style>

</head>

<body>


<div class="navbar">

    <div>
        Credential Manager
    </div>

    <div>

        <a href="/dashboard.php">Dashboard</a>

        <a href="/credentials.php">Credentials</a>

        <a href="/logout.php">Logout</a>

    </div>

</div>


<div class="container">

    <h1>Manage Departments</h1>


    <?php if (isset($_GET['success'])): ?>

        <div class="success">
            Department created successfully.
        </div>

    <?php endif; ?>


    <?php if ($message): ?>

        <div class="error">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <h2>Add Department</h2>


        <form method="POST">

            <label>
                Department Name
            </label>

            <input
                type="text"
                name="department_name"
                required
            >


            <label>
                Description
            </label>

            <textarea
                name="description"
                placeholder="Optional department description"
            ></textarea>


            <button type="submit">

                Add Department

            </button>

        </form>

    </div>


    <div class="card">

        <h2>Existing Departments</h2>


        <?php if (empty($departments)): ?>

            <p>No departments created yet.</p>

        <?php else: ?>


            <table>

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Department</th>

                        <th>Description</th>

                        <th>Created</th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach ($departments as $department): ?>


                        <tr>

                            <td>

                                <?php
                                echo htmlspecialchars($department['id']);
                                ?>

                            </td>


                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $department['department_name']
                                    );
                                    ?>

                                </strong>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $department['description'] ?? ''
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $department['created_at']
                                );
                                ?>

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
