/*
 * DiSSENT registration password visibility controls.
 *
 * Each button controls only its corresponding input.
 * The script does not log, store or send passwords.
 */

(function () {
    'use strict';

    const root = document.getElementById('dissent-registration');

    if (!root) {
        return;
    }

    const form = root.querySelector('#dissent-register-form');

    if (!form) {
        return;
    }

    const controls = [];

    root.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        const targetId = button.getAttribute('aria-controls');

        const input = targetId
            ? document.getElementById(targetId)
            : null;

        /*
         * Only attach to a password input belonging
         * to this registration form.
         */
        if (
            !(input instanceof HTMLInputElement) ||
            !root.contains(input) ||
            input.form !== form ||
            input.type !== 'password'
        ) {
            return;
        }

        const label =
            button.getAttribute('data-password-label') || 'password';

        const wrapper = button.closest('.register-password-control');

        function setVisible(visible) {
            input.type = visible ? 'text' : 'password';

            button.textContent = visible ? 'Hide' : 'Show';

            button.setAttribute(
                'aria-label',
                (visible ? 'Hide ' : 'Show ') + label
            );
        }

        button.addEventListener('click', function () {
            setVisible(input.type === 'password');
        });

        controls.push(function () {
            setVisible(false);
        });

        /* Passwords start hidden. */
        setVisible(false);

        if (wrapper) {
            wrapper.setAttribute('data-password-ready', 'true');
        }

        /* Reveal the button only after its handler is ready. */
        button.hidden = false;
    });

    function hideAll() {
        controls.forEach(function (hide) {
            hide();
        });
    }

    /*
     * Restore masking on submission, reset, page navigation,
     * or when the browser tab is hidden.
     *
     * Changing visibility does not change the password value.
     */
    form.addEventListener('submit', hideAll);
    form.addEventListener('reset', hideAll);

    window.addEventListener('pagehide', hideAll);
    window.addEventListener('pageshow', hideAll);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            hideAll();
        }
    });
}());