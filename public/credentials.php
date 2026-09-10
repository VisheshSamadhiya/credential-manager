<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| Permission Check
|--------------------------------------------------------------------------
*/

if (!canViewCredentials()) {

    header(
        'Location: /dashboard.php?access_denied=view'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Employee ID
|--------------------------------------------------------------------------
*/

$employeeId = isset($_GET['employee_id'])
    ? (int) $_GET['employee_id']
    : 0;

$employee = null;


/*
|--------------------------------------------------------------------------
| Get Employee Information
|--------------------------------------------------------------------------
*/

if ($employeeId > 0) {

    $employeeStmt = $pdo->prepare(
        "SELECT
            employees.id,
            employees.employee_name,
            employees.employee_id,
            employees.department_id,
            departments.department_name
        FROM employees
        LEFT JOIN departments
            ON employees.department_id = departments.id
        WHERE employees.id = :employee_id"
    );

    $employeeStmt->execute([
        ':employee_id' => $employeeId
    ]);

    $employee = $employeeStmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$employee) {

        header(
            'Location: /departments.php'
        );

        exit;
    }

}


/*
|--------------------------------------------------------------------------
| Fetch Credentials
|--------------------------------------------------------------------------
*/

if ($employeeId > 0) {

    $stmt = $pdo->prepare(
        "SELECT
            credentials.id,
            credentials.service_name,
            credentials.service_url,
            credentials.credential_username,
            credentials.credential_password,
            credentials.notes,
            credentials.change_comment,
            credentials.is_new_version,
            credentials.created_at,
            credentials.updated_at,

            employees.employee_name,
            employees.employee_id AS employee_code,

            departments.department_name,

            users.full_name AS created_by_name,
            users.role AS created_by_role

        FROM credentials

        LEFT JOIN users
            ON credentials.created_by = users.id

        LEFT JOIN employees
            ON credentials.employee_id = employees.id

        LEFT JOIN departments
            ON employees.department_id = departments.id

        WHERE credentials.employee_id = :employee_id

        ORDER BY credentials.id DESC"
    );

    $stmt->execute([
        ':employee_id' => $employeeId
    ]);

} else {

    $stmt = $pdo->prepare(
        "SELECT
            credentials.id,
            credentials.service_name,
            credentials.service_url,
            credentials.credential_username,
            credentials.credential_password,
            credentials.notes,
            credentials.change_comment,
            credentials.is_new_version,
            credentials.created_at,
            credentials.updated_at,

            employees.employee_name,
            employees.employee_id AS employee_code,

            departments.department_name,

            users.full_name AS created_by_name,
            users.role AS created_by_role

        FROM credentials

        LEFT JOIN users
            ON credentials.created_by = users.id

        LEFT JOIN employees
            ON credentials.employee_id = employees.id

        LEFT JOIN departments
            ON employees.department_id = departments.id

        ORDER BY credentials.id DESC"
    );

    $stmt->execute();

}


$credentials = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$accessDenied = isset(
    $_GET['access_denied']
);

$updated = isset(
    $_GET['updated']
);

$deleted = isset(
    $_GET['deleted']
);

$added = isset(
    $_GET['added']
);

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
    Credentials - Credential Manager
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f4f6f9;

    color:
        #333;

}


/*
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
*/

.navbar {

    background:
        #2b2b2b;

    padding:
        18px 40px;

    color:
        white;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

}

.navbar-title {

    font-size:
        18px;

    font-weight:
        bold;

}

.navbar-links {

    display:
        flex;

    align-items:
        center;

    gap:
        25px;

}

.navbar a {

    color:
        white;

    text-decoration:
        none;

}

.navbar a:hover {

    text-decoration:
        underline;

}


