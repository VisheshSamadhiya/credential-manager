<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}


function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

function hasRole(string $role): bool
{
    return ($_SESSION['role'] ?? '') === $role;
}


function hasAnyRole(array $roles): bool
{
    return in_array(
        $_SESSION['role'] ?? '',
        $roles,
        true
    );
}


function requireAdmin(): void
{
    requireLogin();

    if (!hasRole('admin')) {
        header('Location: /dashboard.php?access_denied=admin');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
*/

function canManageUsers(): bool
{
    return hasRole('admin');
}


function canManageDepartments(): bool
{
    return hasRole('admin');
}


function canAddEmployees(): bool
{
    return hasRole('admin');
}


function canAddCredentials(): bool
{
    return hasAnyRole([
        'admin',
        'editor'
    ]);
}


function canEditCredentials(): bool
{
    return hasAnyRole([
        'admin',
        'editor'
    ]);
}


function canDeleteCredentials(): bool
{
    return hasRole('admin');
}


function canViewCredentials(): bool
{
    return hasAnyRole([
        'admin',
        'editor',
        'viewer'
    ]);
}
