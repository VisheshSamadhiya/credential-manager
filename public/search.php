<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

/*
|--------------------------------------------------------------------------
| Search Query
|--------------------------------------------------------------------------
|
| Search ONLY:
|
| 1. Employee ID
| 2. Employee Name
| 3. Service Name
|
| Nothing else is searched.
|
*/

$query = trim(
    (string) ($_GET['q'] ?? '')
);

$results = [];

$searchError = null;


/*
|--------------------------------------------------------------------------
| Search Credentials
|--------------------------------------------------------------------------
*/

if ($query !== '') {

    /*
     * Normal substring search.
     *
     * Examples:
     *
     * "Vish"     -> Vishesh
     * "304"      -> Employee ID 304
     * "Gmail"    -> gmail
     * "Chat"     -> Chat GPT
     * "GPT"      -> Chat GPT
     *
     * Passwords are NEVER searched.
     */

    $searchTerm = '%' . $query . '%';


    try {

        $stmt = $pdo->prepare(
            "
            SELECT

                c.id,

                c.employee_id,

                c.service_name,

                c.service_url,

                c.credential_username,

                c.notes,

                c.created_at,

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

            WHERE

                /*
                 * Service Name
                 */
                c.service_name LIKE :search_service

                OR

                /*
                 * Employee Name
                 */
                e.employee_name LIKE :search_employee

                OR

                /*
                 * Employee ID
                 */
                CAST(e.employee_id AS CHAR) LIKE :search_employee_id

            ORDER BY
                c.created_at DESC
            "
        );


        $stmt->execute([

            ':search_service' =>
                $searchTerm,

            ':search_employee' =>
                $searchTerm,

            ':search_employee_id' =>
                $searchTerm

        ]);


        $results =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        error_log(
            'Credential search failed: '
            . $e->getMessage()
        );


        $searchError =
            'Unable to perform the search right now.';


        $results = [];

    }

}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$userId =
    isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : 0;


$user = [
    'full_name' => '',
    'role' => ''
];


try {

    $stmt = $pdo->prepare(
        "
        SELECT
            full_name,
            role
        FROM users
        WHERE id = :id
        LIMIT 1
        "
    );


    $stmt->execute([
        ':id' => $userId
    ]);


    $currentUser =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($currentUser) {

        $user = $currentUser;

    }

} catch (PDOException $e) {

    error_log(
        'Current user lookup failed: '
        . $e->getMessage()
    );

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

<title>
    Search - Credential Manager
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
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

    padding: 18px 40px;

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

    gap: 22px;

    flex-wrap: wrap;

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

    max-width: 1200px;

    margin: 40px auto;

    padding: 0 25px;

}


/*
|--------------------------------------------------------------------------
| Search Card
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


.search-card h1 {

    margin-top: 0;

    margin-bottom: 12px;

}


.search-card p {

    margin-top: 0;

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| Search Form
|--------------------------------------------------------------------------
*/

.search-form {

    display: flex;

    gap: 12px;

}


.search-input {

    flex: 1;

    min-width: 0;

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

    box-shadow:
        0 0 0 3px
        rgba(
            37,
            99,
            235,
            0.10
        );

}


.search-button {

    background: #2563eb;

    color: white;

    border: none;

    padding: 0 25px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 15px;

}


.search-button:hover {

    background: #1d4ed8;

}


/*
|--------------------------------------------------------------------------
| Search Error
|--------------------------------------------------------------------------
*/

.search-error {

    background: #fee2e2;

    color: #991b1b;

    padding: 15px;

    border-radius: 8px;

    margin-bottom: 20px;

}


/*
|--------------------------------------------------------------------------
| Results
|--------------------------------------------------------------------------
*/

.results-title {

    margin-bottom: 10px;

}


.result-summary {

    color: #64748b;

    font-size: 14px;

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

    transition:
        box-shadow 0.2s ease,
        transform 0.2s ease;

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

    transform:
        translateY(-1px);

}


.result-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

}


.service-name {

    font-size: 19px;

    font-weight: 700;

    word-break: break-word;

}


.badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 12px;

    margin-top: 8px;

}


.result-date {

    color: #64748b;

    font-size: 13px;

    white-space: nowrap;

}


/*
|--------------------------------------------------------------------------
| Result Information
|--------------------------------------------------------------------------
*/

.result-info {

    margin-top: 18px;

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(
                180px,
                1fr
            )
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

    word-break: break-word;

}


/*
|--------------------------------------------------------------------------
| Notes
|--------------------------------------------------------------------------
*/

.notes {

    margin-top: 18px;

    padding-top: 15px;

    border-top:
        1px solid
        #edf0f5;

}


.notes-text {

    color: #475569;

    white-space: pre-wrap;

    word-break: break-word;

}


/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

.url-link {

    display: inline-block;

    margin-top: 15px;

    color: #2563eb;

    text-decoration: none;

    word-break: break-all;

}


.url-link:hover {

    text-decoration: underline;

}


/*
|--------------------------------------------------------------------------
| Open Credential Button
|--------------------------------------------------------------------------
*/

.open-button {

    display: inline-block;

    margin-top: 18px;

    padding: 10px 15px;

    background: #1e293b;

    color: white;

    text-decoration: none;

    border-radius: 6px;

}


