# Credential Manager - Fresh cPanel Deployment

This package is a clean, independent deployment for one client. It contains application
code and an empty database schema. It contains no production database, production secrets,
Git history, Jenkins files, SSH keys, or Docker configuration.

## 1. Upload the package

Upload and extract the package somewhere in the cPanel account, preferably outside the
public document root, for example:

```text
/home/CPANEL_USER/credential-manager/
```

The package contains:

```text
credential-manager/
├── config/
├── includes/
├── database/
└── public/
```

## 2. Set the domain document root

Set the client's domain/subdomain document root to:

```text
/home/CPANEL_USER/credential-manager/public
```

This is important because `config/` and `includes/` should remain outside the web root.

## 3. Create the MySQL/MariaDB database

In cPanel:

1. Open **MySQL Databases**.
2. Create a database.
3. Create a database user.
4. Assign the user to the database with **ALL PRIVILEGES**.

cPanel normally prefixes names with the account username, for example:

```text
CPANEL_USER_credential_manager
CPANEL_USER_credential_app
```

## 4. Import the fresh schema

Open **phpMyAdmin** in cPanel.

Select the newly created database and import:

```text
database/schema.sql
```

This schema contains no users, employees, departments, or credentials.
It only initializes `app_settings.installation_completed` to `0`.

## 5. Create the private configuration

On the server, copy:

```text
config/client.php.example
```

to:

```text
config/client.php
```

Set:

```php
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'CPANEL_DATABASE_NAME',
    'db_user' => 'CPANEL_DATABASE_USER',
    'db_password' => 'DATABASE_PASSWORD',
    'credential_encryption_key' => 'BASE64_32_BYTE_KEY',
];
```

Generate the encryption key with:

```bash
openssl rand -base64 32
```

If SSH/Terminal is unavailable, generate a 32-byte base64 key using another trusted
random generator and keep it private.

## 6. Complete first-run setup

Open:

```text
https://CLIENT-DOMAIN/setup.php
```

Create the first administrator using:

- Full name
- Email
- Username
- Password
- Confirm password

The application creates the first account with the `admin` role and then locks setup.

## 7. Login

Open:

```text
https://CLIENT-DOMAIN/login.php
```

## Password handling

Application login passwords are one-way hashed with `password_hash()`.

Stored service credentials are reversibly encrypted with PHP Sodium `secretbox` so an
authorized user can retrieve the actual service password later.

When editing a credential:

- leave **New Password** blank to keep the existing password;
- enter a new password to encrypt and replace it.

The database ciphertext is never placed into the edit password field.

## Security

- Keep `config/client.php` outside the public document root.
- Do not reuse a production encryption key.
- Do not import production data.
- Keep a secure backup of `config/client.php`.
- Losing the encryption key makes encrypted credential secrets unrecoverable.
