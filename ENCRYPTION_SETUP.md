# Credential Manager encryption setup

Credential passwords are stored using PHP Sodium `sodium_crypto_secretbox`.
The encryption key is NOT stored in MySQL.

## 1. Generate the key once

On the server:

```bash
openssl rand -base64 32
```

Keep this key private.

## 2. Pass it to the Docker container

The PHP container must have:

```text
CREDENTIAL_ENCRYPTION_KEY=<your-base64-key>
```

Do not put the real key in GitHub.

## 3. Existing plaintext passwords

If the existing database contains plaintext credential passwords, run the
one-time migration script **before relying on Show/Copy**:

```bash
docker exec -it credential-manager php /var/www/migrate-encrypt-passwords.php
```

After it reports the number of encrypted passwords, remove the migration
script from the deployed container/project.

## 4. New passwords

New credentials, password edits, and password-version records are encrypted
before being written to MySQL.

## 5. Show / Copy

The credentials page does not place the encrypted password or plaintext
password in the HTML. Show/Copy calls `show-password.php`, which authenticates
the session and allows only Admin and Editor users to retrieve the secret.

Viewer users can see credential metadata but cannot retrieve the plaintext
password.

## 6. User passwords

Application login passwords are stored as password hashes using PHP
`password_hash()`. An administrator can create another Admin account and can
delete another Admin account. An administrator cannot delete their own
currently logged-in account.