/*
|--------------------------------------------------------------------------
| Container
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1400px;

    margin:
        auto;

    padding:
        40px;

}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    margin-bottom:
        25px;

}

.header-actions {

    display:
        flex;

    gap:
        10px;

    flex-wrap:
        wrap;

}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

.button {

    display:
        inline-block;

    background:
        #333;

    color:
        white;

    padding:
        10px 16px;

    text-decoration:
        none;

    border-radius:
        5px;

    border:
        none;

    cursor:
        pointer;

}

.button:hover {

    background:
        #555;

}

.add-button {

    display:
        inline-block;

    background:
        #2f7d32;

    color:
        white;

    padding:
        11px 18px;

    text-decoration:
        none;

    border-radius:
        5px;

    font-weight:
        bold;

}

.add-button:hover {

    background:
        #236126;

}

.delete-button {

    background:
        #b00020;

    color:
        white;

    border:
        none;

    padding:
        10px 14px;

    cursor:
        pointer;

    border-radius:
        5px;

}

.delete-button:hover {

    background:
        #850018;

}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

.success {

    background:
        #d4edda;

    color:
        #155724;

    padding:
        15px;

    margin-bottom:
        20px;

    border-radius:
        5px;

}

.access-denied {

    background:
        #fff3cd;

    color:
        #856404;

    padding:
        15px;

    margin-bottom:
        20px;

    border-radius:
        5px;

}


/*
|--------------------------------------------------------------------------
| Employee Information
|--------------------------------------------------------------------------
*/

.employee-info {

    background:
        white;

    padding:
        20px;

    border-radius:
        10px;

    margin-bottom:
        25px;

}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width:
        100%;

    overflow-x:
        auto;

    background:
        white;

    border-radius:
        10px;

}

table {

    width:
        100%;

    border-collapse:
        collapse;

}

th,
td {

    padding:
        15px;

    text-align:
        left;

    border-bottom:
        1px solid #ddd;

    vertical-align:
        top;

}

th {

    background:
        #eeeeee;

}

tr:hover {

    background:
        #f9f9f9;

}


/*
|--------------------------------------------------------------------------
| Password
|--------------------------------------------------------------------------
*/

.password-field {

    min-width:
        260px;

}

.password-actions {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    flex-wrap:
        wrap;

}

.password {

    font-family:
        monospace;

    font-size:
        14px;

    word-break:
        break-all;

}

.toggle-button {

    padding:
        6px 10px;

    border:
        none;

    cursor:
        pointer;

    border-radius:
        4px;

    background:
        #e5e5e5;

}

.copy-button {

    padding:
        6px 10px;

    border:
        none;

    cursor:
        pointer;

    border-radius:
        4px;

    background:
        #333;

    color:
        white;

}

.copy-button:hover {

    background:
        #555;

}

.copy-button:disabled {

    background:
        #999;

    cursor:
        not-allowed;

}

.clipboard-status {

    margin-top:
        8px;

    font-size:
        12px;

    min-height:
        18px;

    color:
        #555;

}


/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

.url-link {

    color:
        #0066cc;

    text-decoration:
        none;

}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

.actions {

    display:
        flex;

    gap:
        8px;

    align-items:
        center;

    flex-wrap:
        wrap;

}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    background:
        white;

    padding:
        40px;

    margin-top:
        20px;

    text-align:
        center;

    border-radius:
        10px;

}


/*
|--------------------------------------------------------------------------
| Version Comment
|--------------------------------------------------------------------------
*/

.version-comment {

    margin-top:
        5px;

    font-size:
        12px;

    color:
        #666;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .navbar {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;

        padding:
            20px;

    }

    .navbar-links {

        flex-wrap:
            wrap;

        gap:
            15px;

    }

    .container {

        padding:
            20px;

    }

    .header {

        flex-direction:
            column;

        align-items:
            flex-start;

    }

}

</style>

</head>


<body>


<!-- Navbar -->

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


<!-- Main Container -->

<div class="container">


<div class="header">


<div>

    <h1>

        <?php if ($employee): ?>

            Credentials

        <?php else: ?>

            All Credentials

        <?php endif; ?>

    </h1>

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


<!-- Employee Information -->

<?php if ($employee): ?>

