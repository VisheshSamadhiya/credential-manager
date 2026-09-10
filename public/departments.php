<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$stmt = $pdo->query(
    "SELECT id, department_name
     FROM departments
     ORDER BY department_name ASC"
);

$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Departments</title>

    <style>

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

        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .department-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
        }

        .department-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
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
        <a href="/departments.php">Departments</a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="/manage-departments.php">Manage Departments</a>
        <?php endif; ?>

        <a href="/logout.php">Logout</a>
    </div>

</div>


<div class="container">

    <h1>Select Department</h1>

    <?php if (empty($departments)): ?>

        <p>No departments available.</p>

    <?php else: ?>

        <div class="department-grid">

            <?php foreach ($departments as $department): ?>

                <a
                    class="department-card"
                    href="/employees.php?department_id=<?php echo $department['id']; ?>"
                >

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $department['department_name']
                        );
                        ?>
                    </h2>

                    <p>View Employees →</p>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

</body>

</html>
