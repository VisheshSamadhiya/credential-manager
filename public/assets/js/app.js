/* =========================================================
   Credential Manager
   Shared JavaScript
   ========================================================= */


/*
|--------------------------------------------------------------------------
| Get Password Input
|--------------------------------------------------------------------------
*/

function getPasswordInput() {

    return (

        document.getElementById(
            'credentialPassword'
        )

        ||

        document.getElementById(
            'new_password'
        )

    );

}


/*
|--------------------------------------------------------------------------
| Get Toggle Button
|--------------------------------------------------------------------------
*/

function getPasswordToggleButton() {

    return document.querySelector(
        '.toggle-password'
    );

}


/*
|--------------------------------------------------------------------------
| Toggle Password
|--------------------------------------------------------------------------
*/

function togglePassword() {


    const passwordInput =
        getPasswordInput();


    const button =
        getPasswordToggleButton();


    if (
        !passwordInput
        ||
        !button
    ) {

        return;

    }


    const isHidden =
        passwordInput.type ===
        'password';


    if (isHidden) {

        passwordInput.type =
            'text';

        button.textContent =
            'Hide';

        button.setAttribute(
            'aria-label',
            'Hide password'
        );

        button.setAttribute(
            'title',
            'Hide password'
        );

    } else {

        passwordInput.type =
            'password';

        button.textContent =
            'Show';

        button.setAttribute(
            'aria-label',
            'Show password'
        );

        button.setAttribute(
            'title',
            'Show password'
        );

    }

}


/*
|--------------------------------------------------------------------------
| Generate Password
|--------------------------------------------------------------------------
*/

function generatePassword() {


    const passwordInput =
        document.getElementById(
            'credentialPassword'
        );


    if (!passwordInput) {

        return;

    }


    const uppercase =
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ';


    const lowercase =
        'abcdefghijklmnopqrstuvwxyz';


    const numbers =
        '0123456789';


    const symbols =
        '!@#$%^&*()_+-=[]{}|;:,.<>?';


    const allCharacters =
        uppercase
        +
        lowercase
        +
        numbers
        +
        symbols;


    let password = '';


    /*
    |--------------------------------------------------------------------------
    | Guarantee character types
    |--------------------------------------------------------------------------
    */

    password +=
        uppercase[
            Math.floor(
                Math.random()
                *
                uppercase.length
            )
        ];


    password +=
        lowercase[
            Math.floor(
                Math.random()
                *
                lowercase.length
            )
        ];


    password +=
        numbers[
            Math.floor(
                Math.random()
                *
                numbers.length
            )
        ];


    password +=
        symbols[
            Math.floor(
                Math.random()
                *
                symbols.length
            )
        ];


    /*
    |--------------------------------------------------------------------------
    | Fill Remaining Characters
    |--------------------------------------------------------------------------
    */

    for (
        let i = password.length;
        i < 16;
        i++
    ) {

        password +=
            allCharacters[
                Math.floor(
                    Math.random()
                    *
                    allCharacters.length
                )
            ];

    }


    /*
    |--------------------------------------------------------------------------
    | Shuffle Password
    |--------------------------------------------------------------------------
    */

    password =
        password
            .split('')
            .sort(
                () =>
                    Math.random()
                    -
                    0.5
            )
            .join('');


    /*
    |--------------------------------------------------------------------------
    | Set Password
    |--------------------------------------------------------------------------
    */

    passwordInput.value =
        password;


    /*
    |--------------------------------------------------------------------------
    | Show Generated Password
    |--------------------------------------------------------------------------
    */

    passwordInput.type =
        'text';


    const button =
        getPasswordToggleButton();


    if (button) {

        button.textContent =
            'Hide';

        button.setAttribute(
            'aria-label',
            'Hide password'
        );

        button.setAttribute(
            'title',
            'Hide password'
        );

    }


    checkPasswordStrength();

}


/*
|--------------------------------------------------------------------------
| Password Strength Checker
|--------------------------------------------------------------------------
*/

function checkPasswordStrength() {


    const passwordInput =
        document.getElementById(
            'credentialPassword'
        );


    const strengthElement =
        document.getElementById(
            'strength'
        );


    if (
        !passwordInput
        ||
        !strengthElement
    ) {

        return;

    }


    const password =
        passwordInput.value;


    /*
    |--------------------------------------------------------------------------
    | Empty Password
    |--------------------------------------------------------------------------
    */

    if (
        password.length === 0
    ) {

        strengthElement.textContent =
            '';

        strengthElement.style.color =
            '';

        return;

    }


    let score = 0;


    /*
    |--------------------------------------------------------------------------
    | Length
    |--------------------------------------------------------------------------
    */

    if (
        password.length >= 8
    ) {

        score++;

    }


    /*
    |--------------------------------------------------------------------------
    | Uppercase
    |--------------------------------------------------------------------------
    */

    if (
        /[A-Z]/.test(password)
    ) {

        score++;

    }


    /*
    |--------------------------------------------------------------------------
    | Lowercase
    |--------------------------------------------------------------------------
    */

    if (
        /[a-z]/.test(password)
    ) {

        score++;

    }


    /*
    |--------------------------------------------------------------------------
    | Number
    |--------------------------------------------------------------------------
    */

    if (
        /[0-9]/.test(password)
    ) {

        score++;

    }


    /*
    |--------------------------------------------------------------------------
    | Special Character
    |--------------------------------------------------------------------------
    */

    if (
        /[^A-Za-z0-9]/.test(password)
    ) {

        score++;

    }


    /*
    |--------------------------------------------------------------------------
    | Result
    |--------------------------------------------------------------------------
    */

    if (score <= 2) {

        strengthElement.textContent =
            'Password Strength: Weak';

        strengthElement.style.color =
            '#b00020';

    }

    else if (score <= 4) {

        strengthElement.textContent =
            'Password Strength: Medium';

        strengthElement.style.color =
            '#996600';

    }

    else {

        strengthElement.textContent =
            'Password Strength: Strong';

        strengthElement.style.color =
            '#198754';

    }

}


/*
|--------------------------------------------------------------------------
| Page Initialization
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const passwordInput =
            getPasswordInput();


        if (passwordInput) {


            passwordInput.addEventListener(
                'input',
                function () {

                    checkPasswordStrength();

                }
            );


        }


    }
);