.open-button:hover {

    background: #334155;

}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    background: white;

    padding: 40px;

    border-radius: 12px;

    text-align: center;

    color: #64748b;

}


.empty h3 {

    color: #475569;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .navbar {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

        padding: 18px 20px;

    }


    .navbar-links {

        gap: 14px;

    }


    .search-form {

        flex-direction: column;

    }


    .search-button {

        padding: 15px;

    }


    .result-header {

        flex-direction: column;

    }


    .result-date {

        white-space: normal;

    }


    .container {

        padding: 0 15px;

    }

}

</style>

    <link rel="stylesheet" href="/assets/theme.css">

</head>


<body>


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


<div class="container">


<div class="search-card">


<h1>
    🔍 Search Credentials
</h1>


<p>
    Search by <strong>Employee ID, Employee Name, or Service Name</strong>.
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

    placeholder="Example: Gmail, Chat GPT, Vishesh, 304..."

    value="<?php
        echo htmlspecialchars(
            $query,
            ENT_QUOTES,
            'UTF-8'
        );
    ?>"

    autocomplete="off"

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


<?php if ($searchError !== null): ?>

<div class="search-error">

    <?php
    echo htmlspecialchars(
        $searchError,
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

</div>

<?php endif; ?>


<?php if ($query !== ''): ?>


<h2 class="results-title">

    Search results for:

    "<?php
    echo htmlspecialchars(
        $query,
        ENT_QUOTES,
        'UTF-8'
    );
    ?>"

</h2>


<?php if (!empty($results)): ?>


<div class="result-summary">

    Found

    <strong>
        <?php echo count($results); ?>
    </strong>

    matching credential<?php
        echo count($results) === 1
            ? ''
            : 's';
    ?>.

</div>


<?php foreach ($results as $result): ?>


<div class="result-card">


<div class="result-header">


<div>


<div class="service-name">

    🔑

    <?php
    echo htmlspecialchars(
        (string) $result['service_name'],
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

</div>


<div class="badge">

    <?php
    echo htmlspecialchars(
        (string) (
            $result['department_name']
            ?? 'No Department'
        ),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

</div>


</div>


<div class="result-date">

    <?php

    $createdTimestamp =
        strtotime(
            (string)
            $result['created_at']
        );


    echo $createdTimestamp !== false

        ? htmlspecialchars(
            date(
                'd M Y',
                $createdTimestamp
            ),
            ENT_QUOTES,
            'UTF-8'
        )

        : '-';

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
            (string) (
                $result['employee_name']
                ?? 'Not Assigned'
            ),
            ENT_QUOTES,
            'UTF-8'
        );
        ?>


        <?php if (
            !empty(
                $result['employee_code']
            )
        ): ?>

            <br>

            <small>

                ID:

                <?php
                echo htmlspecialchars(
                    (string)
                    $result['employee_code'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </small>

        <?php endif; ?>

    </div>

</div>


<div>

    <div class="info-label">
        Username
    </div>


    <div class="info-value">

        <?php
        echo htmlspecialchars(
            (string) (
                $result[
                    'credential_username'
                ]
                ?? '-'
            ),
            ENT_QUOTES,
            'UTF-8'
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
            (string) (
                $result[
                    'created_by_name'
                ]
                ?? 'Unknown'
            ),
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>

</div>


<?php if (
    !empty(
        $result['service_url']
    )
): ?>

<div>

    <div class="info-label">
        URL
    </div>


    <div class="info-value">

        <?php
        echo htmlspecialchars(
            (string)
            $result['service_url'],
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>

</div>

<?php endif; ?>


</div>


<?php if (
    !empty(
        $result['notes']
    )
): ?>

<div class="notes">

    <div class="info-label">
        Notes
    </div>


    <div class="notes-text">

        <?php
        echo htmlspecialchars(
            (string) $result['notes'],
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>

</div>

<?php endif; ?>


<?php if (
    !empty(
        $result['service_url']
    )
): ?>

<a
    class="url-link"
    href="<?php
        echo htmlspecialchars(
            (string) $result['service_url'],
            ENT_QUOTES,
            'UTF-8'
        );
    ?>"
    target="_blank"
    rel="noopener noreferrer"
>
    🌐 Open Service URL
</a>

<?php endif; ?>


<a
    class="open-button"
    href="/credentials.php?employee_id=<?php
        echo (int)
            $result['employee_id'];
    ?>"
>
    View Credentials
</a>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty">

    <h3>
        No results found
    </h3>


    <p>

        No credentials matched

        "<?php
        echo htmlspecialchars(
            $query,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>".

    </p>


    <p>

        Search using an
        <strong>Employee ID</strong>,
        <strong>Employee Name</strong>,
        or
        <strong>Service Name</strong>.

    </p>

</div>


<?php endif; ?>


<?php else: ?>


<div class="empty">

    <h3>
        Start Searching
    </h3>


    <p>

        Search using an
        <strong>Employee ID</strong>,
        <strong>Employee Name</strong>,
        or
        <strong>Service Name</strong>.

    </p>

</div>


<?php endif; ?>


</div>


    <script src="/assets/theme.js"></script>

</body>

</html>