<div class="employee-info">

    <h3>

        Employee:

        <?php
        echo htmlspecialchars(
            $employee['employee_name']
        );
        ?>

    </h3>


    <?php if (!empty($employee['employee_id'])): ?>

        <p>

            <strong>
                Employee ID:
            </strong>

            <?php
            echo htmlspecialchars(
                $employee['employee_id']
            );
            ?>

        </p>

    <?php endif; ?>


    <p>

        <strong>
            Department:
        </strong>

        <?php
        echo htmlspecialchars(
            $employee['department_name']
        );
        ?>

    </p>

</div>

<?php endif; ?>


<!-- Messages -->

<?php if ($accessDenied): ?>

<div class="access-denied">

    Access denied. You do not have permission to perform that action.

</div>

<?php endif; ?>


<?php if ($added): ?>

<div class="success">

    Credential added successfully.

</div>

<?php endif; ?>


<?php if ($updated): ?>

<div class="success">

    Credential updated successfully.

</div>

<?php endif; ?>


<?php if ($deleted): ?>

<div class="success">

    Credential deleted successfully.

</div>

<?php endif; ?>


<!-- Credentials -->

<?php if (empty($credentials)): ?>


<div class="empty">

    <h3>
        No credentials found.
    </h3>


    <?php if (
        canAddCredentials()
        &&
        $employeeId > 0
    ): ?>

        <p>

            <a
                href="/add-credential.php?employee_id=<?php echo (int) $employeeId; ?>"
                class="add-button"
            >
                + Add First Credential
            </a>

        </p>

    <?php endif; ?>


</div>


<?php else: ?>


<div class="table-wrapper">


<table>


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


<tr>


<!-- Service -->

<td>

    <strong>

        <?php
        echo htmlspecialchars(
            $credential['service_name']
        );
        ?>

    </strong>


    <?php if (
        !empty($credential['is_new_version'])
    ): ?>

        <div class="version-comment">

            New Password Version

        </div>

    <?php endif; ?>


    <?php if (
        !empty($credential['change_comment'])
    ): ?>

        <div class="version-comment">

            <?php
            echo htmlspecialchars(
                $credential['change_comment']
            );
            ?>

        </div>

    <?php endif; ?>

</td>


<!-- Employee -->

<?php if ($employeeId === 0): ?>

<td>

    <?php
    echo htmlspecialchars(
        $credential['employee_name']
        ?? 'Unknown'
    );
    ?>


    <?php if (
        !empty(
            $credential['employee_code']
        )
    ): ?>

        <br>

        <small>

            <?php
            echo htmlspecialchars(
                $credential['employee_code']
            );
            ?>

        </small>

    <?php endif; ?>

</td>


<!-- Department -->

<td>

    <?php
    echo htmlspecialchars(
        $credential['department_name']
        ?? 'Unknown'
    );
    ?>

</td>

<?php endif; ?>


<!-- Username -->

<td>

    <?php
    echo htmlspecialchars(
        $credential[
            'credential_username'
        ]
        ?? ''
    );
    ?>

</td>


<!-- Password -->

<td class="password-field">


<div class="password-actions">


<span
    class="password"
    data-credential-id="<?php echo (int) $credential['id']; ?>"
    data-visible="0"
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


<button
    type="button"
    class="copy-button"
    onclick="copyPassword(this)"
>
    Copy
</button>


</div>


<div class="clipboard-status">

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
        href="<?php echo htmlspecialchars(
            $credential[
                'service_url'
            ],
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
        target="_blank"
        rel="noopener noreferrer"
    >
        Open
    </a>

<?php else: ?>

    —

<?php endif; ?>

</td>


<!-- Notes -->

<td>

    <?php
    echo htmlspecialchars(
        $credential['notes']
        ?? ''
    );
    ?>

</td>


<!-- Created By -->

<td>

    <?php
    echo htmlspecialchars(
        $credential[
            'created_by_name'
        ]
        ?? 'Unknown'
    );
    ?>

</td>


<!-- Created -->

<td>

    <?php
    echo htmlspecialchars(
        $credential['created_at']
    );
    ?>

</td>


<!-- Actions -->

<td>


<div class="actions">


<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>

    <a
        class="button"
        href="/edit-credential.php?id=<?php echo (int) $credential['id']; ?>"
    >
        Edit
    </a>

<?php endif; ?>


