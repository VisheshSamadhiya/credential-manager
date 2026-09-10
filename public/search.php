<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Search Query
|--------------------------------------------------------------------------
*/

$query = trim($_GET['q'] ?? '');

$results = [];


/*
|--------------------------------------------------------------------------
| Search Credentials
|--------------------------------------------------------------------------
*/

if ($query !== '') {

    $searchTerm = '%' . $query . '%';


    try {

        $stmt = $pdo->prepare(
            "SELECT
                credentials.id,
                credentials.service_name,
                credentials.service_url,
                credentials.credential_username,
                credentials.notes,
                credentials.created_at,

                employees.employee_name,

                departments.department_name,

                users.full_name AS created_by_name

            FROM credentials


            LEFT JOIN employees

                ON credentials.employee_id = employees.id


            LEFT JOIN departments

                ON employees.department_id = departments.id


            LEFT JOIN users

                ON credentials.created_by = users.id


            WHERE

                credentials.service_name
                    LIKE :search

                OR

                credentials.credential_username
                    LIKE :search

                OR

                credentials.notes
                    LIKE :search

                OR

                employees.employee_name
                    LIKE :search

                OR

                departments.department_name
                    LIKE :search


            ORDER BY
                credentials.created_at DESC"
        );


        $stmt->execute([
            ':search' => $searchTerm
        ]);


        $results =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


    } catch (PDOException $e) {

        $results = [];

    }

}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare(
    "SELECT
        full_name,
        role
    FROM users
    WHERE id = :id"
);

$stmt->execute([
    ':id' => $userId
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Search - Credential Manager</title>


<style>

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
| Navbar
|--------------------------------------------------------------------------
*/

.navbar {

    background: #1e293b;

    color: white;

    padding:

        18px 40px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.navbar-title {

    font-size: 20px;

    font-weight: bold;

}


.navbar-links {

    display: flex;

    gap: 22px;

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
| Main
|--------------------------------------------------------------------------
*/

.container {

    max-width: 1200px;

    margin:

        40px auto;

    padding:

        0 25px;

}


/*
|--------------------------------------------------------------------------
| Search Box
|--------------------------------------------------------------------------
*/

.search-card {

    background: white;

    padding: 30px;

    border-radius: 14px;

    margin-bottom: 30px;

    box-shadow:

        0 4px 15px
        rgba(
            0,
            0,
            0,
            0.05
        );

}


.search-form {

    display: flex;

    gap: 12px;

}


.search-input {

    flex: 1;

    padding: 15px;

    border:

        1px solid
        #dbe1ea;

    border-radius: 8px;

    font-size: 16px;

    outline: none;

}


.search-input:focus {

    border-color: #2563eb;

}


.search-button {

    background: #2563eb;

    color: white;

    border: none;

    padding:

        0 25px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 15px;

}


.search-button:hover {

    background: #1d4ed8;

}


/*
|--------------------------------------------------------------------------
| Results
|--------------------------------------------------------------------------
*/

.results-title {

    margin-bottom: 20px;

}


.result-card {

    background: white;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 15px;

    border:

        1px solid
        #edf0f5;

    transition: 0.2s;

}


.result-card:hover {

    box-shadow:

        0 6px 18px
        rgba(
            0,
            0,
            0,
            0.08
        );

}


.result-header {

    display: flex;

    justify-content: space-between;

    gap: 20px;

}


.service-name {

    font-size: 19px;

    font-weight: 700;

}


.badge {

    display: inline-block;

    padding:

        5px 10px;

    border-radius: 20px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 12px;

    margin-top: 8px;

}


.result-info {

    margin-top: 18px;

    display: grid;

    grid-template-columns:

        repeat(
            auto-fit,
            minmax(180px, 1fr)
        );

    gap: 15px;

}


.info-label {

    font-size: 12px;

    color: #64748b;

    margin-bottom: 5px;

}


.info-value {

    font-weight: 500;

}


.open-button {

    display: inline-block;

    margin-top: 18px;

    padding:

        10px 15px;

    background: #1e293b;

    color: white;

    text-decoration: none;

    border-radius: 6px;

}


.open-button:hover {

    background: #334155;

}


.empty {

    background: white;

    padding: 40px;

    border-radius: 12px;

    text-align: center;

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .navbar {

        flex-direction: column;

        gap: 15px;

    }


    .navbar-links {

        flex-wrap: wrap;

        justify-content: center;

    }


    .search-form {

        flex-direction: column;

    }


    .search-button {

        padding: 15px;

    }

}

</style>

</head>


<body>


<!-- Navbar -->

<div class="navbar">


<div class="navbar-title">

    🔐 Credential Manager

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


<a href="/logout.php">
Logout
</a>


</div>


</div>


<!-- Content -->

<div class="container">


<!-- Search Card -->

<div class="search-card">


<h1>
🔍 Search Credentials
</h1>


<p>

Search by service, username, employee, department, or notes.

</p>


<form
    method="GET"
    action="/search.php"
    class="search-form"
>


<input

    type="text"

    name="q"

    class="search-input"

    placeholder="Example: WordPress, Vishesh, IT Department..."

    value="<?php echo htmlspecialchars($query); ?>"

    autofocus

>


<button
    type="submit"
    class="search-button"
>

Search

</button>


</form>


</div>


<!-- Search Results -->

<?php if ($query !== ''): ?>


<h2 class="results-title">

Search results for:

"<?php echo htmlspecialchars($query); ?>"

</h2>


<?php if (empty($results)): ?>


<div class="empty">

<h3>
No results found
</h3>


<p>

Try searching using a different service, employee, or department name.

</p>


</div>


<?php else: ?>


<?php foreach ($results as $result): ?>


<div class="result-card">


<div class="result-header">


<div>


<div class="service-name">

🔑

<?php
echo htmlspecialchars(
    $result['service_name']
);
?>

</div>


<div class="badge">

<?php
echo htmlspecialchars(
    $result['department_name']
    ?? 'No Department'
);
?>

</div>


</div>


<div>

<?php
echo htmlspecialchars(
    date(
        'd M Y',
        strtotime(
            $result['created_at']
        )
    )
);
?>

</div>


</div>


<div class="result-info">


<div>

<div class="info-label">

Employee

</div>


<div class="info-value">

<?php
echo htmlspecialchars(
    $result['employee_name']
    ?? 'Not Assigned'
);
?>

</div>


</div>


<div>

<div class="info-label">

Username

</div>


<div class="info-value">

<?php
echo htmlspecialchars(
    $result['credential_username']
    ?? '-'
);
?>

</div>


</div>


<div>

<div class="info-label">

Added By

</div>


<div class="info-value">

<?php
echo htmlspecialchars(
    $result['created_by_name']
    ?? 'Unknown'
);
?>

</div>


</div>


</div>


<a
    class="open-button"
    href="/credentials.php?employee_id=<?php echo (int) ($result['employee_id'] ?? 0); ?>"
>

View Credentials

</a>


</div>


<?php endforeach; ?>


<?php endif; ?>


<?php else: ?>


<div class="empty">

<h3>
Start Searching
</h3>


<p>

Use the search bar above to find credentials.

</p>


</div>


<?php endif; ?>


</div>


</body>

</html>
