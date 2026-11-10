/*
 * DiSSENT account forms (register, login, forgot/reset password, profile).
 *
 * - Eye button inside password boxes: show / hide the password.
 * - Live password checklist (rules must match password_rule_failures()
 *   in backend/content.php; PHP always checks again on submit).
 * - Live "Passwords match / do not match" message.
 * - "Please wait" state on submit to avoid double submissions.
 *
 * Passwords are never stored, logged or sent anywhere by this script.
 */
(function () {
    "use strict";

    var RULES = {
        length: function (value) { return Array.from(value).length >= 8; },
        upper: function (value) { return /\p{Lu}/u.test(value); },
        lower: function (value) { return /\p{Ll}/u.test(value); },
        number: function (value) { return /\p{Nd}/u.test(value); },
        special: function (value) { return /[^\p{L}\p{N}\s]/u.test(value); }
    };

    var toggles = [];

    /* ---------- Show / hide password ---------- */

    function setupToggle(button) {
        var input = document.getElementById(button.getAttribute("aria-controls") || "");

        if (!(input instanceof HTMLInputElement) || input.type !== "password") {
            return;
        }

        function setVisible(visible) {
            input.type = visible ? "text" : "password";
            button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
            button.setAttribute("aria-pressed", visible ? "true" : "false");

            // CSS swaps the eye / eye-slash icons (no inline styles).
            button.classList.toggle("is-showing", visible);
        }

        button.addEventListener("click", function () {
            setVisible(input.type === "password");
            input.focus();
        });

        setVisible(false);
        toggles.push(function () { setVisible(false); });

        var wrapper = button.closest(".password-field");

        if (wrapper) {
            wrapper.classList.add("has-toggle");
        }

        button.hidden = false;
    }

    function hideAllPasswords() {
        toggles.forEach(function (hide) { hide(); });
    }

    /* ---------- Live checklist ---------- */

    function setupChecklist(list) {
        var input = document.getElementById(list.getAttribute("data-password-rules-for") || "");

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        var items = list.querySelectorAll("li[data-rule]");

        function update() {
            var value = input.value;

            items.forEach(function (item) {
                var test = RULES[item.getAttribute("data-rule")];
                var met = test ? test(value) : false;
                var state = item.querySelector(".password-checklist__state");

                item.classList.toggle("is-met", met);

                if (state) {
                    state.textContent = met ? " (done)" : " (not yet)";
                }
            });

            list.classList.toggle("is-active", value !== "");
        }

        input.addEventListener("input", update);
        update();
    }

    /* ---------- Passwords match ---------- */

    function setupMatch(message) {
        var confirm = document.getElementById(message.getAttribute("data-password-match-for") || "");
        var original = document.getElementById(message.getAttribute("data-password-match-with") || "");

        if (!(confirm instanceof HTMLInputElement) || !(original instanceof HTMLInputElement)) {
            return;
        }

        function update() {
            if (confirm.value === "") {
                message.textContent = "";
                message.className = "password-match";
                return;
            }

            var matches = confirm.value === original.value;

            message.textContent = matches ? "Passwords match" : "Passwords do not match";
            message.className = "password-match " + (matches ? "is-match" : "is-mismatch");
        }

        confirm.addEventListener("input", update);
        original.addEventListener("input", update);
        update();
    }

    /* ---------- Submit state ---------- */

    function setupBusyForm(form) {
        var button = form.querySelector('button[type="submit"][data-busy-text]');

        if (!button) {
            return;
        }

        var idleText = button.textContent.trim();
        var submitting = false;

        form.addEventListener("submit", function (event) {
            if (event.defaultPrevented) {
                return;
            }

            if (submitting) {
                event.preventDefault();
                return;
            }

            hideAllPasswords();
            submitting = true;
            button.disabled = true;
            button.textContent = button.getAttribute("data-busy-text");
            form.setAttribute("aria-busy", "true");
        });

        // Restore the button after browser back/forward navigation.
        window.addEventListener("pageshow", function () {
            submitting = false;
            button.disabled = false;
            button.textContent = idleText;
            form.removeAttribute("aria-busy");
        });
    }

    function initialise() {
        document.querySelectorAll("[data-password-toggle]").forEach(setupToggle);
        document.querySelectorAll("[data-password-rules-for]").forEach(setupChecklist);
        document.querySelectorAll("[data-password-match-for]").forEach(setupMatch);
        document.querySelectorAll("form[data-auth-form]").forEach(setupBusyForm);

        // Mask passwords again when leaving the page or switching tabs.
        window.addEventListener("pagehide", hideAllPasswords);
        document.addEventListener("visibilitychange", function () {
            if (document.hidden) {
                hideAllPasswords();
            }
        });

        // Move keyboard focus to a server-side error so it is announced.
        var error = document.querySelector(".auth-message--error[tabindex]");

        if (error) {
            error.focus();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise, { once: true });
    } else {
        initialise();
    }
})();