<?php if (
    in_array(
        ($_SESSION['role'] ?? 'viewer'),
        ['admin', 'editor'],
        true
    )
): ?>

    <a
        class="button"
        href="/add-password-version.php?id=<?php echo (int) $credential['id']; ?>"
    >
        + New Password
    </a>

<?php endif; ?>


<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>

    <form
        method="POST"
        action="/delete-credential.php"
        onsubmit="return confirm('Are you sure you want to delete this credential?');"
    >

        <input
            type="hidden"
            name="id"
            value="<?php echo (int) $credential['id']; ?>"
        >


        <button
            type="submit"
            class="delete-button"
        >
            Delete
        </button>

    </form>

<?php endif; ?>


<?php if (($_SESSION['role'] ?? '') === 'viewer'): ?>

    <span>
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


<?php endif; ?>


</div>


<script>
async function fetchCredentialPassword(id, action) {
    const response = await fetch(
        'show-password.php?id=' + encodeURIComponent(id) +
        '&action=' + encodeURIComponent(action || 'view'),
        {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }
    );

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Unable to access password.');
    }

    return data.password;
}

function togglePassword(button) {
    const passwordField = button.closest('.password-field');
    const passwordSpan = passwordField.querySelector('.password');
    const id = passwordSpan.dataset.credentialId;

    if (passwordSpan.dataset.visible === '1') {
        passwordSpan.textContent = '••••••••';
        passwordSpan.dataset.visible = '0';
        button.textContent = 'Show';

        if (passwordSpan._hideTimer) {
            clearTimeout(passwordSpan._hideTimer);
            passwordSpan._hideTimer = null;
        }
        return;
    }

    button.disabled = true;
    button.textContent = 'Loading...';

    fetchCredentialPassword(id, 'view')
        .then(function(password) {
            passwordSpan.textContent = password;
            passwordSpan.dataset.visible = '1';
            button.textContent = 'Hide';

            passwordSpan._hideTimer = setTimeout(function() {
                passwordSpan.textContent = '••••••••';
                passwordSpan.dataset.visible = '0';
                button.textContent = 'Show';
                passwordSpan._hideTimer = null;
            }, 30000);
        })
        .catch(function(error) {
            alert(error.message);
            button.textContent = 'Show';
        })
        .finally(function() {
            button.disabled = false;
        });
}

async function copyPassword(button) {
    const passwordField = button.closest('.password-field');
    const passwordSpan = passwordField.querySelector('.password');
    const status = passwordField.querySelector('.clipboard-status');
    const id = passwordSpan.dataset.credentialId;

    if (button.disabled) return;

    try {
        button.disabled = true;
        const password = await fetchCredentialPassword(id, 'copy');

        try {
            await navigator.clipboard.writeText(password);
        } catch (error) {
            const temporaryInput = document.createElement('textarea');
            temporaryInput.value = password;
            temporaryInput.style.position = 'fixed';
            temporaryInput.style.opacity = '0';
            document.body.appendChild(temporaryInput);
            temporaryInput.focus();
            temporaryInput.select();

            if (!document.execCommand('copy')) {
                throw new Error('Copy failed');
            }

            document.body.removeChild(temporaryInput);
        }

        button.textContent = 'Copied ✓';
        let remainingSeconds = 30;
        status.textContent =
            'Password copied. Clipboard clears in ' +
            remainingSeconds + ' seconds.';

        const countdown = setInterval(async function() {
            remainingSeconds--;

            if (remainingSeconds > 0) {
                status.textContent =
                    'Password copied. Clipboard clears in ' +
                    remainingSeconds + ' seconds.';
                return;
            }

            clearInterval(countdown);

            try {
                await navigator.clipboard.writeText('');
                status.textContent = 'Clipboard cleared successfully.';
            } catch (error) {
                status.textContent = 'Clipboard timer expired.';
            }

            button.disabled = false;
            button.textContent = 'Copy';
        }, 1000);
    } catch (error) {
        status.textContent = error.message || 'Unable to copy password.';
        button.disabled = false;
        button.textContent = 'Copy';
    }
}


</script>
