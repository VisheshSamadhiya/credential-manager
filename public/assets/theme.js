(function () {

    'use strict';

    const STORAGE_KEY =
        'credential_manager_theme';


    function getSavedTheme() {

        return localStorage.getItem(
            STORAGE_KEY
        );

    }


    function systemPrefersDark() {

        return window.matchMedia &&
            window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;

    }


    function getCurrentTheme() {

        return document.documentElement
            .classList
            .contains('dark-theme')
            ? 'dark'
            : 'light';

    }


    function applyTheme(theme) {

        const html =
            document.documentElement;

        if (theme === 'dark') {

            html.classList.add(
                'dark-theme'
            );

        } else {

            html.classList.remove(
                'dark-theme'
            );

        }

        updateButton();

    }


    function updateButton() {

        const button =
            document.getElementById(
                'global-theme-toggle'
            );

        if (!button) {
            return;
        }

        const dark =
            getCurrentTheme() === 'dark';

        button.innerHTML = dark
            ? '<span aria-hidden="true">☀</span>'
            : '<span aria-hidden="true">☾</span>';

        button.setAttribute(
            'aria-label',
            dark
                ? 'Switch to light mode'
                : 'Switch to dark mode'
        );

        button.setAttribute(
            'title',
            dark
                ? 'Switch to light mode'
                : 'Switch to dark mode'
        );

    }


    function toggleTheme() {

        const newTheme =
            getCurrentTheme() === 'dark'
                ? 'light'
                : 'dark';

        localStorage.setItem(
            STORAGE_KEY,
            newTheme
        );

        applyTheme(newTheme);

    }


    /*
     * Apply saved theme immediately.
     */

    const savedTheme =
        getSavedTheme();

    if (
        savedTheme === 'dark' ||
        (
            savedTheme === null &&
            systemPrefersDark()
        )
    ) {

        document.documentElement
            .classList
            .add('dark-theme');

    }


    /*
     * Create theme button.
     */

    function createThemeButton() {

        if (
            document.getElementById(
                'global-theme-toggle'
            )
        ) {
            return;
        }

        const button =
            document.createElement(
                'button'
            );

        button.id =
            'global-theme-toggle';

        button.type =
            'button';

        button.addEventListener(
            'click',
            toggleTheme
        );

        document.body.appendChild(
            button
        );

        updateButton();

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            createThemeButton
        );

    } else {

        createThemeButton();

    }

})();
