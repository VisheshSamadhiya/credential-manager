/*
|--------------------------------------------------------------------------
| Password Show / Hide
|--------------------------------------------------------------------------
*/

function togglePassword() {

    const passwordInput =
        document.getElementById('credentialPassword') ||
        document.getElementById('new_password');

    const button =
        document.querySelector('.toggle-password');

    if (!passwordInput || !button) {
        return;
    }

    if (passwordInput.type === 'password') {

        passwordInput.type = 'text';

        button.textContent = 'Hide';

    } else {

        passwordInput.type = 'password';

        button.textContent = 'Show';
    }
}


/*
|--------------------------------------------------------------------------
| Password Generator
|--------------------------------------------------------------------------
*/

function generatePassword() {

    const characters =
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ' +
        'abcdefghijklmnopqrstuvwxyz' +
        '0123456789' +
        '!@#$%^&*()_+-=';

    let password = '';

    for (let i = 0; i < 16; i++) {

        const randomIndex =
            Math.floor(
                Math.random() * characters.length
            );

        password +=
            characters.charAt(randomIndex);
    }

    const passwordInput =
        document.getElementById('credentialPassword');

    if (!passwordInput) {
        return;
    }

    passwordInput.value = password;

    checkPasswordStrength();
}


/*
|--------------------------------------------------------------------------
| Password Strength Checker
|--------------------------------------------------------------------------
*/

function checkPasswordStrength() {

    const passwordInput =
        document.getElementById('credentialPassword');

    const strengthElement =
        document.getElementById('strength');

    if (!passwordInput || !strengthElement) {
        return;
    }

    const password = passwordInput.value;

    let score = 0;

    if (password.length >= 8) {
        score++;
    }

    if (/[A-Z]/.test(password)) {
        score++;
    }

    if (/[a-z]/.test(password)) {
        score++;
    }

    if (/[0-9]/.test(password)) {
        score++;
    }

    if (/[^A-Za-z0-9]/.test(password)) {
        score++;
    }

    if (password.length === 0) {

        strengthElement.textContent = '';

    } else if (score <= 2) {

        strengthElement.textContent =
            'Password Strength: Weak';

    } else if (score <= 4) {

        strengthElement.textContent =
            'Password Strength: Medium';

    } else {

        strengthElement.textContent =
            'Password Strength: Strong';
    }
}


/*
|--------------------------------------------------------------------------
| Password Strength - Live Input
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {

    const passwordInput =
        document.getElementById('credentialPassword');

    if (passwordInput) {

        passwordInput.addEventListener(
            'input',
            checkPasswordStrength
        );
    }
});