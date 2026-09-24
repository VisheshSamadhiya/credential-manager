/*
 * =========================================================
 * CREDENTIAL MANAGER
 * GLOBAL THEME CONTROLLER
 * =========================================================
 *
 * - Dark mode is the default.
 * - Theme persists between pages.
 * - Does not modify page layout.
 * - Does not inject page content.
 * - No emoji icons.
 * =========================================================
 */

(function () {
    "use strict";

    const STORAGE_KEY = "credential-manager-theme";

    const DARK = "dark";
    const LIGHT = "light";


    /* =====================================================
       READ STORED THEME
       ===================================================== */

    function getStoredTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);

            if (stored === DARK || stored === LIGHT) {
                return stored;
            }

            /* Compatibility with an older theme key */
            const legacy = localStorage.getItem("theme");

            if (legacy === DARK || legacy === LIGHT) {
                return legacy;
            }
        } catch (error) {
            /* Storage may be unavailable */
        }

        return DARK;
    }


    /* =====================================================
       APPLY THEME
       ===================================================== */

    function applyTheme(theme) {
        const root = document.documentElement;

        const isDark = theme === DARK;

        root.classList.toggle("dark-theme", isDark);
        root.classList.toggle("light-theme", !isDark);

        root.setAttribute(
            "data-theme",
            isDark ? DARK : LIGHT
        );

        root.style.colorScheme =
            isDark ? DARK : LIGHT;

        updateThemeButtons(isDark);
    }


    /* =====================================================
       SAVE THEME
       ===================================================== */

    function saveTheme(theme) {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                theme
            );
        } catch (error) {
            /* Ignore storage errors */
        }
    }


    /* =====================================================
       TOGGLE
       ===================================================== */

    function toggleTheme() {
        const isDark =
            document.documentElement.classList.contains(
                "dark-theme"
            );

        const nextTheme =
            isDark ? LIGHT : DARK;

        saveTheme(nextTheme);
        applyTheme(nextTheme);
    }


    /* =====================================================
       UPDATE BUTTON ACCESSIBILITY
       ===================================================== */

    function updateThemeButtons(isDark) {
        const buttons =
            document.querySelectorAll(
                ".theme-toggle, [data-theme-toggle]"
            );

        buttons.forEach(function (button) {
            const nextTheme =
                isDark ? "light" : "dark";

            const label =
                isDark
                    ? "Switch to light theme"
                    : "Switch to dark theme";

            button.setAttribute(
                "aria-label",
                label
            );

            button.setAttribute(
                "title",
                label
            );

            button.dataset.themeState =
                isDark ? DARK : LIGHT;

            button.dataset.nextTheme =
                nextTheme;
        });
    }


    /* =====================================================
       CREATE GLOBAL TOGGLE IF PAGE DOES NOT HAVE ONE
       ===================================================== */

    function createToggleIfMissing() {
        const existing =
            document.querySelector(
                ".theme-toggle, [data-theme-toggle]"
            );

        if (existing) {
            return;
        }

        if (!document.body) {
            return;
        }

        const button =
            document.createElement("button");

        button.type = "button";

        button.className =
            "theme-toggle";

        button.setAttribute(
            "data-theme-toggle",
            ""
        );

        button.setAttribute(
            "aria-label",
            "Switch theme"
        );

        button.setAttribute(
            "title",
            "Switch theme"
        );

        document.body.appendChild(button);
    }


    /* =====================================================
       BIND BUTTONS
       ===================================================== */

    function bindThemeButtons() {
        const buttons =
            document.querySelectorAll(
                ".theme-toggle, [data-theme-toggle]"
            );

        buttons.forEach(function (button) {
            if (
                button.dataset.themeBound === "1"
            ) {
                return;
            }

            button.dataset.themeBound = "1";

            button.addEventListener(
                "click",
                function () {
                    toggleTheme();
                }
            );
        });

        updateThemeButtons(
            document.documentElement.classList.contains(
                "dark-theme"
            )
        );
    }


    /* =====================================================
       APPLY BEFORE PAGE LOAD
       ===================================================== */

    applyTheme(
        getStoredTheme()
    );


    /* =====================================================
       DOM READY
       ===================================================== */

    document.addEventListener(
        "DOMContentLoaded",
        function () {
            createToggleIfMissing();
            bindThemeButtons();
        }
    );


    /* =====================================================
       SUPPORT DYNAMICALLY ADDED BUTTONS
       ===================================================== */

    if (
        typeof MutationObserver !== "undefined"
    ) {
        const observer =
            new MutationObserver(
                function () {
                    bindThemeButtons();
                }
            );

        observer.observe(
            document.documentElement,
            {
                childList: true,
                subtree: true
            }
        );
    }

})();
